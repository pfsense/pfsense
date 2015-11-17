<?php
/*
	status_lb_pool.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2010 Seth Mos <seth.mos@dds.nl>
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */
/*
	pfSense_MODULE: routing
*/

##|+PRIV
##|*IDENT=page-status-loadbalancer-pool
##|*NAME=Status: Load Balancer: Pool page
##|*DESCR=Allow access to the 'Status: Load Balancer: Pool' page.
##|*MATCH=status_lb_pool.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("vslb.inc");

define('COLOR', true);
define('LIGHTGREEN', '#90EE90');
define('LIGHTCORAL', '#F08080');
define('KHAKI',		 '#F0E68C');
define('LIGHTGRAY',	 '#D3D3D3');
define('WHITE',		 '#FFFFFF');

if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}

$a_pool = &$config['load_balancer']['lbpool'];

$lb_logfile = "{$g['varlog_path']}/relayd.log";

$nentries = $config['syslog']['nentries'];

if (!$nentries)
	$nentries = 50;

$now = time();
$year = date("Y");

$pgtitle = array(gettext("Status"), gettext("Load Balancer"), gettext("Pool"));
$shortcut_section = "relayd";

include("head.inc");

$relay_hosts = get_lb_summary();

if ($_POST) {
	if ($_POST['apply']) {
		$retval = 0;
		$retval |= filter_configure();
		$retval |= relayd_configure();
		$savemsg = get_std_save_message($retval);
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
		write_config("Updated load balancer pools via status screen.");
	}
}

if (is_subsystem_dirty('loadbalancer'))
	print_info_box_np('The load balancer configuration has been changed You must apply the changes in order for them to take effect.');

/* active tabs */
$tab_array = array();
$tab_array[] = array(gettext("Pools"), true, "status_lb_pool.php");
$tab_array[] = array(gettext("Virtual Servers"), false, "status_lb_vs.php");
display_top_tabs($tab_array);

$rowsprinted = 0;
?>

<form action="status_lb_pool.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title">Load Balancer Pools</h2></div>
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
	switch($pool['mode']) {
		case "loadbalance":
			echo "Load balancing";
			break;
		case "failover":
			echo "Manual failover";
			break;
		default:
			echo "(default)";
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
		if($server['ip']['addr']!="") {
			switch ($server['ip']['state']) {
				case 'up':
					$bgcolor = LIGHTGREEN;	// lightgreen
					$checked = "checked=\"checked\"";
					break;
				case 'disabled':
					$bgcolor = WHITE;
					$checked = "";
					break;
				default:
					$bgcolor = LIGHTCORAL;	// lightcoral
					$checked = "checked=\"checked\"";
			}
?>
								<tr>
<?php
			switch ($pool['mode']) {
				case 'loadbalance':
					print("<td><input type=\"checkbox\" name=\"{$pool['name']}|" . str_replace('.', '_', $server['ip']['addr']) . "\" {$checked} /></td>\n");
					break;
				case 'failover':
					print("<td><input type=\"radio\" name=\"{$pool['name']}\" value=\"{$server['ip']['addr']}\" {$checked} /></td>\n");
					break;
			}

			print("<td bgcolor=\"{$bgcolor}\">&nbsp;{$server['ip']['addr']}:{$pool['port']}&nbsp;</td><td bgcolor=\"{$bgcolor}\">&nbsp;");

			if($server['ip']['avail'])
				print(" ({$server['ip']['avail']}) ");
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
</form>

<?php
if($rowsprinted > 0) {
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

include("foot.inc");
