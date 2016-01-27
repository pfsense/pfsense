<?php
/*
	diag_dump_states_sources.php
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

##|+PRIV
##|*IDENT=page-diagnostics-sourcetracking
##|*NAME=Diagnostics: Show Source Tracking
##|*DESCR=Allow access to the 'Diagnostics: Show Source Tracking' page.
##|*MATCH=diag_dump_states_sources.php*
##|-PRIV

require_once("guiconfig.inc");

/* handle AJAX operations */
if ($_POST['action']) {
	if ($_POST['action'] == "remove") {
		if (is_ipaddr($_POST['srcip']) && is_ipaddr($_POST['dstip'])) {
			$retval = mwexec("/sbin/pfctl -K " . escapeshellarg($_POST['srcip']) . " -K " . escapeshellarg($_POST['dstip']));
			echo htmlentities("|{$_GET['srcip']}|{$_POST['dstip']}|{$retval}|");
		} else {
			echo gettext("invalid input");
		}
		exit;
	}
}

/* get our states */
if ($_POST['filter']) {
	exec("/sbin/pfctl -s Sources | grep " . escapeshellarg(htmlspecialchars($_GET['filter'])), $sources);
} else {
	exec("/sbin/pfctl -s Sources", $sources);
}


$pgtitle = array(gettext("Diagnostics"), gettext("Show Source Tracking"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("States"), false, "diag_dump_states.php");
$tab_array[] = array(gettext("Source Tracking"), true, "diag_dump_states_sources.php");
$tab_array[] = array(gettext("Reset States"), false, "diag_resetstate.php");
display_top_tabs($tab_array);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('a[data-entry]').on('click', function() {
		var el = $(this);
		var data = $(this).data('entry').split('|');

		$.ajax(
			'/diag_dump_states_sources.php',
			{
				type: 'post',
				data: {
					action: 'remove',
					srcip: data[0],
					dstip: data[1]
				},
				success: function() {
					el.parents('tr').remove();
				},
		});
	});
});
//]]>
</script>

<?php

$form = new Form;
$section = new Form_Section('Filters');

$section->addInput(new Form_Input(
	'filter',
	'Filter expression',
	'text',
	$_POST['filter']
));

$form->add($section);
print $form;

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Current source tracking entries")?></h2></div>
	<div class="panel-body">
		<table class="table table-striped">
			<thead>
				<tr>
					<th><?=gettext("Source -> Destination")?></th>
					<th><?=gettext("# States")?></th>
					<th><?=gettext("# Connections")?></th>
					<th><?=gettext("Rate")?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
<?php
$row = 0;
if (count($sources) > 0) {
	foreach ($sources as $line) {
		if ($row >= 1000) {
			break;
		}

		// 192.168.20.2 -> 216.252.56.1 ( states 10, connections 0, rate 0.0/0s )

		$source_split = "";
		preg_match("/(.*)\s\(\sstates\s(.*),\sconnections\s(.*),\srate\s(.*)\s\)/", $line, $source_split);
		list($all, $info, $numstates, $numconnections, $rate) = $source_split;

		$source_split = "";
		preg_match("/(.*)\s\<?-\>?\s(.*)/", $info, $source_split);
		list($all, $srcip, $dstip) = $source_split;
?>
				<tr>
					<td><?= $info ?></td>
					<td><?= $numstates ?></td>
					<td><?= $numconnections ?></td>
					<td><?= $rate ?></td>

					<td>
						<a class="btn btn-xs btn-danger" data-entry="<?=$srcip?>|<?=$dstip?>"
							title="<?=sprintf(gettext('Remove all source tracking entries from %1$s to %2$s'), $srcip, $dstip);?>"><?=gettext("Remove")?></a>
					</td>
				</tr>
<?php
		$row++;
	}
}
?>
			</tbody>
		</table>
	</div>
</div>
<?php
if ($row == 0) {
	print('<p class="alert alert-warning">' . gettext('No source tracking entries were found.') . '</p>');
}

include("foot.inc");
