<?php
/*
	firewall_shaper.php
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

##|+PRIV
##|*IDENT=page-firewall-trafficshaper
##|*NAME=Firewall: Traffic Shaper
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper' page.
##|*MATCH=firewall_shaper.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("rrd.inc");

if ($_GET['reset'] != "") {
	/* XXX: Huh, why are we killing php? */
	mwexec("killall -9 pfctl php");
	exit;
}

$pgtitle = array(gettext("Firewall"), gettext("Traffic Shaper"), gettext("Interfaces"));
$shortcut_section = "trafficshaper";

$shaperIFlist = get_configured_interface_with_descr();
read_altq_config();
/*
 * The whole logic in these code maybe can be specified.
 * If you find a better way contact me :).
 */

if ($_GET) {
	if ($_GET['queue']) {
		$qname = htmlspecialchars(trim($_GET['queue']));
	}
	if ($_GET['interface']) {
		$interface = htmlspecialchars(trim($_GET['interface']));
	}
	if ($_GET['action']) {
		$action = htmlspecialchars($_GET['action']);
	}
}

if ($_POST) {
	if ($_POST['name']) {
		$qname = htmlspecialchars(trim($_POST['name']));
	}
	if ($_POST['interface']) {
		$interface = htmlspecialchars(trim($_POST['interface']));
	}
	if ($_POST['parentqueue']) {
		$parentqueue = htmlspecialchars(trim($_POST['parentqueue']));
	}
}

if ($interface) {
	$altq = $altq_list_queues[$interface];

	if ($altq) {
		$queue =& $altq->find_queue($interface, $qname);
	} else {
		$addnewaltq = true;
	}
}

$dontshow = false;
$newqueue = false;
$dfltmsg = false;

if ($_GET) {
	switch ($action) {
		case "delete":
			if ($queue) {
				$queue->delete_queue();
				if (write_config()) {
					mark_subsystem_dirty('shaper');
				}
			}

			header("Location: firewall_shaper.php");
			exit;
			break;
		case "resetall":
			foreach ($altq_list_queues as $altq) {
				$altq->delete_all();
			}
			unset($altq_list_queues);
			$altq_list_queues = array();
			$tree = "<ul class=\"tree\" >";
			$tree .= get_interface_list_to_show();
			$tree .= "</ul>";
			unset($config['shaper']['queue']);
			unset($queue);
			unset($altq);
			$can_add = false;
			$can_enable = false;
			$dontshow = true;
			foreach ($config['filter']['rule'] as $key => $rule) {
				if (isset($rule['wizard']) && $rule['wizard'] == "yes") {
					unset($config['filter']['rule'][$key]);
				}
			}

			if (write_config()) {
				$retval = 0;
				$retval |= filter_configure();
				$savemsg = get_std_save_message($retval);

				if (stristr($retval, "error") <> true) {
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
			/* XXX: Find better way because we shouldn't know about this */
		if ($altq) {

			switch ($altq->GetScheduler()) {
				case "PRIQ":
					$q = new priq_queue();
				break;
				case "FAIRQ":
					$q = new fairq_queue();
				break;
				case "HFSC":
					$q = new hfsc_queue();
				break;
				case "CBQ":
						$q = new cbq_queue();
				break;
				default:
					/* XXX: Happens when sched==NONE?! */
					$q = new altq_root_queue();
				break;
			}
		} else if ($addnewaltq) {
			$q = new altq_root_queue();
		} else {
			$input_errors[] = gettext("Could not create new queue/discipline! Did you remember to apply any recent changes?");
		}

		if ($q) {
			$q->SetInterface($interface);
			$sform = $q->build_form();
			$sform->addGlobal(new Form_Input(
				'parentqueue',
				null,
				'hidden',
				$qname
			));

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

	if ($addnewaltq) {
		$altq =& new altq_root_queue();
		$altq->SetInterface($interface);

		switch ($altq->GetBwscale()) {
				case "Mb":
					$factor = 1000 * 1000;
					break;
				case "Kb":
					$factor = 1000;
					break;
				case "b":
					$factor = 1;
					break;
				case "Gb":
					$factor = 1000 * 1000 * 1000;
					break;
				case "%": /* We don't use it for root_XXX queues. */
				default: /* XXX assume Kb by default. */
					$factor = 1000;
					break;
			}

		$altq->SetAvailableBandwidth($altq->GetBandwidth() * $factor);
		$altq->ReadConfig($_POST);
		$altq->validate_input($_POST, $input_errors);
		if (!$input_errors) {
			unset($tmppath);
			$tmppath[] = $altq->GetInterface();
			$altq->SetLink($tmppath);
			$altq->wconfig();
			if (write_config()) {
				mark_subsystem_dirty('shaper');
			}
			$can_enable = true;
			$can_add = true;
		}

		read_altq_config();
		$sform = $altq->build_form();
	} else if ($parentqueue) { /* Add a new queue */
		$qtmp =& $altq->find_queue($interface, $parentqueue);
		if ($qtmp) {
			$tmppath =& $qtmp->GetLink();
			array_push($tmppath, $qname);
			$tmp =& $qtmp->add_queue($interface, $_POST, $tmppath, $input_errors);
			if (!$input_errors) {
				array_pop($tmppath);
				$tmp->wconfig();
				$can_enable = true;
				if ($tmp->CanHaveChildren() && $can_enable) {
					if ($tmp->GetDefault() <> "") {
						$can_add = false;
					} else {
						$can_add = true;
					}
				} else {
					$can_add = false;
				}
				if (write_config()) {
					mark_subsystem_dirty('shaper');
				}
				$can_enable = true;
				if ($altq->GetScheduler() != "PRIQ") { /* XXX */
					if ($tmp->GetDefault() <> "") {
						$can_add = false;
					} else {
						$can_add = true;
					}
				}
			}
			read_altq_config();
			$sform = $tmp->build_form();
		} else {
			$input_errors[] = gettext("Could not add new queue.");
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

		/* reset rrd queues */
		system("rm -f /var/db/rrd/*queuedrops.rrd");
		system("rm -f /var/db/rrd/*queues.rrd");
		enable_rrd_graphing();

		clear_subsystem_dirty('shaper');

		if ($queue) {
			$sform = $queue->build_form();
			$dontshow = false;
		} else {
			$sform = $default_shaper_message;
			$dontshow = true;
		}
	} else if ($queue) {
		$queue->validate_input($_POST, $input_errors);
		if (!$input_errors) {
			$queue->update_altq_queue_data($_POST);
			$queue->wconfig();
			if (write_config()) {
				mark_subsystem_dirty('shaper');
			}
			$dontshow = false;
		}
		read_altq_config();
		$sform = $queue->build_form();
	} else	{
		$dfltmsg = true;
		$dontshow = true;
	}
	mwexec("killall qstats");
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
	if ($queue->CanHaveChildren() && $can_enable) {
		if ($altq->GetQname() <> $queue->GetQname() && $queue->GetDefault() <> "") {
			$can_add = false;
		} else {
			$can_add = true;
		}
	} else {
		$can_add = false;
	}
}

//$pgtitle = "Firewall: Shaper: By Interface View";
include("head.inc");

$tree = '<ul class="tree" >';
if (is_array($altq_list_queues)) {
	foreach ($altq_list_queues as $tmpaltq) {
		$tree .= $tmpaltq->build_tree();
	}
	$tree .= get_interface_list_to_show();
}

$tree .= "</ul>";

if ($queue) {
	print($queue->build_javascript());
}

print($newjavascript);

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
$tab_array[] = array(gettext("By Interface"), true, "firewall_shaper.php");
$tab_array[] = array(gettext("By Queue"), false, "firewall_shaper_queues.php");
$tab_array[] = array(gettext("Limiter"), false, "firewall_shaper_vinterface.php");
$tab_array[] = array(gettext("Wizards"), false, "firewall_shaper_wizards.php");
display_top_tabs($tab_array);

?>
<script type="text/javascript" src="./tree/tree.js"></script>

<div class="table-responsive">
	<table class="table">
		<tbody>
			<tr class="tabcont">
				<td class="col-md-1">
<?php
// Display the shaper tree
print($tree);

if (count($altq_list_queues) > 0) {
?>
					<a href="firewall_shaper.php?action=resetall" class="btn btn-sm btn-danger">
						<?=gettext('Remove Shaper')?>
					</a>
<?php
}
?>
				</td>
				<td>
<?php

if (!$dfltmsg && $sform)  {
	// Add global buttons
	if (!$dontshow || $newqueue) {
		if ($can_add || $addnewaltq) {
			if ($queue) {
				$url = 'firewall_shaper.php?interface='. $interface . '&queue=' . $queue->GetQname() . '&action=add';
			} else {
				$url = 'firewall_shaper.php?interface='. $interface . '&action=add';
			}

			$sform->addGlobal(new Form_Button(
				'add',
				'Add new Queue',
				$url
			))->removeClass('btn-default')->addClass('btn-success');

		}

		if ($queue) {
			$url = 'firewall_shaper.php?interface='. $interface . '&queue=' . $queue->GetQname() . '&action=delete';
		} else {
			$url = 'firewall_shaper.php?interface='. $interface . '&action=delete';
		}

		$sform->addGlobal(new Form_Button(
			'delete',
			$queue ? 'Delete this queue':'Disable shaper on interface',
			$url
		))->removeClass('btn-default')->addClass('btn-danger');

	}

	print($sform);
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
		<?=print_info_box($default_shaper_msg, 'info')?>
	</div>
</div>
<?php
}
include("foot.inc");
