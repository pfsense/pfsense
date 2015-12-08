<?php
/*
	diag_dump_states.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Copyright (c)  2005 Colin Smith
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */
/*
	pfSense_BUILDER_BINARIES:	/sbin/pfctl
	pfSense_MODULE: filter
*/

##|+PRIV
##|*IDENT=page-diagnostics-showstates
##|*NAME=Diagnostics: Show States
##|*DESCR=Allow access to the 'Diagnostics: Show States' page.
##|*MATCH=diag_dump_states.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("interfaces.inc");

/* handle AJAX operations */
if (isset($_POST['action']) && $_POST['action'] == "remove") {
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

$pgtitle = array(gettext("Diagnostics"), gettext("Show States"));
include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[
events.push(function(){
	$('a[data-entry]').on('click', function(){
		var el = $(this);
		var data = $(this).data('entry').split('|');

		$.ajax(
			'/diag_dump_states.php',
			{
				type: 'post',
				data: {
					action: 'remove',
					srcip: data[0],
					dstip: data[1]
				},
				success: function(){
					el.parents('tr').remove();
				},
		});
	});
});
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

$form = new Form(false);

$section = new Form_Section('State filter');

$section->addInput(new Form_Input(
	'filter',
	'Filter expression',
	'text',
	$_POST['filter'],
	['placeholder' => 'Simple filter such as 192.168, v6, icmp or ESTABLISHED']
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
<table class="table table-striped table-condensed table-hover sortable-theme-bootstrap" data-sortable>
	<thead>
		<tr>
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
	$fd = popen("/sbin/pfctl -s state {$grepline}", "r");
	while ($line = chop(fgets($fd))) {
		if ($row >= 10000)
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
		<tr>
			<td><?= $iface ?></td>
			<td><?= $proto ?></td>
			<td><?= $info ?></td>
			<td><?= $state ?></td>

			<td>
				<a class="btn fa fa-times" data-entry="<?=$srcip?>|<?=$dstip?>"
					title="<?=sprintf(gettext('Remove all state entries from %s to %s'), $srcip, $dstip);?>"></a>
			</td>
		</tr>
<?php $row++; } 
?>
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

include("foot.inc");
