<?php
/*
	load_balancer_virtual_server.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2005-2008 Bill Marquette <bill.marquette@gmail.com>
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

##|+PRIV
##|*IDENT=page-services-loadbalancer-virtualservers
##|*NAME=Services: Load Balancer: Virtual Servers
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
for ($i = 0; isset($config['load_balancer']['virtual_server'][$i]); $i++) {
	if ($a_vs[$i]) {
		$a_vs[$i]['mode'] = htmlspecialchars($a_vs[$i]['mode']);
		$a_vs[$i]['relay_protocol'] = htmlspecialchars($a_vs[$i]['relay_protocol']);
		$a_vs[$i]['poolname'] = "<a href=\"/load_balancer_pool_edit.php?id={$poodex[$a_vs[$i]['poolname']]}\">" . htmlspecialchars($a_vs[$i]['poolname']) . "</a>";
		if ($a_vs[$i]['sitedown'] != '') {
			$a_vs[$i]['sitedown'] = "<a href=\"/load_balancer_pool_edit.php?id={$poodex[$a_vs[$i]['sitedown']]}\">" . htmlspecialchars($a_vs[$i]['sitedown']) . "</a>";
		} else {
			$a_vs[$i]['sitedown'] = 'none';
		}
	}
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"), gettext("Virtual Servers"));
$shortcut_section = "relayd-virtualservers";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('loadbalancer')) {
	print_info_box_np(gettext("The virtual server configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
}

/* active tabs */
$tab_array = array();
$tab_array[] = array(gettext("Pools"), false, "load_balancer_pool.php");
$tab_array[] = array(gettext("Virtual Servers"), true, "load_balancer_virtual_server.php");
$tab_array[] = array(gettext("Monitors"), false, "load_balancer_monitor.php");
$tab_array[] = array(gettext("Settings"), false, "load_balancer_setting.php");
display_top_tabs($tab_array);
?>

<form action="load_balancer_virtual_server.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Virtual Servers')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th><?=gettext('Name')?></th>
						<th><?=gettext('Protocol')?></th>
						<th><?=gettext('IP Address'); ?></th>
						<th><?=gettext('Port'); ?></th>
						<th><?=gettext('Pool'); ?></th>
						<th><?=gettext('Fallback pool'); ?></th>
						<th><?=gettext('Description'); ?></th>
						<th><!-- Action buttons --></th>
					</tr>
				</thead>
				<tbody>
<?php
if (!empty($a_vs)) {
	$i = 0;
	foreach ($a_vs as $a_v) {
?>
					<tr>
						<td><?=htmlspecialchars($a_v['name'])?></td>
						<td><?=htmlspecialchars($a_v['relay_protocol'])?></td>
						<td><?=htmlspecialchars($a_v['ipaddr'])?></td>
						<td><?=htmlspecialchars($a_v['port'])?></td>
						<td><?=$a_v['poolname']?></td>
						<td><?=$a_v['sitedown']?></td>
						<td><?=htmlspecialchars($a_v['descr'])?></td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit virtual server')?>"	href="load_balancer_virtual_server_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-clone"	title="<?=gettext('Copy virtual server')?>"	href="load_balancer_virtual_server_edit.php?act=dup&id=<?=$i?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Delete virtual server')?>"	href="load_balancer_virtual_server.php?act=del&id=<?=$i?>"></a>
						</td>
					</tr>
<?php
		$i++;
	}
} else {
?>						<tr>
							<td	 colspan="8"> <?php
								print_info_box(gettext('No virtual servers have been configured'));
?>							</td>
						</tr> <?php
}
?>
				</tbody>
			</table>
		</div>
	</div>
</form>

<nav class="action-buttons">
	<a href="load_balancer_virtual_server_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<?php

include("foot.inc");
