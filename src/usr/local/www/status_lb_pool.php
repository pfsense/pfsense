<?php
/*
 * status_lb_pool.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Seth Mos <seth.mos@dds.nl>
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
##|*IDENT=page-status-loadbalancer-pool
##|*NAME=Status: Load Balancer: Pool
##|*DESCR=Allow access to the 'Status: Load Balancer: Pool' page.
##|*MATCH=status_lb_pool.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("vslb.inc");

define('COLOR', true);

init_config_arr(array('load_balancer', 'lbpool'));
$a_pool = &$config['load_balancer']['lbpool'];

$lb_logfile = "{$g['varlog_path']}/relayd.log";

$nentries = $config['syslog']['nentries'];

if (!$nentries) {
	$nentries = 50;
}

$now = time();
$year = date("Y");

$pgtitle = array(gettext("Status"), gettext("Load Balancer"), gettext("Pools"));
$pglinks = array("", "@self", "@self");
$shortcut_section = "relayd";

include("head.inc");

$relay_hosts = get_lb_summary();

if ($_POST) {
	if ($_POST['apply']) {
		$retval = 0;
		$retval |= filter_configure();
		$retval |= relayd_configure();
		clear_subsystem_dirty('loadbalancer');
	} else {
		/* Keep a list of servers we find in POST variables */
		$newservers = array();

		foreach ($_POST as $name => $value) {
			/* Look through the POST vars to find the pool data */
			if (strpos($name, '|') !== false) {
				list($poolname, $ip) = explode("|", $name);
				$ip = str_replace('_', '.', $ip);
				$newservers[$poolname][] = $ip;
			} elseif (is_ipaddr($value)) {
				$newservers[$name][] = $value;
			}
		}

		foreach ($a_pool as & $pool) {
			if (is_array($pool['servers']) && is_array($pool['serversdisabled'])) {
				$oldservers = array_merge($pool['servers'], $pool['serversdisabled']);
			} elseif (is_array($pool['servers'])) {
				$oldservers = $pool['servers'];
			} elseif (is_array($pool['serversdisabled'])) {
				$oldservers = $pool['serversdisabled'];
			} else {
				$oldservers = array();
			}
			if (is_array($newservers[$pool['name']])) {
				$pool['servers'] = $newservers[$pool['name']];
				$pool['serversdisabled'] = array_diff($oldservers, $newservers[$pool['name']]);
			}
		}

		mark_subsystem_dirty('loadbalancer');
		write_config(gettext("Updated load balancer pools via status screen."));
	}
}

if (is_subsystem_dirty('loadbalancer')) {
	print_apply_box(gettext("The load balancer configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

/* active tabs */
$tab_array = array();
$tab_array[] = array(gettext("Pools"), true, "status_lb_pool.php");
$tab_array[] = array(gettext("Virtual Servers"), false, "status_lb_vs.php");
display_top_tabs($tab_array);

$rowsprinted = 0;
?>

<form action="status_lb_pool.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Load Balancer Pools");?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Name")?></th>
						<th><?=gettext("Mode")?></th>
						<th><?=gettext("Servers")?></th>
						<th><?=gettext("Monitor")?></th>
						<th><?=gettext("Description")?></th>
					</tr>
				</thead>
				<tbody>
<?php
foreach ($a_pool as $pool):
	$rowsprinted++;
?>
					<tr>
						<td>
							<?=$pool['name']?>
						</td>
						<td>
<?php
	switch ($pool['mode']) {
		case "loadbalance":
			echo gettext("Load balancing");
			break;
		case "failover":
			echo gettext("Manual failover");
			break;
		default:
			echo gettext("(default)");
	}
?>
						</td>
						<td>
							<table> <!-- Mini-table allows manipulation of cell colors-->
<?php
	$pool_hosts=array();

	foreach ((array) $pool['servers'] as $server) {
		$svr['ip']['addr']=$server;
		$svr['ip']['state']=$relay_hosts[$pool['name'].":".$pool['port']][$server]['state'];
		$svr['ip']['avail']=$relay_hosts[$pool['name'].":".$pool['port']][$server]['avail'];
		$pool_hosts[]=$svr;
	}

	foreach ((array) $pool['serversdisabled'] as $server) {
		$svr['ip']['addr']="$server";
		$svr['ip']['state']='disabled';
		$svr['ip']['avail']='disabled';
		$pool_hosts[]=$svr;
	}

	asort($pool_hosts);

	foreach ((array) $pool_hosts as $server) {
		if ($server['ip']['addr'] != "") {
			switch ($server['ip']['state']) {
				case 'up':
					$bgcolor = "bg-success";
					$checked = "checked";
					break;
				case 'disabled':
					$bgcolor = "";
					$checked = "";
					break;
				default:
					$bgcolor = "bg-danger";
					$checked = "checked";
			}
?>
								<tr>
<?php
			switch ($pool['mode']) {
				case 'loadbalance':
					print("<td class=\"{$bgcolor}\"><input type=\"checkbox\" name=\"{$pool['name']}|" . str_replace('.', '_', $server['ip']['addr']) . "\" {$checked} /></td>\n");
					break;
				case 'failover':
					print("<td class=\"{$bgcolor}\"><input type=\"radio\" name=\"{$pool['name']}\" value=\"{$server['ip']['addr']}\" {$checked} /></td>\n");
					break;
			}

			print("<td class=\"{$bgcolor}\">&nbsp;{$server['ip']['addr']}:{$pool['port']}&nbsp;</td><td class=\"{$bgcolor}\">&nbsp;");

			if ($server['ip']['avail']) {
				print(" ({$server['ip']['avail']}) ");
			}
?>
									</td>
								</tr>
<?php
			}
		}
?>
							</table>
						</td>
						<td >
							<?=$pool['monitor']; ?>
						</td>
						<td>
							<?=htmlspecialchars($pool['descr'])?>
						</td>
					</tr>
<?php
endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>

<?php
if ($rowsprinted > 0) {
?>
                        <nav class="action-buttons">
                                <button name="Submit" type="submit" class="btn btn-primary btn-sm" value="<?= gettext("Save"); ?>" >
                                        <i class="fa fa-save icon-embed-btn"></i>
                                        <?=gettext("Save")?>
                                </button>

                                <button name="Reset" type="reset"  class="btn btn-danger btn-sm" value="<?= gettext("Reset"); ?>">
                                        <i class="fa fa-undo icon-embed-btn"></i>
                                        <?=gettext("Reset")?>
                                </button>
                        </nav>
<?php
}
?>

</form>

<?php
include("foot.inc");
