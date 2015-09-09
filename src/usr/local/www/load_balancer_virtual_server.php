<?php
/* $Id$ */
/*
	load_balancer_virtual_server.php
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2005-2008 Bill Marquette <bill.marquette@gmail.com>.
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

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
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-services-loadbalancer-virtualservers
##|*NAME=Services: Load Balancer: Virtual Servers page
##|*DESCR=Allow access to the 'Services: Load Balancer: Virtual Servers' page.
##|*MATCH=load_balancer_virtual_server.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("vslb.inc");

if (!is_array($config['load_balancer']['virtual_server'])) {
	$config['load_balancer']['virtual_server'] = array();
}
$a_vs = &$config['load_balancer']['virtual_server'];

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		$retval |= filter_configure();
		$retval |= relayd_configure();
		$savemsg = get_std_save_message($retval);
		/* Wipe out old relayd anchors no longer in use. */
		cleanup_lb_marked();
		clear_subsystem_dirty('loadbalancer');
	}
}

if ($_GET['act'] == "del") {
	if (array_key_exists($_GET['id'], $a_vs)) {

		if (!$input_errors) {
			cleanup_lb_mark_anchor($a_vs[$_GET['id']]['name']);
			unset($a_vs[$_GET['id']]);
			write_config();
			mark_subsystem_dirty('loadbalancer');
			header("Location: load_balancer_virtual_server.php");
			exit;
		}
	}
}

/* Index lbpool array for easy hyperlinking */
$poodex = array();
for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
	$poodex[$config['load_balancer']['lbpool'][$i]['name']] = $i;
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"), gettext("Virtual Servers"));
$shortcut_section = "relayd-virtualservers";

include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('loadbalancer')): ?><br/>
<?php print_info_box_np(gettext("The virtual server configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
<?php endif; 
/* active tabs */
		$tab_array = array();
		$tab_array[] = array(gettext("Pools"), false, "load_balancer_pool.php");
		$tab_array[] = array(gettext("Virtual Servers"), true, "load_balancer_virtual_server.php");
		$tab_array[] = array(gettext("Monitors"), false, "load_balancer_monitor.php");
		$tab_array[] = array(gettext("Settings"), false, "load_balancer_setting.php");
		display_top_tabs($tab_array);
$poodex = array();
for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
	$poodex[$config['load_balancer']['lbpool'][$i]['name']] = $i;
}
?>

<form action="load_balancer_virtual_server.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Virtual Server')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext('Name')?></th>
						<th><?=gettext('Protocol')?></th>
						<th><?=gettext('IP Address')?></th>
						<th><?=gettext('Port')?></th>
						<th><?=gettext('Pool')?></th>
						<th><?=gettext('Fallback Pool')?></th>
						<th><?=gettext('Description')?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
<?php

$idx = 0;
foreach($a_vs as $vs) {
if ($vs['sitedown'] != '') {
			$sitedown = "<a href=\"/load_balancer_pool_edit.php?id=".$poodex[$vs['sitedown']]."\">" . htmlspecialchars($vs['sitedown']) . "</a>";
		} else {
			$sitedown = 'none';
		}	
?>
					<tr>
						<td>
							<?=$vs['name']?>
						</td>
						<td>
							<?=htmlspecialchars($vs['relay_protocol'])?>
						</td>
						<td>
							<?=$vs['ipaddr']?>
						</td>
						<td>
							<?=$vs['port']?>
						</td>
						<td>
							<a href="/load_balancer_pool_edit.php?id=<?=$poodex[$vs['poolname']]?>"> <?= htmlspecialchars($vs['poolname']) ?> </a>
						</td>
						<td>
							<?=$sitedown?>
						</td>
						<td>
							<?=htmlspecialchars($vs['descr'])?>
						</td>
						<td>
							<a href="load_balancer_virtual_server_edit.php?id=<?=$idx?>" class="btn btn-xs btn-info"><?=gettext('Edit')?></a>
							<a href="load_balancer_virtual_server.php?act=del&id=<?=$idx?>" class="btn btn-xs btn-danger"><?=gettext('Delete')?></a>
							<a href="load_balancer_virtual_server_edit.php?act=dup&id=<?=$idx?>" class="btn btn-xs btn-default"><?=gettext('Duplicate')?></a>
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
			<a href="load_balancer_virtual_server_edit.php" class="btn btn-success"><?=gettext('Add')?></a>
		</nav>

	</div>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
