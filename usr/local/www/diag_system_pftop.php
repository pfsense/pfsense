<?php
/* $Id$ */
/*
	diag_system_pftop.php
	Copyright (C) 2008-2009 Scott Ullrich
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
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-diagnostics-system-pftop
##|*NAME=Diagnostics: pfTop
##|*DESCR=Allows access to the 'Diagnostics: pfTop' page
##|*MATCH=diag_system_pftop.php*
##|-PRIV

require("guiconfig.inc");

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

<form method="post" action="diag_system_pftop.php">
<script type="text/javascript">
//<![CDATA[
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
		jQuery('#pftopactivitydiv').html('<font face="Courier" size="2"><pre style="text-align:left;">' + transport.responseText  + '<\/pre><\/font>');
		setTimeout('getpftopactivity()', 2500);
	}
	setTimeout('getpftopactivity()', 1000);
//]]>
</script>


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
			<div class="form-group col-sm-2" id='viewtypediv'><?=gettext("View type:"); ?>
				<select  class="form-control col-sm-2" name='viewtype' id='viewtype'>
					<option value='default' <?=($viewtype == "default") ? "selected=\"selected\"" : ""; ?>><?=gettext("Default")?></option>
					<option value='label' <?=($viewtype == "label") ? "selected=\"selected\"" : ""; ?>><?=gettext("Label")?></option>
					<option value='long' <?=($viewtype == "long") ? "selected=\"selected\"" : ""; ?>><?=gettext("Long")?></option>
					<option value='queue' <?=($viewtype == "queue") ? "selected=\"selected\"" : ""; ?>><?=gettext("Queue")?></option>
					<option value='rules' <?=($viewtype == "rules") ? "selected=\"selected\"" : ""; ?>><?=gettext("Rules")?></option>
					<option value='size' <?=($viewtype == "size") ? "selected=\"selected\"" : ""; ?>><?=gettext("Size")?></option>
					<option value='speed' <?=($viewtype == "speed") ? "selected=\"selected\"" : ""; ?>><?=gettext("Speed")?></option>
					<option value='state' <?=($viewtype == "state") ? "selected=\"selected\"" : ""; ?>><?=gettext("State")?></option>
					<option value='time' <?=($viewtype == "time") ? "selected=\"selected\"" : ""; ?>><?=gettext("Time")?></option>
				</select>
			</div>
		    <div class="form-group col-sm-2" id='sorttypediv'><?=gettext("Sort type:"); ?>
				<select class="form-control col-sm-2" name='sorttype' id='sorttype'>
					<option value='age' <?=($sorttype == "age") ? "selected=\"selected\"" : ""; ?>><?=gettext("Age")?></option>
					<option value='bytes' <?=($sorttype == "bytes") ? "selected=\"selected\"" : ""; ?>><?=gettext("Bytes")?></option>
					<option value='dest' <?=($sorttype == "dest") ? "selected=\"selected\"" : ""; ?>><?=gettext("Destination Address")?></option>
					<option value='dport' <?=($sorttype == "dport") ? "selected=\"selected\"" : ""; ?>><?=gettext("Destination Port")?></option>
					<option value='exp' <?=($sorttype == "exp") ? "selected=\"selected\"" : ""; ?>><?=gettext("Expiry")?></option>
					<option value='none' <?=($sorttype == "none") ? "selected=\"selected\"" : ""; ?>><?=gettext("None")?></option>
					<option value='peak' <?=($sorttype == "peak") ? "selected=\"selected\"" : ""; ?>><?=gettext("Peak")?></option>
					<option value='pkt' <?=($sorttype == "pkt") ? "selected=\"selected\"" : ""; ?>><?=gettext("Packet")?></option>
					<option value='rate' <?=($sorttype == "rate") ? "selected=\"selected\"" : ""; ?>><?=gettext("Rate")?></option>
					<option value='size' <?=($sorttype == "size") ? "selected=\"selected\"" : ""; ?>><?=gettext("Size")?></option>
					<option value='sport' <?=($sorttype == "sport") ? "selected=\"selected\"" : ""; ?>><?=gettext("Source Port")?></option>
					<option value='src' <?=($sorttype == "src") ? "selected=\"selected\"" : ""; ?>><?=gettext("Source Address")?></option>
				</select>
			</div>
		    <div class="form-group col-sm-2" id='statesdiv'><?=gettext("# of States:"); ?>
				<select class="form-control col-sm-2 name=" states' id='states'>
					<option value='50' <?=($numstate == "50") ? "selected=\"selected\"" : ""; ?>>50</option>
					<option value='100' <?=($numstate == "100") ? "selected=\"selected\"" : ""; ?>>100</option>
					<option value='200' <?=($numstate == "200") ? "selected=\"selected\"" : ""; ?>>200</option>
					<option value='500' <?=($numstate == "500") ? "selected=\"selected\"" : ""; ?>>500</option>
					<option value='1000' <?=($numstate == "1000") ? "selected=\"selected\"" : ""; ?>>1000</option>
					<option value='all' <?=($numstate == "all") ? "selected=\"selected\"" : ""; ?>>all</option>
				</select>
			</div>
        </div>
    </div>

    <div class="panel panel-default panel-info">
        <div class="panel-heading">Output</div>
	    <div class="panel panel-body" id="pftopactivitydiv">
	        <?=gettext("Gathering pfTOP activity, please wait...")?>
	    </div>									
    </div>
    
    </div>
</form>
    
<?php include("foot.inc"); ?>

<script type="text/javascript">
//<![CDATA[
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
//]]>
</script>
