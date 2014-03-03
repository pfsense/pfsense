<?php
/* $Id$ */
/*
	firewall_shaper_layer7.php
	Copyright (C) 2008 Helder Pereira, André Ribeiro
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
	pfSense_BUILDER_BINARIES:	/usr/bin/killall
	pfSense_MODULE:	shaper
*/

##|+PRIV
##|*IDENT=page-firewall-trafficshaper-layer7
##|*NAME=Firewall: Traffic Shaper: Layer7 page
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper: Layer7' page.
##|*MATCH=firewall_shaper_layer7.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

// Variables protocols (dynamic) and structures (static)
$avail_protos =& generate_protocols_array();
$avail_structures = array("action","queue","limiter");

// Available behaviours
$avail_behaviours_action = array("block");
read_altq_config();
$avail_behaviours_altq = get_altq_name_list();
read_dummynet_config();
$avail_behaviours_limiter = get_dummynet_name_list();
$show_proto_form = false;

//More variables
$pgtitle = array(gettext("Firewall"),gettext("Traffic Shaper"), gettext("Layer7"));
$shortcut_section = "trafficshaper";

$output_form = "";

$default_layer7shaper_msg = "<tr><td colspan=\"4\">";
$default_layer7shaper_msg .= "<span class=\"vexpl\"><span class=\"red\"><strong>" . gettext("Note") . ":<br/>";
$default_layer7shaper_msg .= "</strong></span>" . gettext("You can add new layer7 protocol patterns by simply uploading the file") . " <a href=\"diag_patterns.php\">" . gettext("here") . ".</a></span><br/>";
$default_layer7shaper_msg .= "</td></tr>";


read_layer7_config();

if($_GET['reset'] <> "") {
	// kill all ipfw-classifyd processes
	mwexec("killall -9 ipfw-classifyd");
	exit;
}

if ($_GET) {
	if ($_GET['container'])
		$name = htmlspecialchars(trim($_GET['container']));
        if ($_GET['action'])
                $action = htmlspecialchars($_GET['action']);
}

if($_POST) {
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
			$output_form .= $container->build_form(); //constructs the graphical interface on the right side
			unset($container);
			break;
		case "show":
			$show_proto_form = true;
			if($container) {
				$output_form .= $container->build_form();
			}
			else {
				$show_proto_form = false;
				$input_errors[] = gettext("Layer7 Rules Container not found!");
			}
			break;
		default:
			echo log_error("Get default");
			$show_proto_form = false;
			$output_form .= $dn_default_shaper_msg . $default_layer7shaper_msg;
			break;
	}
}

//add a new l7rules container
else if ($_POST) {
	$show_proto_form = true;
	unset($input_errors);

	if($_POST['submit']) {
		if (isset($layer7_rules_list[$name])) {
			$l7r = $layer7_rules_list[$name];
			$_POST['divert_port'] = $l7r->GetRPort();
		} else {
			$l7r =& new layer7();
			$_POST['divert_port'] = $l7r->gen_divert_port();
		}
		for($i=0; $_POST['protocol'][$i] <> ""; $i++) {
			$_POST['l7rules'][$i]['protocol'] = $_POST['protocol'][$i];
			$_POST['l7rules'][$i]['structure'] = $_POST['structure'][$i];
			$_POST['l7rules'][$i]['behaviour'] = $_POST['behaviour'][$i];
		}
		$l7r->validate_input($_POST,&$input_errors);
		$l7r->ReadConfig($_POST['container'], $_POST);
		//Before writing the results, we need to test for repeated protocols
		$non_dupes = array();
		$dupes = array();
		for($j=0; $j<$i; $j++) {
			if(!$non_dupes[$_POST['protocol'][$j]])
				$non_dupes[$_POST['protocol'][$j]] = true;
			else
				$dupes[] = $_POST['protocol'][$j];
		}
		unset($non_dupes);
		if(sizeof($dupes) == 0 && !$input_errors) {
			$l7r->wconfig();
			if (write_config())
				mark_subsystem_dirty('shaper');

			read_layer7_config();
		}
		else {
			if(sizeof($dupes) > 0) {
				$dupe_error = gettext("Found the following repeated protocol definitions") . ": ";
				foreach($dupes as $dupe)
					$dupe_error .= "$dupe ";
				$input_errors[] .= $dupe_error;
			}
		}
		unset($dupes);
		unset($dupe_error);
		//Even if there are repeated protocols, we won't lose any previous values
		//The user will be able to solve the situation
		$output_form .= $l7r->build_form();
		//Necessary to correctly build the proto form
		$container = $layer7_rules_list[$name];
		if($input_errors)
			$container =& $l7r;
	} else if($_POST['apply']) {
		write_config();

		$retval = 0;
		$retval = filter_configure();
		$savemsg = get_std_save_message($retval);

		if(stristr($retval, "error") <> true)
			$savemsg = get_std_save_message($retval);
		else
			$savemsg = $retval;

		clear_subsystem_dirty('shaper');

		if($container) {
			$output_form .= $container->build_form();
		} else {
			$show_proto_form = false;
			$output_form .= $dn_default_shaper_msg . $default_layer7shaper_msg;
		}
	} else if ($_POST['delete']) {
		$container->delete_l7c();
		if (write_config())
			mark_subsystem_dirty('shaper');
		unset($container);

		header("Location: firewall_shaper_layer7.php");
		exit;
	}
	else {
		$show_proto_form = false;
	}
}
else {
	$show_proto_form = false;
	$output_form .= $dn_default_shaper_msg . $default_layer7shaper_msg;
}

// Builds the left tree
$tree = "<ul class=\"tree\" >";
$rowIndex = 0;
if (is_array($layer7_rules_list)) {
        foreach ($layer7_rules_list as $tmpl7) {
			$rowIndex++;
                $tree .= $tmpl7->build_tree();
        }
}
if ($rowIndex == 0)
	$tree .= "<li></li>";
$tree .= "</ul>";

$output = "<table summary=\"output form\">";
$output .= $output_form;
$closehead = false;
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
	<?php if (!empty($avail_behaviours_limiter)) {
	  foreach ($avail_behaviours_limiter as $key => $limiter) { ?>
		name = "<?= $limiter; ?>";
		index = <?= $key; ?>;
		a_behav[index] = name;
	<?php }
	} ?>
	return a_behav;
}

/* Fill the variables with available protocols, structures and behaviours */
function fillProtocol() {
	var protocol = '<select name="protocol[]" style="font-size:8pt">';
	var name;

	<?php foreach ($avail_protos as $key => $proto) { ?>
		name = "<?= $proto; ?>";
		protocol += "<option value=" + name + ">" + name + "<\/option>";
	<?php } ?>
	protocol += "<\/select>";

	return protocol;
}

function fillStructure() {
	var structure = '<select name="structure[]" style="font-size:8pt" onchange="changeBehaviourValues(this.parentNode.parentNode);">';
	var name;
	<?php foreach ($avail_structures as $key => $struct) { ?>
		name = "<?= $struct; ?>";
		if(name == "queue") {
		  if(js_behaviours_altq != "") { structure += "<option value=" + name + ">" + name + "<\/option>";}
		}
		else {
		  if(name == "limiter") {
		    if(js_behaviours_limiter != "") { structure += "<option value=" + name + ">" + name + "<\/option>";}
		  }
		  else structure += "<option value=" + name + ">" + name + "<\/option>"; //action
		}
	<?php } ?>
	structure += "<\/select>";

	return structure;
}

//Used by default to fill the values when inserting a new row.
function fillBehaviour() {
	var behaviour = '<select name="behaviour[]" style="width:80px; font-size:8pt">';
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
	var selectedRow = row.rowIndex - 2; //because row.rowIndex returns 2, not 0
	var structureSelected = document.getElementsByName("structure[]")[selectedRow].value;

	//Select the behaviours values to array a_behav
	var a_behav = new Array();
	if (structureSelected == "action") {
		a_behav = js_behaviours_action; //static
	}
	else {
		if (structureSelected == "queue") {
			a_behav = js_behaviours_altq;
		}
		else {
			a_behav = js_behaviours_limiter;
		}
	}

	//Build the html statement with the array values previously selected
	var new_behav;
	var name;
	for(i=0; i<a_behav.length; i++) {
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
  var tFielsNum =  rows_count - initial_count[table_id];
  if (rows_limit!=0 && tFielsNum >= rows_limit) return false;

  var remove = '<a onclick="removeRow(\''+table_id+'\',this.parentNode.parentNode)" href="#"><img border="0" src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" alt="x" /><\/a>';

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
function removeRow(tbl,row) {
  var table = document.getElementById(tbl);
  try {
    table.deleteRow(row.rowIndex);
  } catch (ex) {
    alert(ex);
  }
}
//]]>
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>
<div id="inputerrors"></div>
<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="firewall_shaper_layer7.php" method="post" id="iform" name="iform">

<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('shaper')): ?><p>
<?php print_info_box_np(gettext("The traffic shaper configuration has been changed")  .  ".<br/>" . gettext("You must apply the changes in order for them to take effect."));?><br/></p>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="traffic shaper layer7">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("By Interface"), false, "firewall_shaper.php");
	$tab_array[1] = array(gettext("By Queue"), false, "firewall_shaper_queues.php");
	$tab_array[2] = array(gettext("Limiter"), false, "firewall_shaper_vinterface.php");
	$tab_array[3] = array(gettext("Layer7"), true, "firewall_shaper_layer7.php");
	$tab_array[4] = array(gettext("Wizards"), false, "firewall_shaper_wizards.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">

		<?php if (count($layer7_rules_list) > 0): ?>
                        <tr class="tabcont"><td width="25%" align="left">
                        </td><td width="75%"> </td></tr>

		<?php endif; ?>
			<tr>
			<td width="25%" valign="top" align="left">
			<?php
				echo $tree;
			?>
			<br/><br/>
			<a href="firewall_shaper_layer7.php?action=add">
			<img src="./themes/<?=$g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("Create new l7 rules group"); ?>" width="17" height="17" border="0" alt="add" />  <?=gettext("Create new l7 rules group"); ?>
			</a><br/>
			</td>
			<td width="75%" valign="top" align="center">
			<div id="shaperarea" style="position:relative">
			<?php
				echo $output;
			?>

			<!-- Layer 7 rules form -->
			<?php if($show_proto_form): ?>
			<tr><td width="22%" valign="top" class="vncellreq">
                                <div id="addressnetworkport">
                                        <?=gettext("Rule(s)"); ?>
                                </div>
                        </td>

                        <td width="78%" class="vtable">
                                <table width="236" id="maintable" summary="main table">
					<tbody>

						<tr>
                                                        <td colspan="4">
                                                            <div style="font-size: 8pt; padding:5px; margin-top: 16px; margin-bottom: 16px; border:1px dashed #000066;"
                                                                id="itemhelp">
                                                                <?=gettext("Add one or more rules"); ?>
                                                            </div>
                                                        </td>
                                                </tr>

                                                <tr>
                                                        <td>
                                                            <div style="font-size: 8pt; padding:5px;"
                                                                id="onecolumn">
                                                                <?=gettext("Protocol"); ?>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div style="font-size: 8pt; padding:5px;"
                                                                id="twocolumn">
                                                                <?=gettext("Structure"); ?>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div style="font-size: 8pt; padding:5px;"
                                                                id="threecolumn">
                                                                <?=gettext("Behaviour"); ?>
                                                            </div>
                                                        </td>
                                                </tr>
                                                <!-- PHP Code to generate the existing rules -->
						<?php
						if($container) {
							foreach($container->rsets as $l7rule) {
						?>
						<tr>
							<td>
							<select name="protocol[]" class="formselect" style="font-size:8pt">
							<?php foreach($avail_protos as $proto): ?>
							<option value="<?=$proto;?>" <?php if ($proto == $l7rule->GetRProtocol()) echo "selected=\"selected\""; ?>><?=$proto;?></option>
							<?php endforeach; ?>
							</select>
						</td>
						<td>
							<select name="structure[]" class="formselect" style="font-size:8pt" onchange="changeBehaviourValues(this.parentNode.parentNode);">
							<?php foreach($avail_structures as $struct) {
							  if($struct == "queue") {
							    if(!empty($avail_behaviours_altq)) { ?>
							      <option value="<?=$struct ?>" <?php if ($struct == $l7rule->GetRStructure()) echo "selected=\"selected\""; ?>><?=$struct;?></option>
							    <?php }
							  }
							  else {
							    if($struct == "limiter") {
								if(!empty($avail_behaviours_limiter)) { ?>
								  <option value="<?=$struct ?>" <?php if ($struct == $l7rule->GetRStructure()) echo "selected=\"selected\""; ?>><?=$struct;?></option>
								<?php }
							    }
							    else {
							      if($struct == "action") { ?>
								  <option value="<?=$struct ?>" <?php if ($struct == $l7rule->GetRStructure()) echo "selected=\"selected\""; ?>><?=$struct;?></option>
							      <?php }
							    }
							  }
							} ?>
							</select>
						</td>
						<td>
							<select name="behaviour[]" class="formselect" style="width:80px; font-size:8pt">
							<?php if($l7rule->GetRStructure() == "action"): ?>
								<?php foreach($avail_behaviours_action as $behaviour): ?>
								<option value="<?=$behaviour ?>" <?php if ($behaviour == $l7rule->GetRBehaviour()) echo "selected=\"selected\""; ?>><?=$behaviour;?></option>
								<?php endforeach; ?>
								</select>
							<?php endif; ?>
							<?php if($l7rule->GetRStructure() == "queue"): ?>
								<?php foreach($avail_behaviours_altq as $behaviour): ?>
								<option value="<?=$behaviour ?>" <?php if ($behaviour == $l7rule->GetRBehaviour()) echo "selected=\"selected\""; ?>><?=$behaviour;?></option>
								<?php endforeach; ?>
								</select>
							<?php endif; ?>
							<?php if($l7rule->GetRStructure() == "limiter"): ?>
								<?php foreach($avail_behaviours_limiter as $behaviour): ?>
								<option value="<?=$behaviour ?>" <?php if ($behaviour == $l7rule->GetRBehaviour()) echo "selected=\"selected\""; ?>><?=$behaviour;?></option>
								<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td>
						<td>
							<a onclick="removeRow('maintable',this.parentNode.parentNode); return false;" href="#"><img border="0" src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" alt="x" /></a>
						</td>
						</tr>

						<?php
							} //end foreach
						} //end if
						?>
                                        </tbody>
                                </table>

                                        <a onclick="javascript:addRow('maintable'); return false;" href="#"> <img border="0"
                                                src="/themes/<?=$g['theme']; ?>/images/icons/icon_plus.gif"
                                                alt="" title="<?=gettext("add another entry"); ?>" /> </a>
                        </td>
			</tr>

                        <tr>
                        <td width="22%" valign="top">
                                &nbsp;
                        </td>

                        <td width="78%">
                                <input id="submit" name="submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />

				<a href="firewall_shaper_layer7.php">
                                <input id="cancelbutton" name="cancelbutton" type="button" class="formbtn" value="<?=gettext("Cancel"); ?>" />

				<?php if($container): ?>
						<input id="delete" type="submit" class="formbtn" name="delete" value="<?=gettext("Delete"); ?>" />
				<?php endif ?>
				</a>
                        </td>
                        </tr>
			<?php endif; ?>
			<!-- End of layer7 rules form -->
			</table>
			</div><!-- end of div:shape area -->

		      </td></tr>
                    </table>
		</div>
	  </td>
	</tr>
</table>
</form>

<?php include("fend.inc"); ?>
</body>
</html>
