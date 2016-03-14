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

##|+PRIV
##|*IDENT=page-diagnostics-showstates
##|*NAME=Diagnostics: Show States
##|*DESCR=Allow access to the 'Diagnostics: Show States' page.
##|*MATCH=diag_dump_states.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("interfaces.inc");

function get_ip($addr) {

	$parts = explode(":", $addr);
	if (count($parts) == 2)
		return (trim($parts[0]));
	else {
		/* IPv6 */
		$parts = explode("[", $addr);
		if (count($parts) == 2)
			return (trim($parts[0]));
	}

	return ("");
}

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

$pgtitle = array(gettext("Diagnostics"), gettext("States"), gettext("States"));
include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('a[data-entry]').on('click', function() {
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
				success: function() {
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
if (isset($config['system']['lb_use_sticky'])) {
	$tab_array[] = array(gettext("Source Tracking"), false, "diag_dump_states_sources.php");
}
$tab_array[] = array(gettext("Reset States"), false, "diag_resetstate.php");
display_top_tabs($tab_array);

// Start of tab content
$current_statecount=`pfctl -si | grep "current entries" | awk '{ print $3 }'`;

$form = new Form(false);

$section = new Form_Section('State Filter', 'secfilter', COLLAPSIBLE|SEC_OPEN);

$iflist = get_configured_interface_with_descr();
$iflist['lo0'] = "lo0";
$iflist['all'] = "all";
if (isset($_POST['interface']))
	$ifselect = $_POST['interface'];
else
	$ifselect = "all";

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$ifselect,
	$iflist
));

$section->addInput(new Form_Input(
	'filter',
	'Filter expression',
	'text',
	$_POST['filter'],
	['placeholder' => 'Simple filter such as 192.168, v6, icmp or ESTABLISHED']
));

$filterbtn = new Form_Button(
	'filterbtn',
	gettext('Filter'),
	null,
	'fa-filter'
);
$filterbtn->addClass('btn-primary btn-sm');
$section->addInput(new Form_StaticText(
	'',
	$filterbtn
));

if (isset($_POST['filter']) && (is_ipaddr($_POST['filter']) || is_subnet($_POST['filter']))) {
	$killbtn = new Form_Button(
		'killfilter',
		gettext('Kill States'),
		null,
		'fa-trash'
	);
	$killbtn->addClass('btn-danger btn-sm');
	$section->addInput(new Form_StaticText(
		'Kill filtered states',
		$killbtn
	))->setHelp('Remove all states to and from the filtered address');
}

$form->add($section);
print $form;
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("States")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-condensed table-hover sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Interface")?></th>
						<th><?=gettext("Protocol")?></th>
						<th><?=gettext("Source -> Router -> Destination")?></th>
						<th><?=gettext("State")?></th>
						<th><?=gettext("Packets")?></th>
						<th><?=gettext("Bytes")?></th>
						<th></th> <!-- For the optional "Remove" button -->
					</tr>
				</thead>
				<tbody>
<?php
	$arr = array();
	/* RuleId filter. */
	if (isset($_REQUEST['ruleid'])) {
		$ids = explode(",", $_REQUEST['ruleid']);
		for ($i = 0; $i < count($ids); $i++)
			$arr[] = array("ruleid" => intval($ids[$i]));
	}

	/* Interface filter. */
	if (isset($_POST['interface']) && $_POST['interface'] != "all")
		$arr[] = array("interface" => get_real_interface($_POST['interface']));

	if (isset($_POST['filter']) && strlen($_POST['filter']) > 0)
		$arr[] = array("filter" => $_POST['filter']);

	if (count($arr) > 0)
		$res = pfSense_get_pf_states($arr);
	else
		$res = pfSense_get_pf_states();

	$states = 0;
	if ($res != NULL && is_array($res))
		$states = count($res);

	/* XXX - limit to 10.000 states. */
	if ($states > 10000)
		$states = 10000;

	for ($i = 0; $i < $states; $i++) {
		if ($res[$i]['direction'] === "out") {
			$info = $res[$i]['src'];
			if ($res[$i]['src-orig'])
				$info .= " (" . $res[$i]['src-orig'] . ")";
			$info .= " -> ";
			$info .= $res[$i]['dst'];
			if ($res[$i]['dst-orig'])
				$info .= " (" . $res[$i]['dst-orig'] . ")";
			$srcip = get_ip($res[$i]['src']);
			$dstip = get_ip($res[$i]['dst']);
		} else {
			$info = $res[$i]['dst'];
			if ($res[$i]['dst-orig'])
				$info .= " (" . $res[$i]['dst-orig'] . ")";
			$info .= " &lt;- ";
			$info .= $res[$i]['src'];
			if ($res[$i]['src-orig'])
				$info .= " (" . $res[$i]['src-orig'] . ")";
			$srcip = get_ip($res[$i]['dst']);
			$dstip = get_ip($res[$i]['src']);
		}

?>
					<tr>
						<td><?= convert_real_interface_to_friendly_descr($res[$i]['if']) ?></td>
						<td><?= $res[$i]['proto'] ?></td>
						<td><?= $info ?></td>
						<td><?= $res[$i]['state'] ?></td>
						<td><?= format_number($res[$i]['packets in']) ?> /
						    <?= format_number($res[$i]['packets out']) ?></td>
						<td><?= format_bytes($res[$i]['bytes in']) ?> /
						    <?= format_bytes($res[$i]['bytes out']) ?></td>

						<td>
							<a class="btn fa fa-trash" data-entry="<?=$srcip?>|<?=$dstip?>"
								title="<?=sprintf(gettext('Remove all state entries from %1$s to %2$s'), $srcip, $dstip);?>"></a>
						</td>
					</tr>
<?
	}
?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php

if ($states == 0) {
	if (isset($_POST['filter']) && !empty($_POST['filter'])) {
		$errmsg = gettext('No states were found that match the current filter.');
	} else {
		$errmsg = gettext('No states were found.');
	}

	print_info_box($errmsg, 'warning', false);
}

include("foot.inc");
