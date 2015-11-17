<?php
/*
	firewall_shaper_layer7.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Copyright (c)  2008 Helder Pereira, André Ribeiro
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
	pfSense_BUILDER_BINARIES:	/usr/bin/killall
	pfSense_MODULE: shaper
*/

##|+PRIV
##|*IDENT=page-firewall-trafficshaper-layer7
##|*NAME=Firewall: Traffic Shaper: Layer7 page
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper: Layer7' page.
##|*MATCH=firewall_shaper_layer7.php*
##|-PRIV

require("guiconfig.inc");
require_once('classes/Form.class.php');
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$dfltmsg = false;

// Variables protocols (dynamic) and structures (static)
$avail_protos =& generate_protocols_array();
$avail_structures = array("action", "queue", "limiter");

// Available behaviours
$avail_behaviours_action = array("block");
read_altq_config();
$avail_behaviours_altq = get_altq_name_list();
read_dummynet_config();
$avail_behaviours_limiter = get_dummynet_name_list();
$show_proto_form = false;

//More variables
$pgtitle = array(gettext("Firewall"), gettext("Traffic Shaper"), gettext("Layer7"));
$shortcut_section = "trafficshaper";

$default_layer7shaper_msg = '<br />' .
							gettext('You can add new layer7 protocol patterns by simply uploading the file') .
							' <a href="diag_patterns.php">' . gettext('here') . '</a>';

read_layer7_config();

$sform = new Form(false);

if ($_GET['reset'] != "") {
	// kill all ipfw-classifyd processes
	mwexec("killall -9 ipfw-classifyd");
	exit;
}

if ($_GET) {
	if ($_GET['container']) {
		$name = htmlspecialchars(trim($_GET['container']));
	}
	if ($_GET['action']) {
		$action = htmlspecialchars($_GET['action']);
	}
}

if ($_POST) {
	if ($_POST['container']) {
		$name = htmlspecialchars(trim($_POST['container']));
	}
}

if ($name) {
	//Get the object from the 7rules list
	$container = $layer7_rules_list[$name];
}

if ($_GET) {
	switch ($action) {
		case "add":
			$show_proto_form = true;
			$container = new layer7();
			$sform = $container->build_form(); //constructs the graphical interface on the right side
			unset($container);
		break;
		case "show":
			$show_proto_form = true;
			if ($container) {
				$sform = $container->build_form();
			}
			else {
				$show_proto_form = false;
				$input_errors[] = gettext("Layer7 Rules Container not found!");
			}
		break;
		default:
			echo log_error("Get default");
			$show_proto_form = false;
			$dfltmsg = true;
		break;
	}
}

//add a new l7rules container
if ($_POST) {
	$show_proto_form = true;
	unset($input_errors);

	if ($_POST['Submit']) {

		if (isset($layer7_rules_list[$name])) {
			$l7r = $layer7_rules_list[$name];
			$_POST['divert_port'] = $l7r->GetRPort();
		} else {
			$l7r =& new layer7();
			$_POST['divert_port'] = $l7r->gen_divert_port();
		}
		for ($i = 0; $_POST['protocol'][$i] <> ""; $i++) {
			$_POST['l7rules'][$i]['protocol'] = $_POST['protocol'][$i];
			$_POST['l7rules'][$i]['structure'] = $_POST['structure'][$i];
			$_POST['l7rules'][$i]['behaviour'] = $_POST['behaviour'][$i];
		}
		$l7r->validate_input($_POST, $input_errors);
		$l7r->ReadConfig($_POST['container'], $_POST);
		//Before writing the results, we need to test for repeated protocols
		$non_dupes = array();
		$dupes = array();
		for ($j = 0; $j < $i; $j++) {
			if (!$non_dupes[$_POST['protocol'][$j]]) {
				$non_dupes[$_POST['protocol'][$j]] = true;
			} else {
				$dupes[] = $_POST['protocol'][$j];
			}
		}

		unset($non_dupes);
		if (sizeof($dupes) == 0 && !$input_errors) {
			$l7r->wconfig();
			if (write_config()) {
				mark_subsystem_dirty('shaper');
			}

			read_layer7_config();
		} else {
			if (sizeof($dupes) > 0) {
				$dupe_error = gettext("Found the following repeated protocol definitions") . ": ";
				foreach ($dupes as $dupe) {
					$dupe_error .= "$dupe ";
				}
				$input_errors[] .= $dupe_error;
			}
		}

		unset($dupes);
		unset($dupe_error);
		//Even if there are repeated protocols, we won't lose any previous values
		//The user will be able to solve the situation
		$sform = $l7r->build_form();
		//Necessary to correctly build the proto form
		$container = $layer7_rules_list[$name];
		if ($input_errors) {
			$container =& $l7r;
		}
	} else if ($_POST['apply']) {
		write_config();

		$retval = 0;
		$retval = filter_configure();
		$savemsg = get_std_save_message($retval);

		if (stristr($retval, "error") <> true) {
			$savemsg = get_std_save_message($retval);
		} else {
			$savemsg = $retval;
		}

		clear_subsystem_dirty('shaper');

		if ($container) {
			$sform = $container->build_form();
		} else {
			$show_proto_form = false;
			$dfltmsg = true;
		}
	} else if ($_POST['delete']) {
		$container->delete_l7c();
		if (write_config()) {
			mark_subsystem_dirty('shaper');
		}
		unset($container);

		header("Location: firewall_shaper_layer7.php");
		exit;
	} else {
		$show_proto_form = false;
	}
}

if (!$_GET && !$_POST) {
	$show_proto_form = false;
	$dfltmsg = true;
}

// Builds the left tree
$tree = "<ul class=\"tree\" >";
if (is_array($layer7_rules_list)) {
	foreach ($layer7_rules_list as $tmpl7) {
		$tree .= $tmpl7->build_tree();
	}
}

$tree .= "</ul>";

include("head.inc");
?>

<link rel="stylesheet" type="text/css" media="all" href="./tree/tree.css" />
<script type="text/javascript" src="./tree/tree.js"></script>

<script type="text/javascript">
//<![CDATA[
var initial_count = new Array();
var rows_limit = 0; // Set to 0 to disable limitation

/* Build the behaviours arrays in javascript */
var js_behaviours_action = ['block']; //static

var js_behaviours_altq = new Array();
js_behaviours_altq = array_altq(js_behaviours_altq);

var js_behaviours_limiter = new Array();
js_behaviours_limiter = array_limiter(js_behaviours_limiter);

function array_altq(a_behav) {
	var index;
	<?php if (!empty($avail_behaviours_altq)) {
	  foreach ($avail_behaviours_altq as $key => $queue) { ?>
		name = "<?= $queue; ?>";
		index = <?= $key; ?>;
		a_behav[index] = name;
	<?php }
	} ?>
	return a_behav;
}

function array_limiter(a_behav) {
	var index;
	<?php
	if (!empty($avail_behaviours_limiter)) {
		foreach ($avail_behaviours_limiter as $key => $limiter) { ?>
		name = "<?= $limiter; ?>";
		index = <?= $key; ?>;
		a_behav[index] = name;
	<?php
		}
	} ?>
	return a_behav;
}

/* Fill the variables with available protocols, structures and behaviours */
function fillProtocol() {
	var protocol = '<select class="form-control" name="protocol[]">';
	var name;

	<?php foreach ($avail_protos as $key => $proto) { ?>
		name = "<?= $proto; ?>";
		protocol += "<option value=" + name + ">" + name + "<\/option>";
	<?php } ?>
	protocol += "<\/select>";

	return protocol;
}

function fillStructure() {
	var structure = '<select class="form-control" name="structure[]" onchange="changeBehaviourValues(this.parentNode.parentNode);">';
	var name;
	<?php foreach ($avail_structures as $key => $struct) { ?>
		name = "<?= $struct; ?>";
		if (name == "queue") {
		  if (js_behaviours_altq != "") { structure += "<option value=" + name + ">" + name + "<\/option>";}
		}
		else {
		  if (name == "limiter") {
			if (js_behaviours_limiter != "") { structure += "<option value=" + name + ">" + name + "<\/option>";}
		  }
		  else structure += "<option value=" + name + ">" + name + "<\/option>"; //action
		}
	<?php } ?>
	structure += "<\/select>";

	return structure;
}

//Used by default to fill the values when inserting a new row.
function fillBehaviour() {
	var behaviour = '<select class="form-control" name="behaviour[]">';
	var name;
	<?php foreach ($avail_behaviours_action as $key => $behav) { ?>
		name = "<?= $behav; ?>";
		behaviour += "<option value=" + name + ">" + name + "<\/option>";
	<?php } ?>
	behaviour += "<\/select>";

	return behaviour;
}

/* Change the values on behaviours select when changing the structure row */
function changeBehaviourValues(row) {

	var selectedRow = row.rowIndex - 1; // The header is counted as the first row
	var structureSelected = document.getElementsByName("structure[]")[selectedRow].value;

	//Select the behaviours values to array a_behav
	var a_behav = new Array();

	if (structureSelected == "action") {
		a_behav = js_behaviours_action; //static
	} else {
		if (structureSelected == "queue") {
			a_behav = js_behaviours_altq;
		} else {
			a_behav = js_behaviours_limiter;
		}
	}


	//Build the html statement with the array values previously selected
	var new_behav;
	var name;
	for (i = 0; i < a_behav.length; i++) {
		new_behav += "<option value=" + a_behav[i] + ">" + a_behav[i] + "<\/option>";
	}

	document.getElementsByName("behaviour[]")[selectedRow].innerHTML = new_behav;
}

/* Add row to the table */
function addRow(table_id) {
  var tbl = document.getElementById(table_id);

  // counting rows in table
  var rows_count = tbl.rows.length;
  if (initial_count[table_id] == undefined) {
	// if it is first adding in this table setting initial rows count
	initial_count[table_id] = rows_count;
  }
  // determining real count of added fields
  var tFielsNum = rows_count - initial_count[table_id];
  if (rows_limit != 0 && tFielsNum >= rows_limit) return false;

  var remove = '<a class="btn  btn-default" onclick="removeRow(\''+table_id+'\',this.parentNode.parentNode)">Remove<\/a>';

	try {
		var newRow = tbl.insertRow(rows_count);
		var newCell = newRow.insertCell(0);
		newCell.innerHTML = fillProtocol();
		var newCell = newRow.insertCell(1);
		newCell.innerHTML = fillStructure();
		var newCell = newRow.insertCell(2);
		newCell.innerHTML = fillBehaviour();
		var newCell = newRow.insertCell(3);
		newCell.innerHTML = remove;
	}
	catch (ex) {
		//if exception occurs
		alert(ex);
	}
}

/* Remove row from the table */
function removeRow(tbl, row) {
	var table = document.getElementById(tbl);
	try {
		table.deleteRow(row.rowIndex);
	} catch (ex) {
		alert(ex);
	}
}
//]]>
</script>

<?php
// This function creates a table of rule selectors which are then inserted into the form
// using a StaticText class. While not pretty this maintains compatibility with all of
// the above javascript

function build_l7table() {
	global $container, $avail_protos, $avail_structures, $avail_behaviours_altq, $avail_behaviours_limiter,
		   $avail_behaviours_action;

	$tbl = '<table id="newtbl" class="table table-hover table-condensed">'; // No stripes for this table
	$tbl .= '<thead><tr><th>Protocol</th><th>Structure</th><th>Behavior</th></tr></thead>';
	$tbl .= '<tbody>';

	if ($container) {
		foreach ($container->rsets as $l7rule) {

			$tbl .= '<tr><td>';
			$tbl .= '<select name="protocol[]" class="form-control">';

			foreach ($avail_protos as $proto):
				$tbl .= '<option value="' . $proto . '"';

				if ($proto == $l7rule->GetRProtocol())
					$tbl .= ' selected="selected"';

				$tbl .= '>' . $proto . '</option>';

			endforeach;

			$tbl .= '</select></td><td>';
			$tbl .= '<select name="structure[]" class="form-control" onchange="changeBehaviourValues(this.parentNode.parentNode);">';

			foreach ($avail_structures as $struct) {
				if ($struct == "queue") {
					if (!empty($avail_behaviours_altq)) {
						$tbl .= '<option value="' . $struct . '"';
						if ($struct == $l7rule->GetRStructure())
							$tbl .= ' selected="selected"';

						$tbl .= '>' . $struct . '</option>';
						}
					}
					else {
						if ($struct == "limiter") {
							if (!empty($avail_behaviours_limiter)) {
								$tbl .= '<option value="' . $struct . '"';
								if ($struct == $l7rule->GetRStructure())
									$tbl .= ' selected="selected"';

								$tbl .= '>' . $struct . '</option>';
							}
						}
						else {
							if ($struct == "action") {
								$tbl .= '<option value="' . $struct . '"';
								if ($struct == $l7rule->GetRStructure())
									$tbl .= ' selected="selected"';

								$tbl .= '>' . $struct . '</option>';
							}
						}
					}
				}

			$tbl .= '</select></td><td>';

			$tbl .= '<select name="behaviour[]" class="form-control">';

			if ($l7rule->GetRStructure() == "action"):
				foreach ($avail_behaviours_action as $behaviour):
					$tbl .= '<option value="' . $behaviour . '"';
					if ($behaviour == $l7rule->GetRBehaviour())
						$tbl .= ' selected="selected"';

					$tbl .= '>' . $behaviour . '</option>';

				endforeach;

				$tbl .= '</select>';

			endif;

			if ($l7rule->GetRStructure() == "queue"):
				foreach ($avail_behaviours_altq as $behaviour):

					$tbl .= '<option value="' . $behaviour	. '"';
					if ($behaviour == $l7rule->GetRBehaviour())
						$tbl .= ' selected="selected"';

					$tbl .= '>' . $behaviour . '</option>';

				endforeach;

				$tbl .= '</select>';

			endif;

			if ($l7rule->GetRStructure() == "limiter"):
				foreach ($avail_behaviours_limiter as $behaviour):
					$tbl .= '<option value="' . $behaviour . '"';
					if ($behaviour == $l7rule->GetRBehaviour())
						$tbl .= ' selected="selected"';

					$tbl .= '>' . $behaviour . '</option>';

				endforeach;

				$tbl .= '</select>';

	endif;

				$tbl .= '</td><td>';
				$tbl .= '<a type="button" class="btn  btn-default" onclick="removeRow(\'newtbl\',this.parentNode.parentNode); return false;" href="#">';
				$tbl .= gettext('Remove') . '</a>';
				$tbl .= '</td></tr>';


			} //end foreach
		} //end if

	$tbl .= '</tbody></table>';

	$tbl .= '<a id="addrow" type="button" onclick="javascript:addRow(\'newtbl\'); return false;" href="#" class="btn btn-sm btn-success">' . gettext('Add row') .
			'</a>';

	return($tbl);
}

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg, 'success');

if (is_subsystem_dirty('shaper'))
	print_info_box_np(gettext("The traffic shaper configuration has been changed")	.  ".<br />" . gettext("You must apply the changes in order for them to take effect."));

$tab_array = array();
$tab_array[] = array(gettext("By Interface"), false, "firewall_shaper.php");
$tab_array[] = array(gettext("By Queue"), false, "firewall_shaper_queues.php");
$tab_array[] = array(gettext("Limiter"), false, "firewall_shaper_vinterface.php");
$tab_array[] = array(gettext("Layer7"), true, "firewall_shaper_layer7.php");
$tab_array[] = array(gettext("Wizards"), false, "firewall_shaper_wizards.php");
display_top_tabs($tab_array);

// Create a StaticText control and populate it with the rules table
if (!$dfltmsg) {
	$section = new Form_Section('Add one (or more) rules');

	$section->addInput(new Form_StaticText(
		'Rule(s)',
		build_l7table()
	));

	$sform->add($section);
}
?>

	<div class="panel panel-default">
		<div class="panel-heading" align="center"><h2 class="panel-title">Layer 7</h2></div>
		<div class="panel-body">
			<div class="form-group">
				<div class="col-sm-2 ">
					<?=$tree?>
					<br />
					<a href="firewall_shaper_layer7.php?action=add" class="btn btn-sm btn-success">
						<?=gettext("Create new L7<br />rule group")?>
					</a>
				</div>
				<div class="col-sm-10">
<?php
if ($dfltmsg)
	print_info_box($output_form = $dn_default_shaper_msg . $default_layer7shaper_msg);
else
	print($sform);
?>
				</div>
			</div>
		</div>
	</div>


<?php
include("foot.inc");
