<?php
/* $Id$ */
/*
    diag_system_pftop.php
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
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-diagnostics-system-pftop
##|*NAME=Diagnostics: pfTop
##|*DESCR=Allows access to the 'Diagnostics: pfTop' page
##|*MATCH=diag_system_pftop.php*
##|-PRIV

require("guiconfig.inc");

$pfSversion = str_replace("\n", "", file_get_contents("/etc/version"));

$pgtitle = gettext("Diagnostics: pfTop");

$sorttypes = array('age', 'bytes', 'dest', 'dport', 'exp', 'none', 'peak', 'pkt', 'rate', 'size', 'sport', 'src');
$viewtypes = array('default', 'label', 'long', 'queue', 'rules', 'size', 'speed', 'state', 'time');
$viewall = array('queue', 'label', 'rules');
$numstates = array('50', '100', '200', '500', '1000', 'all');

if($_REQUEST['getactivity']) {
	if($_REQUEST['sorttype'] && in_array($_REQUEST['sorttype'], $sorttypes)
		&& $_REQUEST['viewtype'] && in_array($_REQUEST['viewtype'], $viewtypes)
		&& $_REQUEST['states'] && in_array($_REQUEST['states'], $numstates)) {
		$viewtype = escapeshellarg($_REQUEST['viewtype']);
		if (in_array($_REQUEST['viewtype'], $viewall)) {
			$sorttype = "";
			$numstate = "-a";
		} else {
			$sorttype = "-o " . escapeshellarg($_REQUEST['sorttype']);
			$numstate = ($_REQUEST['states'] == "all" ? "-a" : escapeshellarg($_REQUEST['states']));
		}
	} else {
		$sorttype = "bytes";
		$viewtype = "default";
		$numstate = "100";
	}

	$text = `pftop -b {$sorttype} -v {$viewtype} {$numstate}`;
	echo $text;
	exit;
}

include("head.inc");

if($_REQUEST['sorttype'] && in_array($_REQUEST['sorttype'], $sorttypes)
	&& $_REQUEST['viewtype'] && in_array($_REQUEST['viewtype'], $viewtypes)
	&& $_REQUEST['states'] && in_array($_REQUEST['states'], $numstates)) {
	$viewtype = escapeshellarg($_REQUEST['viewtype']);
	if (in_array($_REQUEST['viewtype'], $viewall)) {
		$sorttype = "";
		$numstate = "-a";
	} else {
		$sorttype = "-o " . escapeshellarg($_REQUEST['sorttype']);
		$numstate = ($_REQUEST['states'] == "all" ? "-a" : escapeshellarg($_REQUEST['states']));
	}
} else {
	$sorttype = "bytes";
	$viewtype = "default";
	$numstate = "100";
}

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<form method="post" action="diag_system_pftop.php">
<script type="text/javascript">
	function getpftopactivity() {
		var url = "/diag_system_pftop.php";
		var pars = 'getactivity=yes&sorttype=' + jQuery('#sorttype').val() + '&viewtype=' + jQuery('#viewtype').val() + '&states=' + jQuery('#states').val();
		jQuery.ajax(
			url,
			{
				type: 'post',
				data: pars,
				complete: activitycallback
			});
	}
	function activitycallback(transport) {
		jQuery('#pftopactivitydiv').html('<font face="Courier"><font size="2"><b><pre style="text-align:left;">' + transport.responseText  + '</pre></font>');
		setTimeout('getpftopactivity()', 2500);
	}
	setTimeout('getpftopactivity()', 1000);
</script>
<div id='maincontent'>
<?php
	include("fbegin.inc"); 
	if(strstr($pfSversion, "1.2")) 
		echo "<p class=\"pgtitle\">{$pgtitle}</p>";
	if($savemsg) {
		echo "<div id='savemsg'>";
		print_info_box($savemsg);
		echo "</div>";	
	}
	if ($input_errors)
		print_input_errors($input_errors);
?>
	<form method="post">
		<div id="mainarea" style="padding-bottom: 0px;">
		<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td class="list">
					<div id='viewtypediv'><?=gettext("View type:"); ?>
						<select name='viewtype' id='viewtype'>
							<option value='default' <?php echo ($viewtype == "default") ? "selected" : ""; ?>><?=gettext("Default");?></option>
							<option value='label' <?php echo ($viewtype == "label") ? "selected" : ""; ?>><?=gettext("Label");?></option>
							<option value='long' <?php echo ($viewtype == "long") ? "selected" : ""; ?>><?=gettext("Long");?></option>
							<option value='queue' <?php echo ($viewtype == "queue") ? "selected" : ""; ?>><?=gettext("Queue");?></option>
							<option value='rules' <?php echo ($viewtype == "rules") ? "selected" : ""; ?>><?=gettext("Rules");?></option>
							<option value='size' <?php echo ($viewtype == "size") ? "selected" : ""; ?>><?=gettext("Size");?></option>
							<option value='speed' <?php echo ($viewtype == "speed") ? "selected" : ""; ?>><?=gettext("Speed");?></option>
							<option value='state' <?php echo ($viewtype == "state") ? "selected" : ""; ?>><?=gettext("State");?></option>
							<option value='time' <?php echo ($viewtype == "time") ? "selected" : ""; ?>><?=gettext("Time");?></option>
						</select>
					</div>
				</td>
				<td class="list">
					<div id='sorttypediv'><?=gettext("Sort type:"); ?>
						<select name='sorttype' id='sorttype'>
							<option value='age' <?php echo ($sorttype == "age") ? "selected" : ""; ?>><?=gettext("Age");?></option>
							<option value='bytes' <?php echo ($sorttype == "bytes") ? "selected" : ""; ?>><?=gettext("Bytes");?></option>
							<option value='dest' <?php echo ($sorttype == "dest") ? "selected" : ""; ?>><?=gettext("Destination Address");?></option>
							<option value='dport' <?php echo ($sorttype == "dport") ? "selected" : ""; ?>><?=gettext("Destination Port");?></option>
							<option value='exp' <?php echo ($sorttype == "exp") ? "selected" : ""; ?>><?=gettext("Expiry");?></option>
							<option value='none' <?php echo ($sorttype == "none") ? "selected" : ""; ?>><?=gettext("None");?></option>
							<option value='peak' <?php echo ($sorttype == "peak") ? "selected" : ""; ?>><?=gettext("Peak");?></option>
							<option value='pkt' <?php echo ($sorttype == "pkt") ? "selected" : ""; ?>><?=gettext("Packet");?></option>
							<option value='rate' <?php echo ($sorttype == "rate") ? "selected" : ""; ?>><?=gettext("Rate");?></option>
							<option value='size' <?php echo ($sorttype == "size") ? "selected" : ""; ?>><?=gettext("Size");?></option>
							<option value='sport' <?php echo ($sorttype == "sport") ? "selected" : ""; ?>><?=gettext("Source Port");?></option>
							<option value='src' <?php echo ($sorttype == "src") ? "selected" : ""; ?>><?=gettext("Source Address");?></option>
						</select>
					</div>
				</td>
				<td class="list">
					<div id='statesdiv'><?=gettext("Number of States:"); ?>
						<select name='states' id='states'>
							<option value='50' <?php echo ($numstate == "50") ? "selected" : ""; ?>>50</option>
							<option value='100' <?php echo ($numstate == "100") ? "selected" : ""; ?>>100</option>
							<option value='200' <?php echo ($numstate == "200") ? "selected" : ""; ?>>200</option>
							<option value='500' <?php echo ($numstate == "500") ? "selected" : ""; ?>>500</option>
							<option value='1000' <?php echo ($numstate == "1000") ? "selected" : ""; ?>>1000</option>
							<option value='all' <?php echo ($numstate == "all") ? "selected" : ""; ?>>all</option>
						</select>
					</div>
				</td>
			</tr>
			<tr>
				<td colspan=3 align=center>
					<table id="backuptable" class="tabcont" align="center" width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td>
								<center>
									<table>
										<tr>
											<td>
												<div name='pftopactivitydiv' id='pftopactivitydiv'><b><?=gettext("Gathering pfTOP activity, please wait...");?></div>
											</td>
										</tr>
									</table>
								</center>
							</td>
						</tr>
					</table>
				</td>
		  </tr>
		</table>
	</div>
</form>
<?php include("fend.inc"); ?>
<script type="text/javascript">
jQuery("#viewtype").change(function() {
	var selected = jQuery("#viewtype option:selected");
	switch(selected.val()) {
		case "queue":
		case "label":
		case "rules":
			jQuery("#sorttype, #sorttypediv, #statesdiv, #states").hide();
			break;
		default:
			jQuery("#sorttype, #sorttypediv, #statesdiv, #states").show();
	}
});
</script>
</body>
</html>
