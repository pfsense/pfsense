<?php
/*
 * diag_resetstate.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-diagnostics-resetstate
##|*NAME=Diagnostics: Reset states
##|*DESCR=Allow access to the 'Diagnostics: Reset states' page.
##|*MATCH=diag_resetstate.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");

if ($_POST) {
	$savemsg = "";

	if ($_POST['statetable']) {
		filter_flush_state_table();
		if ($savemsg) {
			$savemsg .= " ";
		}
		$savemsg .= gettext("The state table has been flushed successfully.");
	}

	if ($_POST['sourcetracking']) {
		mwexec("/sbin/pfctl -F Sources");
		if ($savemsg) {
			$savemsg .= " <br />";
		}
		$savemsg .= gettext("The source tracking table has been flushed successfully.");
	}
}

$pgtitle = array(gettext("Diagnostics"), gettext("States"), gettext("Reset States"));
$pglinks = array("", "diag_dump_states.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$statetablehelp = sprintf(gettext('Resetting the state tables will remove all entries from the corresponding tables. This means that all open connections ' .
					'will be broken and will have to be re-established. This may be necessary after making substantial changes to the ' .
					'firewall and/or NAT rules, especially if there are IP protocol mappings (e.g. for PPTP or IPv6) with open connections.%1$s' .
					'The firewall will normally leave the state tables intact when changing rules.%2$s' .
					'%3$sNOTE:%4$s Resetting the firewall state table may cause the browser session to appear hung after clicking &quot;Reset&quot;. ' .
					'Simply refresh the page to continue.'), "<br /><br />", "<br /><br />", "<strong>", "</strong>");

$sourcetablehelp = sprintf(gettext('Resetting the source tracking table will remove all source/destination associations. ' .
					'This means that the "sticky" source/destination association ' .
					'will be cleared for all clients.%s' .
					'This does not clear active connection states, only source tracking.'), "<br /><br />");

$tab_array = array();
$tab_array[] = array(gettext("States"), false, "diag_dump_states.php");

if (isset($config['system']['lb_use_sticky'])) {
	$tab_array[] = array(gettext("Source Tracking"), false, "diag_dump_states_sources.php");
}

$tab_array[] = array(gettext("Reset States"), true, "diag_resetstate.php");
display_top_tabs($tab_array);

$form = new Form(false);

$section = new Form_Section('State reset options');

$section->addInput(new Form_Checkbox(
	'statetable',
	'State Table',
	'Reset the firewall state table',
	false
))->setHelp($statetablehelp);

if (isset($config['system']['lb_use_sticky'])) {
	$section->addInput(new Form_Checkbox(
		'sourcetracking',
		'Source Tracking',
		'Reset firewall source tracking',
		false
	))->setHelp($sourcetablehelp);
}

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Reset',
	null,
	'fa-trash'
))->addClass('btn-warning');

print $form;

$nonechecked = gettext("Please select at least one reset option");
$cfmmsg = gettext("Do you really want to reset the selected states?");
?>

<script type="text/javascript">
//<![CDATA[
	events.push(function(){

		$('form').submit(function(event){
			if ( !($('#statetable').prop("checked") == true) && !($('#sourcetracking').prop("checked") == true)) {
				alert("<?=$nonechecked?>");
				event.preventDefault();
			} else if (!confirm("<?=$cfmmsg?>")) {
				event.preventDefault();
			}
		});
	});
//]]>
</script>

<?php include("foot.inc"); ?>
