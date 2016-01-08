<?php
/*
	system_gateway_groups.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2010 Seth Mos <seth.mos@dds.nl>
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
##|*IDENT=page-system-gatewaygroups
##|*NAME=System: Gateway Groups
##|*DESCR=Allow access to the 'System: Gateway Groups' page.
##|*MATCH=system_gateway_groups.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("openvpn.inc");

if (!is_array($config['gateways']['gateway_group'])) {
	$config['gateways']['gateway_group'] = array();
}

$a_gateway_groups = &$config['gateways']['gateway_group'];
$a_gateways = &$config['gateways']['gateway_item'];
$changedesc = gettext("Gateway Groups") . ": ";

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {

		$retval = 0;

		$retval = system_routing_configure();
		send_multiple_events(array("service reload dyndnsall", "service reload ipsecdns", "filter reload"));

		/* reconfigure our gateway monitor */
		setup_gateways_monitor();

		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			clear_subsystem_dirty('staticroutes');
		}

		foreach ($a_gateway_groups as $gateway_group) {
			$gw_subsystem = 'gwgroup.' . $gateway_group['name'];
			if (is_subsystem_dirty($gw_subsystem)) {
				openvpn_resync_gwgroup($gateway_group['name']);
				clear_subsystem_dirty($gw_subsystem);
			}
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_gateway_groups[$_GET['id']]) {
		$changedesc .= gettext("removed gateway group") . " {$_GET['id']}";
		foreach ($config['filter']['rule'] as $idx => $rule) {
			if ($rule['gateway'] == $a_gateway_groups[$_GET['id']]['name']) {
				unset($config['filter']['rule'][$idx]['gateway']);
			}
		}

		unset($a_gateway_groups[$_GET['id']]);
		write_config($changedesc);
		mark_subsystem_dirty('staticroutes');
		header("Location: system_gateway_groups.php");
		exit;
	}
}

$pgtitle = array(gettext("System"), gettext("Routing"), gettext("Gateway Groups"));
$shortcut_section = "gateway-groups";

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('staticroutes')) {
	print_info_box_np(sprintf(gettext("The gateway configuration has been changed.%sYou must apply the changes in order for them to take effect."), "<br />"));
}

$tab_array = array();
$tab_array[] = array(gettext("Gateways"), false, "system_gateways.php");
$tab_array[] = array(gettext("Static Routes"), false, "system_routes.php");
$tab_array[] = array(gettext("Gateway Groups"), true, "system_gateway_groups.php");
display_top_tabs($tab_array);
?>

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th><?=gettext("Group Name")?></th>
				<th><?=gettext("Gateways")?></th>
				<th><?=gettext("Priority")?></th>
				<th><?=gettext("Description")?></th>
				<th><?=gettext("Actions")?></th>
			</tr>
		</thead>
		<tbody>
<?php
$i = 0;
foreach ($a_gateway_groups as $gateway_group):
?>
			<tr>
				<td>
				   <?=$gateway_group['name']?>
				</td>
				<td>
<?php
	foreach ($gateway_group['item'] as $item) {
		$itemsplit = explode("|", $item);
		print(htmlspecialchars(strtoupper($itemsplit[0])) . "<br />\n");
	}
?>
				</td>
				<td>
<?php
	foreach ($gateway_group['item'] as $item) {
		$itemsplit = explode("|", $item);
		print("Tier ". htmlspecialchars($itemsplit[1]) . "<br />\n");
	}
?>
				</td>
				<td>
					<?=htmlspecialchars($gateway_group['descr'])?>
				</td>
				<td>
					<a href="system_gateway_groups_edit.php?id=<?=$i?>" class="fa fa-pencil" title="<?=gettext('Edit')?>"></a>
					<a href="system_gateway_groups_edit.php?dup=<?=$i?>" class="fa fa-clone" title="<?=gettext('Copy')?>"></a>
					<a href="system_gateway_groups.php?act=del&amp;id=<?=$i?>" class="fa fa-trash" title="<?=gettext('Delete')?>"></a>
				</td>
			</tr>
<?php
	$i++;
endforeach;
?>
		</tbody>
	</table>
</div>

<nav class="action-buttons">
	<a href="system_gateway_groups_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<div class="infoblock">
	<?=print_info_box(gettext('Remember to use these Gateway Groups in firewall rules in order to enable load balancing, failover, ' .
						   'or policy-based routing.' . '<br />' .
						   'Without rules directing traffic into the Gateway Groups, they will not be used.'), 'info')?>
</div>
<?php
include("foot.inc");
