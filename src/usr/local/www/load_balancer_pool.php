<?php
/* $Id$ */
/*
	load_balancer_pool.php
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2005-2008 Bill Marquette <bill.marquette@gmail.com>.
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
	pfSense_MODULE: routing
*/

##|+PRIV
##|*IDENT=page-loadbalancer-pool
##|*NAME=Load Balancer: Pool page
##|*DESCR=Allow access to the 'Load Balancer: Pool' page.
##|*MATCH=load_balancer_pool.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}

$a_pool = &$config['load_balancer']['lbpool'];


if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		$retval |= filter_configure();
		$retval |= relayd_configure();

		$savemsg = get_std_save_message($retval);
		clear_subsystem_dirty('loadbalancer');
	}
}

if ($_GET['act'] == "del") {
	if (array_key_exists($_GET['id'], $a_pool)) {
		/* make sure no virtual servers reference this entry */
		if (is_array($config['load_balancer']['virtual_server'])) {
			foreach ($config['load_balancer']['virtual_server'] as $vs) {
				if ($vs['poolname'] == $a_pool[$_GET['id']]['name']) {
					$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one virtual server.");
					break;
				}
			}
		}

		if (!$input_errors) {
			unset($a_pool[$_GET['id']]);
			write_config();
			mark_subsystem_dirty('loadbalancer');
			header("Location: load_balancer_pool.php");
			exit;
		}
	}
}

/* Index monitor_type array for easy hyperlinking */
$mondex = array();
for ($i = 0; isset($config['load_balancer']['monitor_type'][$i]); $i++) {
	$mondex[$config['load_balancer']['monitor_type'][$i]['name']] = $i;
}

for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
	$a_pool[$i]['monitor'] = "<a href=\"/load_balancer_monitor_edit.php?id={$mondex[$a_pool[$i]['monitor']]}\">" . htmlspecialchars($a_pool[$i]['monitor']) . "</a>";
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"),gettext("Pool"));
$shortcut_section = "relayd";

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg);

if (is_subsystem_dirty('loadbalancer'))
	print_info_box_np(sprintf(gettext("The load balancer configuration has been changed%sYou must apply the changes in order for them to take effect."), "<br />"));

/* active tabs */
$tab_array = array();
$tab_array[] = array(gettext("Pools"), true, "load_balancer_pool.php");
$tab_array[] = array(gettext("Virtual Servers"), false, "load_balancer_virtual_server.php");
$tab_array[] = array(gettext("Monitors"), false, "load_balancer_monitor.php");
$tab_array[] = array(gettext("Settings"), false, "load_balancer_setting.php");
display_top_tabs($tab_array);

?>
<form action="load_balancer_pool.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Pool')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext('Name')?></th>
						<th><?=gettext('Mode')?></th>
						<th><?=gettext('Servers')?></th>
						<th><?=gettext('Port')?></th>
						<th><?=gettext('Monitor')?></th>
						<th><?=gettext('Description')?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
<?php

$idx = 0;
foreach($a_pool as $pool) {
?>
					<tr>
						<td>
							<?=$pool['name']?>
						</td>
						<td>
							<?=htmlspecialchars($pool['mode'])?>
						</td>
						<td>
<?php
	$numsvrs = count($pool['servers']) - 1;

	foreach ($pool['servers'] as $server => $ip) {
		print($ip);
		print(($server < $numsvrs) ? '<br />':'');
	}

?>
						</td>
						<td>
							<?=$pool['port']?>
						</td>
						<td>
							<?=$pool['monitor']?>
						</td>
						<td>
							<?=htmlspecialchars($pool['descr'])?>
						</td>
						<td>
							<a href="load_balancer_pool_edit.php?id=<?=$idx?>" class="btn btn-xs btn-info"><?=gettext('Edit')?></a>
							<a href="load_balancer_pool.php?act=del&id=<?=$idx?>" class="btn btn-xs btn-danger"><?=gettext('Delete')?></a>
							<a href="load_balancer_pool_edit.php?act=dup&id=<?=$idx?>" class="btn btn-xs btn-default"><?=gettext('Duplicate')?></a>
						</td>
					</tr>
<?php
	$idx++;
}
?>
				</tbody>
			</table>
		</div>

		<nav class="action-buttons">
			<a href="load_balancer_pool_edit.php" class="btn btn-success"><?=gettext('Add')?></a>
		</nav>

	</div>
</form>

<?php include("foot.inc");