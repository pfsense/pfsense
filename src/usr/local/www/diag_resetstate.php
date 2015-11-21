<?php
/*
	diag_resetstate.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
	pfSense_MODULE: filter
*/

##|+PRIV
##|*IDENT=page-diagnostics-resetstate
##|*NAME=Diagnostics: Reset state page
##|*DESCR=Allow access to the 'Diagnostics: Reset state' page.
##|*MATCH=diag_resetstate.php*
##|-PRIV

require("guiconfig.inc");
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

$pgtitle = array(gettext("Diagnostics"), gettext("Reset state"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg, 'alert-success');

$statetablehelp =	'Resetting the state tables will remove all entries from the corresponding tables. This means that all open connections ' .
					'will be broken and will have to be re-established. This may be necessary after making substantial changes to the ' .
					'firewall and/or NAT rules, especially if there are IP protocol mappings (e.g. for PPTP or IPv6) with open connections.' .
					'<br /><br />' .
					'The firewall will normally leave the state tables intact when changing rules.' .
					'<br /><br />' .
					'<strong>NOTE:</strong> If you reset the firewall state table, the browser session may appear to be hung after clicking &quot;Reset&quot;. ' .
					'Simply refresh the page to continue.';

$sourcetablehelp =	'Resetting the source tracking table will remove all source/destination associations. ' .
					'This means that the \"sticky\" source/destination association ' .
					'will be cleared for all clients.' .
					' <br /><br />' .
					'This does not clear active connection states, only source tracking.';

$tab_array = array();
$tab_array[] = array(gettext("States"), false, "diag_dump_states.php");

if (isset($config['system']['lb_use_sticky']))
	$tab_array[] = array(gettext("Source Tracking"), false, "diag_dump_states_sources.php");

$tab_array[] = array(gettext("Reset States"), true, "diag_resetstate.php");
display_top_tabs($tab_array);

$resetbtn = new Form_Button(
	'Submit',
	'Reset'
);

$resetbtn->removeClass('btn-primary')->addClass('btn-danger');

$form = new Form($resetbtn);

$section = new Form_Section('Select states to reset');

$section->addInput(new Form_Checkbox(
	'statetable',
	'State Table',
	'Reset the firewall state table',
	true
))->setHelp($statetablehelp);

if (isset($config['system']['lb_use_sticky'])) {
	$section->addInput(new Form_Checkbox(
		'sourcetracking',
		'Source Tracking',
		'Reset firewall source tracking',
		true
	))->setHelp($sourcetablehelp);
}

$form->add($section);
print $form;
?>

<?php include("foot.inc"); ?>
