<?php
/*
	firewall_shaper_queues.php
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
##|*IDENT=page-firewall-trafficshaper-queues
##|*NAME=Firewall: Traffic Shaper: Queues
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper: Queues' page.
##|*MATCH=firewall_shaper_queues.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("rrd.inc");

if ($_GET['reset'] != "") {
	mwexec("killall -9 pfctl");
	exit;
}

$qname = gettext("No queue configured/selected");

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
				if (write_config()) {
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

					if (write_config()) {
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
	write_config();

	$retval = 0;
	/* Setup pf rules since the user may have changed the optimization value */
	$retval = filter_configure();
	$savemsg = get_std_save_message($retval);
	if (stristr($retval, "error") <> true) {
		$savemsg = get_std_save_message($retval);
		$class = 'alert-success';
	} else {
		$savemsg = $retval;
		$class = 'alert-danger';
	}

	/* reset rrd queues */
	system("rm -f /var/db/rrd/*queuedrops.rrd");
	system("rm -f /var/db/rrd/*queues.rrd");
	enable_rrd_graphing();

	clear_subsystem_dirty('shaper');
}

$pgtitle = array(gettext("Firewall"), gettext("Traffic Shaper"), gettext("Queues"));
$shortcut_section = "trafficshaper";

include("head.inc");
?>

<script type="text/javascript" src="./tree/tree.js"></script>

<?php
if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, $class);
}

if (is_subsystem_dirty('shaper')) {
	print_info_box_np(gettext("The traffic shaper configuration has been changed. You must apply the changes in order for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("By Interface"), false, "firewall_shaper.php");
$tab_array[] = array(gettext("By Queue"), true, "firewall_shaper_queues.php");
$tab_array[] = array(gettext("Limiter"), false, "firewall_shaper_vinterface.php");
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

<?php
include("foot.inc");
?>
