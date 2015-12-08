<?php
/*
	firewall_shaper_vinterface.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-firewall-trafficshaper-limiter
##|*NAME=Firewall: Traffic Shaper: Limiter
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper: Limiter' page.
##|*MATCH=firewall_shaper_vinterface.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if ($_GET['reset'] != "") {
	mwexec("/usr/bin/killall -9 pfctl");
	exit;
}

$pgtitle = array(gettext("Firewall"), gettext("Traffic Shaper"), gettext("Limiter"));
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
				$output_form .= $queue->build_form();
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
				$retval = 0;
				$retval = filter_configure();
				$savemsg = get_std_save_message($retval);

			if (stristr($retval, "error") != true) {
				$savemsg = get_std_save_message($retval);
			} else {
				$savemsg = $retval;
			}

		} else {
			$savemsg = gettext("Unable to write config.xml (Access Denied?)");
		}

		$dfltmsg = true;

		break;
	case "add":
		if ($dnpipe) {
			$q = new dnqueue_class();
			$q->SetPipe($pipe);
			$output_form .= "<input type=\"hidden\" name=\"parentqueue\" id=\"parentqueue\"";
			$output_form .= " value=\"".$pipe."\" />";
		} else if ($addnewpipe) {
			$q = new dnpipe_class();
			$q->SetQname($pipe);
		} else
			$input_errors[] = gettext("Could not create new queue/discipline!");

		if ($q) {
			$sform = $q->build_form();
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
			if (write_config())
				mark_subsystem_dirty('shaper');
		} else {
			$input_errors[] = gettext("Queue not found!");
		}
		break;
	case "disable":
		if ($queue) {
			$queue->SetEnabled("");
			$sform = $queue->build_form();
			$queue->wconfig();
			if (write_config())
				mark_subsystem_dirty('shaper');
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
			$input_errors[] = gettext("You cannot name a child queue with the same name as a parent limiter");
		} else {
			$dnpipe =& new dnpipe_class();

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
			$input_errors[] = gettext("You cannot name a child queue with the same name as a parent limiter");
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

		$retval = 0;
		$retval = filter_configure();
		$savemsg = get_std_save_message($retval);

		if (stristr($retval, "error") != true) {
			$savemsg = get_std_save_message($retval);
		} else {
			$savemsg = $retval;
		}

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
	} else
		$can_add = false;
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
$closehead = false;
include("head.inc");
?>
<link rel="stylesheet" type="text/css" media="all" href="./tree/tree.css" property="stylesheet" />
<script type="text/javascript" src="./tree/tree.js"></script>

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

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('shaper')) {
	print_info_box_np(gettext("The traffic shaper configuration has been changed. You must apply the changes in order for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("By Interface"), false, "firewall_shaper.php");
$tab_array[] = array(gettext("By Queue"), false, "firewall_shaper_queues.php");
$tab_array[] = array(gettext("Limiter"), true, "firewall_shaper_vinterface.php");
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
						<?=gettext('New Limiter')?>
					</a>
				</td>
				<td>
<?php

if ($dfltmsg) {
	print_info_box($dn_default_shaper_msg);
} else {
	// Add global buttons
	if (!$dontshow || $newqueue) {
		if ($can_add || $addnewaltq) {
			if ($queue) {
				$url = 'href="firewall_shaper_vinterface.php?pipe=' . $pipe . '&queue=' . $queue->GetQname() . '&action=add';
			} else {
				$url = 'firewall_shaper.php?pipe='. $pipe . '&action=add';
			}

			$sform->addGlobal(new Form_Button(
				'add',
				'Add new Queue',
				$url
			))->removeClass('btn-default')->addClass('btn-success');
		}

		if ($queue) {
			$url = 'firewall_shaper_vinterface.php?pipe='. $pipe . '&queue=' . $queue->GetQname() . '&action=delete';
		} else {
			$url = 'firewall_shaper_vinterface.php?pipe='. $pipe . '&action=delete';
		}

		$sform->addGlobal(new Form_Button(
			'delete',
			$queue ? 'Delete this queue':'Delete',
			$url
		))->removeClass('btn-default')->addClass('btn-danger');
	}

	// Print the form
	print($sform);

}
?>
				</td>
			</tr>
		</tbody>
	</table>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

    // Disables the specified input element
    function disableInput(id, disable) {
        $('#' + id).prop("disabled", disable);
    }

	function change_masks() {
		disableInput('maskbits', ($('#scheduler').val() == 'none'));
		disableInput('maskbitsv6', ($('#scheduler').val() == 'none'));
	}

	// ---------- On initial page load ------------------------------------------------------------

	change_masks();

	// ---------- Click checkbox handlers ---------------------------------------------------------

    $('#scheduler').on('change', function() {
        change_masks();
    });
});
//]]>
</script>


<?php
include("foot.inc");
