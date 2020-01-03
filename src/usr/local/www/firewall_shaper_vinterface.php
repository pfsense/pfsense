<?php
/*
 * firewall_shaper_vinterface.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-firewall-trafficshaper-limiter
##|*NAME=Firewall: Traffic Shaper: Limiters
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper: Limiters' page.
##|*MATCH=firewall_shaper_vinterface.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if ($_GET['reset'] != "") {
	mwexec("/usr/bin/killall -9 pfctl");
	exit;
}

$pgtitle = array(gettext("Firewall"), gettext("Traffic Shaper"), gettext("Limiters"));
$pglinks = array("", "firewall_shaper.php", "@self");
$shortcut_section = "trafficshaper-limiters";
$dfltmsg = false;

read_dummynet_config();
/*
 * The whole logic in these code maybe can be specified.
 * If you find a better way contact me :).
 */

if ($_GET) {
	if ($_GET['queue']) {
		$qname = htmlspecialchars(trim($_GET['queue']));
	}
	if ($_GET['pipe']) {
		$pipe = htmlspecialchars(trim($_GET['pipe']));
	}
	if ($_GET['action']) {
		$action = htmlspecialchars($_GET['action']);
	}
}

if ($_POST) {
	if ($_POST['name']) {
		$qname = htmlspecialchars(trim($_POST['name']));
	} else if ($_POST['newname']) {
		$qname = htmlspecialchars(trim($_POST['newname']));
	}
	if ($_POST['pipe']) {
		$pipe = htmlspecialchars(trim($_POST['pipe']));
	} else {
		$pipe = htmlspecialchars(trim($qname));
	}
	if ($_POST['parentqueue']) {
		$parentqueue = htmlspecialchars(trim($_POST['parentqueue']));
	}
}

if ($pipe) {
	$dnpipe = $dummynet_pipe_list[$pipe];
	if ($dnpipe) {
		$queue =& $dnpipe->find_queue($pipe, $qname);
	} else {
		$addnewpipe = true;
	}
}

$dontshow = false;
$newqueue = false;
$output_form = "";

if ($_GET) {
	switch ($action) {
		case "delete":
			if ($queue) {
				if (is_array($config['filter']['rule'])) {
					foreach ($config['filter']['rule'] as $rule) {
						if ($rule['dnpipe'] == $queue->GetQname() || $rule['pdnpipe'] == $queue->GetQname()) {
							$input_errors[] = gettext("This pipe/queue is referenced in filter rules, please remove references from there before deleting.");
						}
					}
				}
				if (!$input_errors) {
					$queue->delete_queue();
					if (write_config()) {
						mark_subsystem_dirty('shaper');
					}
					header("Location: firewall_shaper_vinterface.php");
					exit;
				}
				$sform= $queue->build_form();
			} else {
				$input_errors[] = sprintf(gettext("No queue with name %s was found!"), $qname);
				$output_form .= $dn_default_shaper_msg;
				$dontshow = true;
			}
			break;
		case "resetall":
			foreach ($dummynet_pipe_list as $dn) {
				$dn->delete_queue();
			}
			unset($dummynet_pipe_list);
			$dummynet_pipe_list = array();
			unset($config['dnshaper']['queue']);
			unset($queue);
			unset($pipe);
			$can_add = false;
			$can_enable = false;
			$dontshow = true;
			foreach ($config['filter']['rule'] as $key => $rule) {
				if (isset($rule['dnpipe'])) {
					unset($config['filter']['rule'][$key]['dnpipe']);
				}
				if (isset($rule['pdnpipe'])) {
					unset($config['filter']['rule'][$key]['pdnpipe']);
				}
			}
			if (write_config()) {
				$changes_applied = true;
				$retval = 0;
				$retval |= filter_configure();
			} else {
				$no_write_config_msg = gettext("Unable to write config.xml (Access Denied?).");
			}

			$dfltmsg = true;

		break;
	case "add":
		if ($dnpipe) {
			$q = new dnqueue_class();
			$q->SetPipe($pipe);
		} else if ($addnewpipe) {
			$q = new dnpipe_class();
			$q->SetQname($pipe);
		} else {
			$input_errors[] = gettext("Could not create new queue/discipline!");
		}

		if ($q) {
			$sform = $q->build_form();
			if ($dnpipe) {
				$sform->addGlobal(new Form_Input(
					'parentqueue',
					null,
					'hidden',
					$pipe
				));
			}
			$newjavascript = $q->build_javascript();
			unset($q);
			$newqueue = true;
		}

		break;
	case "show":
		if ($queue) {
			$sform = $queue->build_form();
		} else {
			$input_errors[] = gettext("Queue not found!");
		}
		break;
	case "enable":
		if ($queue) {
			$queue->SetEnabled("on");
			$sform = $queue->build_form();
			$queue->wconfig();
			if (write_config()) {
				mark_subsystem_dirty('shaper');
			}
		} else {
			$input_errors[] = gettext("Queue not found!");
		}
		break;
	case "disable":
		if ($queue) {
			$queue->SetEnabled("");
			$sform = $queue->build_form();
			$queue->wconfig();
			if (write_config()) {
				mark_subsystem_dirty('shaper');
			}
		} else {
			$input_errors[] = gettext("Queue not found!");
		}
		break;
	default:
		$dfltmsg = true;
		$dontshow = true;
		break;
	}
}

if ($_POST) {
	unset($input_errors);

	if ($addnewpipe) {
		if (!empty($dummynet_pipe_list[$qname])) {
			$input_errors[] = gettext("A child queue cannot be named the same as a parent limiter.");
		} else {
			$__tmp_dnpipe = new dnpipe_class(); $dnpipe =& $__tmp_dnpipe;

			$dnpipe->ReadConfig($_POST);
			$dnpipe->validate_input($_POST, $input_errors);
			if (!$input_errors) {
				$number = dnpipe_find_nextnumber();
				$dnpipe->SetNumber($number);
				unset($tmppath);
				$tmppath[] = $dnpipe->GetQname();
				$dnpipe->SetLink($tmppath);
				$dnpipe->wconfig();
				if (write_config()) {
					mark_subsystem_dirty('shaper');
				}
				$can_enable = true;
				$can_add = true;
			}

			read_dummynet_config();
			$sform = $dnpipe->build_form();
			$newjavascript = $dnpipe->build_javascript();
		}
	} else if ($parentqueue) { /* Add a new queue */
		if (!empty($dummynet_pipe_list[$qname])) {
			$input_errors[] = gettext("A child queue cannot be named the same as a parent limiter.");
		} else if ($dnpipe) {
			$tmppath =& $dnpipe->GetLink();
			array_push($tmppath, $qname);
			$tmp =& $dnpipe->add_queue($pipe, $_POST, $tmppath, $input_errors);
			if (!$input_errors) {
				array_pop($tmppath);
				$tmp->wconfig();
				if (write_config()) {
					$can_enable = true;
					$can_add = false;
					mark_subsystem_dirty('shaper');
				}
			}
			read_dummynet_config();
			$sform = $tmp->build_form();
		} else {
			$input_errors[] = gettext("Could not add new queue.");
		}
	} else if ($_POST['apply']) {
		write_config();

		$changes_applied = true;
		$retval = 0;
		$retval |= filter_configure();

		/* XXX: TODO Make dummynet pretty graphs */
		//	enable_rrd_graphing();

		clear_subsystem_dirty('shaper');

		if ($queue) {
			$sform = $queue->build_form();
			$dontshow = false;
		} else {
			$output_form .= $dn_default_shaper_message;
			$dontshow = true;
		}

	} else if ($queue) {
		$queue->validate_input($_POST, $input_errors);
		if (!$input_errors) {
			$queue->update_dn_data($_POST);
			$queue->wconfig();
			if (write_config()) {
				mark_subsystem_dirty('shaper');
			}
			$dontshow = false;
		}
		read_dummynet_config();
		$sform = $queue->build_form();
	} else	{
		$dfltmsg = true;
		$dontshow = true;
	}
}

if (!$_POST && !$_GET) {
	$dfltmsg = true;
	$dontshow = true;
}

if ($queue) {
	if ($queue->GetEnabled()) {
		$can_enable = true;
	} else {
		$can_enable = false;
	}
	if ($queue->CanHaveChildren()) {
		$can_add = true;
	} else {
		$can_add = false;
	}
}

$tree = "<ul class=\"tree\" >";
if (is_array($dummynet_pipe_list)) {
	foreach ($dummynet_pipe_list as $tmpdn) {
		$tree .= $tmpdn->build_tree();
	}
}
$tree .= "</ul>";

$output = "<table summary=\"output form\">";
$output .= $output_form;
include("head.inc");
?>
<script type="text/javascript" src="./vendor/tree/tree.js"></script>

<script type="text/javascript">
//<![CDATA[
function show_source_port_range() {
	document.getElementById("sprtable").style.display = '';
	document.getElementById("sprtable1").style.display = '';
	document.getElementById("sprtable2").style.display = '';
	document.getElementById("sprtable5").style.display = '';
	document.getElementById("sprtable4").style.display = 'none';
	document.getElementById("showadvancedboxspr").innerHTML='';
}
//]]>
</script>

<?php
if ($queue) {
	echo $queue->build_javascript();
} else {
	echo $newjavascript;
}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($no_write_config_msg) {
	print_info_box($no_write_config_msg, 'danger');
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('shaper')) {
	print_apply_box(gettext("The traffic shaper configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("By Interface"), false, "firewall_shaper.php");
$tab_array[] = array(gettext("By Queue"), false, "firewall_shaper_queues.php");
$tab_array[] = array(gettext("Limiters"), true, "firewall_shaper_vinterface.php");
$tab_array[] = array(gettext("Wizards"), false, "firewall_shaper_wizards.php");
display_top_tabs($tab_array);
?>
<div class="table-responsive">
	<table class="table">
		<tbody>
			<tr class="tabcont">
				<td class="col-md-1">
					<?=$tree?>
					<a href="firewall_shaper_vinterface.php?pipe=new&amp;action=add" class="btn btn-sm btn-success">
						<i class="fa fa-plus icon-embed-btn"></i>
						<?=gettext('New Limiter')?>
					</a>
				</td>
				<td>
<?php

if (!$dfltmsg) {
	// Add global buttons
	if (!$dontshow || $newqueue) {
		if ($can_add && ($action != "add")) {
			if ($queue) {
				$url = 'firewall_shaper_vinterface.php?pipe=' . $pipe . '&queue=' . $queue->GetQname() . '&action=add';
			} else {
				$url = 'firewall_shaper_vinterface.php?pipe='. $pipe . '&action=add';
			}

			$sform->addGlobal(new Form_Button(
				'add',
				'Add new Queue',
				$url,
				'fa-plus'
			))->addClass('btn-success');
		}

		if ($action != "add") {
			if ($queue) {
				$url = 'firewall_shaper_vinterface.php?pipe='. $pipe . '&queue=' . $queue->GetQname() . '&action=delete';
			} else {
				$url = 'firewall_shaper_vinterface.php?pipe='. $pipe . '&action=delete';
			}

			if ($sform) {
				$sform->addGlobal(new Form_Button(
					'delete',
					($queue && ($qname != $pipe)) ? 'Delete this queue':'Delete Limiter',
					$url,
					'fa-trash'
				))->addClass('btn-danger');
			}
		}
	}

	// Print the form
	if ($sform) {
		$sform->setAction("firewall_shaper_vinterface.php");
		print($sform);
	}

}
?>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<?php
if ($dfltmsg) {
?>
<div>
	<div class="infoblock">
		<?php print_info_box($dn_default_shaper_msg, 'info', false); ?>
	</div>
</div>
<?php
}
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

    // Disables the specified input element
    function disableInput(id, disable) {
        $('#' + id).prop("disabled", disable);
    }

	function change_masks() {
		disableInput('maskbits', ($('#mask').val() == 'none'));
		disableInput('maskbitsv6', ($('#mask').val() == 'none'));
	}

	// ---------- On initial page load ------------------------------------------------------------

	change_masks();

	// ---------- Click checkbox handlers ---------------------------------------------------------

    $('#mask').on('change', function() {
        change_masks();
    });
});
//]]>
</script>


<?php
include("foot.inc");
