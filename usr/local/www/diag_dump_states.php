<?php
/*
	diag_dump_states.php
	Copyright (C) 2005 Colin Smith
	Copyright (C) 2005-2009 Scott Ullrich
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
	pfSense_BUILDER_BINARIES:	/sbin/pfctl
	pfSense_MODULE: filter
*/

##|+PRIV
##|*IDENT=page-diagnostics-showstates
##|*NAME=Diagnostics: Show States page
##|*DESCR=Allow access to the 'Diagnostics: Show States' page.
##|*MATCH=diag_dump_states.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("interfaces.inc");

/* handle AJAX operations */
if(isset($_POST['action']) && $_POST['action'] == "remove") {
	if (isset($_POST['srcip']) && isset($_POST['dstip']) && is_ipaddr($_POST['srcip']) && is_ipaddr($_POST['dstip'])) {
		$retval = pfSense_kill_states($_POST['srcip'], $_POST['dstip']);
		echo htmlentities("|{$_POST['srcip']}|{$_POST['dstip']}|0|");
	} else {
		echo gettext("invalid input");
	}
	return;
}

if (isset($_POST['filter']) && isset($_POST['killfilter'])) {
	if (is_ipaddr($_POST['filter'])) {
		$tokill = $_POST['filter'] . "/32";
	} elseif (is_subnet($_POST['filter'])) {
		$tokill = $_POST['filter'];
	} else {
		// Invalid filter
		$tokill = "";
	}
	if (!empty($tokill)) {
		$retval = pfSense_kill_states($tokill);
		$retval = pfSense_kill_states("0.0.0.0/0", $tokill);
	}
}

$pgtitle = array(gettext("Diagnostics"),gettext("Show States"));
include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[
	function removeState(srcip, dstip) {
		var busy = function(index,icon) {
			jQuery(icon).bind("onclick","");
			jQuery(icon).attr('src',jQuery(icon).attr('src').replace("\.gif", "_d.gif"));
			jQuery(icon).css("cursor","wait");
		}

		jQuery('img[name="i:' + srcip + ":" + dstip + '"]').each(busy);

		jQuery.ajax(
			"<?=$_SERVER['SCRIPT_NAME']?>",
			{
				type: "post",
				data: {
					action: "remove",
					srcip: srcip,
					dstip: dstip
				},
				complete: removeComplete
			}
		);
	}

	function removeComplete(req) {
		var values = req.responseText.split("|");
		if(values[3] != "0") {
			alert('<?=gettext("An error occurred.")?>');
			return;
		}

		jQuery('tr[id="r:' + values[1] + ":" + values[2] + '"]').each(
			function(index,row) { jQuery(row).fadeOut(1000); }
		);
	}
//]]>
</script>

<?php
$tab_array = array();
$tab_array[] = array(gettext("States"), true, "diag_dump_states.php");
if (isset($config['system']['lb_use_sticky']))
	$tab_array[] = array(gettext("Source Tracking"), false, "diag_dump_states_sources.php");
$tab_array[] = array(gettext("Reset States"), false, "diag_resetstate.php");
display_top_tabs($tab_array);

// Start of tab content
$current_statecount=`pfctl -si | grep "current entries" | awk '{ print $3 }'`;

require('classes/Form.class.php');

$form = new Form(false);

$section = new Form_Section('State filter');

$section->addInput(new Form_Input(
	'filter',
	'Filter expression',
	'text',
	$_POST['filter'],
	['placeholder' => '']
));

$filterbtn = new Form_Button('filterbtn', 'Filter', null);
$filterbtn->removeClass('btn-primary')->addClass('btn-default btn-sm');
$section->addInput(new Form_StaticText(
	'',
	 $filterbtn
));

if (isset($_POST['filter']) && (is_ipaddr($_POST['filter']) || is_subnet($_POST['filter']))) {
	$killbtn = new Form_Button('killfilter', 'Kill States');
	$killbtn->removeClass('btn-primary')->addClass('btn-danger btn-sm');
	$section->addInput(new Form_StaticText(
	   'Kill filtered states',
	   $killbtn
	))->setHelp('Remove all states to and from the filtered address');
}

$form->add($section);
print $form;
?>

<table class="table table-striped table-hover table-compact">
	<thead>
		<tr class="info">
			<th><?=gettext("Int")?></th>
			<th><?=gettext("Proto")?></th>
			<th><?=gettext("Source -> Router -> Destination")?></th>
			<th><?=gettext("State")?></th>
			<th></th> <!-- For the optional "Remove" button -->
		</tr>
	</thead>
	<tbody>
<?php
	$row = 0;
	/* get our states */
	$grepline = (isset($_POST['filter'])) ? "| /usr/bin/egrep " . escapeshellarg(htmlspecialchars($_POST['filter'])) : "";
	$fd = popen("/sbin/pfctl -s state {$grepline}", "r" );
	while ($line = chop(fgets($fd))) {
		if($row >= 10000)
			break;

		$line_split = preg_split("/\s+/", $line);

		$iface	= array_shift($line_split);
		$proto = array_shift($line_split);
		$state = array_pop($line_split);
		$info  = implode(" ", $line_split);

		// We may want to make this optional, with a large state table, this could get to be expensive.
		$iface = convert_real_interface_to_friendly_descr($iface);

		/* break up info and extract $srcip and $dstip */
		$ends = preg_split("/\<?-\>?/", $info);
		$parts = explode(":", $ends[0]);
		$srcip = trim($parts[0]);
		$parts = explode(":", $ends[count($ends) - 1]);
		$dstip = trim($parts[0]);
?>

		<tr id="r:<?= $srcip ?>:<?= $dstip ?>">
			<td><?= $iface ?></td>
			<td><?= $proto ?></td>
			<td><?= $info ?></td>
			<td><?= $state ?></td>

			<td>
				<a class="btn btn-xs btn-danger"
				   onclick="removeState('<?= $srcip ?>', '<?= $dstip ?>');" name="i:<?= $srcip ?>:<?= $dstip ?>"
				   title="<?= gettext('Remove all state entries from ') ?><?= $srcip ?><?= gettext(' to ') ?><?= $dstip ?>" alt="">Remove</a>
			</td>
		</tr>
<?php $row++; } ?>
	</tbody>
</table>
<?php

if ($row == 0) {
	if (isset($_POST['filter']) && !empty($_POST['filter']))
		$errmsg = gettext('No states were found that match the current filter');
	else
		$errmsg = gettext('No states were found');

	print('<p class="alert alert-warning">' . $errmsg . '</p>');
}

include("foot.inc"); ?>