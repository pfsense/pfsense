<?php
/*
 * firewall_shaper_queues.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-firewall-trafficshaper-queues
##|*NAME=Firewall: Traffic Shaper: Queues
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper: Queues' page.
##|*MATCH=firewall_shaper_queues.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("rrd.inc");

if ($_GET['reset'] != "") {
	mwexec("/usr/bin/killall -9 pfctl");
	exit;
}

$qname = gettext("No Queue Configured/Selected");

$shaperIFlist = get_configured_interface_with_descr();
read_altq_config();
$qlist =& get_unique_queue_list();

if (!is_array($qlist)) {
	$qlist = array();
}

$tree = "<ul class=\"tree\" >";
foreach ($qlist as $queue => $qkey) {
	$tree .= "<li><a href=\"firewall_shaper_queues.php?queue={$queue}&amp;action=show\" >";
	if (isset($shaperIFlist[$queue])) {
		$tree .= $shaperIFlist[$queue] . "</a></li>";
	} else {
		$tree .= $queue . "</a></li>";
	}
}
$tree .= "</ul>";
$output = "";

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

	switch ($action) {
		case "delete":
			$altq =& $altq_list_queues[$interface];
			$qtmp =& $altq->find_queue("", $qname);
			if ($qtmp) {
				$qtmp->delete_queue();
				if (write_config("Traffic Shaper: Queue deleted")) {
					mark_subsystem_dirty('shaper');
				}
			}
			header("Location: firewall_shaper_queues.php");
			exit;
			break;
		case "add":
			/*
			 * XXX: WARNING: This returns the first it finds.
			 * Maybe the user expects something else?!
			 */
			foreach ($altq_list_queues as $altq) {
				$qtmp =& $altq->find_queue("", $qname);

				if ($qtmp) {
					$copycfg = array();
					$qtmp->copy_queue($interface, $copycfg);
					$aq =& $altq_list_queues[$interface];

					if ($qname == $qtmp->GetInterface()) {
						$config['shaper']['queue'][] = $copycfg;
					} else if ($aq) {
						$tmp1 =& $qtmp->find_parentqueue($interface, $qname);
						if ($tmp1) {
							$tmp =& $aq->find_queue($interface, $tmp1->GetQname());
						}

						if ($tmp) {
							$link =& get_reference_to_me_in_config($tmp->GetLink());
						} else {
							$link =& get_reference_to_me_in_config($aq->GetLink());
						}

						$link['queue'][] = $copycfg;
					} else {
						$newroot = array();
						$newroot['name'] = $interface;
						$newroot['interface'] = $interface;
						$newroot['scheduler'] = $altq->GetScheduler();
						$newroot['queue'] = array();
						$newroot['queue'][] = $copycfg;
						$config['shaper']['queue'][] = $newroot;
					}

					if (write_config("Traffic Shaper: Added new queue")) {
						mark_subsystem_dirty('shaper');
					}

					break;
					}
				}

			header("Location: firewall_shaper_queues.php?queue=".$qname."&action=show");
			exit;
		break;
		case "show":
			foreach ($config['interfaces'] as $if => $ifdesc) {
				$altq = $altq_list_queues[$if];

				if ($altq) {
					$qtmp =& $altq->find_queue("", $qname);

					if ($qtmp) {
						$output .= $qtmp->build_shortform();
					} else {
						$output .= build_iface_without_this_queue($if, $qname);
					}
				} else {
					if (!is_altq_capable($ifdesc['if'])) {
						continue;
					}

					if (!isset($ifdesc['enable']) && $if != "lan" && $if != "wan") {
						continue;
					}

					$output .= build_iface_without_this_queue($if, $qname);
				}
			}
		break;
	}
}

if ($_POST['apply']) {
	write_config("Traffic Shaper: Changes applied");

	$retval = 0;
	/* Setup pf rules since the user may have changed the optimization value */
	$retval |= filter_configure();

	/* reset rrd queues */
	system("rm -f /var/db/rrd/*queuedrops.rrd");
	system("rm -f /var/db/rrd/*queues.rrd");
	enable_rrd_graphing();

	clear_subsystem_dirty('shaper');
}

$pgtitle = array(gettext("Firewall"), gettext("Traffic Shaper"), gettext("By Queue"));
$pglinks = array("", "firewall_shaper.php", "@self");
$shortcut_section = "trafficshaper";

include("head.inc");
?>

<script type="text/javascript" src="./vendor/tree/tree.js"></script>

<?php
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('shaper')) {
	print_apply_box(gettext("The traffic shaper configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("By Interface"), false, "firewall_shaper.php");
$tab_array[] = array(gettext("By Queue"), true, "firewall_shaper_queues.php");
$tab_array[] = array(gettext("Limiters"), false, "firewall_shaper_vinterface.php");
$tab_array[] = array(gettext("Wizards"), false, "firewall_shaper_wizards.php");
display_top_tabs($tab_array);

?>

<form action="firewall_shaper_queues.php" method="post" name="iform" id="iform">
	<div class="panel panel-default">
		<div class="panel-heading text-center"><h2 class="panel-title"><?=$qname?></h2></div>
		<div class="panel-body">
			<div class="form-group">
				<div class="col-sm-2 ">
					<?=$tree?>
				</div>
				<div class="col-sm-10">
					<?=$output?>
				</div>
			</div>
		</div>
	</div>
</form>

<?php if (empty(get_interface_list_to_show()) && (!is_array($altq_list_queues) || (count($altq_list_queues) == 0))): ?>
<div>
	<div class="infoblock blockopen">
		<?php print_info_box(gettext("This firewall does not have any interfaces assigned that are capable of using ALTQ traffic shaping."), 'danger', false); ?>
	</div>
</div>
<?php endif; ?>

<?php
include("foot.inc");
?>
