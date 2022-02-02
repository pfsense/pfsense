<?php
/*
 * firewall_virtual_ip.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>
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
##|*IDENT=page-firewall-virtualipaddresses
##|*NAME=Firewall: Virtual IP Addresses
##|*DESCR=Allow access to the 'Firewall: Virtual IP Addresses' page.
##|*MATCH=firewall_virtual_ip.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("firewall_virtual_ip.inc");

init_config_arr(array('virtualip', 'vip'));
$a_vip = &$config['virtualip']['vip'];
$input_errors = array();
$retval = 0;

if ($_POST['apply']) {
	$rv = applyVIP();
	$retval = $rv['retval'];
}

if ($_POST['act'] == "del") {
	$rv = deleteVIP($_POST['id']);
	$input_errors = $rv['input_errors'];
}

$types = array('proxyarp' => gettext('Proxy ARP'),
			   'carp' => gettext('CARP'),
			   'other' => gettext('Other'),
			   'ipalias' => gettext('IP Alias')
			   );

$pgtitle = array(gettext("Firewall"), gettext("Virtual IPs"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
} else if ($_POST['apply']) {
	print_apply_result_box($retval);
} else if (is_subsystem_dirty('vip')) {
	print_apply_box(gettext("The VIP configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

/* active tabs
$tab_array = array();
$tab_array[] = array(gettext("Virtual IPs"), true, "firewall_virtual_ip.php");
 $tab_array[] = array(gettext("CARP Settings"), false, "system_hasync.php");
display_top_tabs($tab_array);
*/
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Virtual IP Address')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed table-rowdblclickedit sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Virtual IP address")?></th>
					<th><?=gettext("Interface")?></th>
					<th><?=gettext("Type")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
<?php
$interfaces = get_configured_interface_with_descr(true);
$viplist = get_configured_vip_list();

foreach ($viplist as $vipname => $address) {
	$interfaces[$vipname] = $address;
	$interfaces[$vipname] .= " (";
	if (get_vip_descr($address)) {
		$interfaces[$vipname] .= get_vip_descr($address);
	} else {
		$vip = get_configured_vip($vipname);
		$interfaces[$vipname] .= "vhid: {$vip['vhid']}";
	}
	$interfaces[$vipname] .= ")";
}

$interfaces['lo0'] = "Localhost";

$i = 0;
foreach ($a_vip as $vipent):
	if ($vipent['subnet'] != "" or $vipent['range'] != "" or
		$vipent['subnet_bits'] != "" or (isset($vipent['range']['from']) && $vipent['range']['from'] != "")):
?>
				<tr>
					<td>
<?php
	if (($vipent['type'] == "single") || ($vipent['type'] == "network")) {
		if ($vipent['subnet_bits']) {
			print("{$vipent['subnet']}/{$vipent['subnet_bits']}");
		}
	}

	if ($vipent['type'] == "range") {
		print("{$vipent['range']['from']}-{$vipent['range']['to']}");
	}

	if ($vipent['mode'] == "carp") {
		print(" (vhid: {$vipent['vhid']})");
	}
?>
					</td>
					<td>
						<?=htmlspecialchars($interfaces[$vipent['interface']])?>&nbsp;
					</td>
					<td>
						<?=$types[$vipent['mode']]?>
					</td>
					<td>
						<?=htmlspecialchars($vipent['descr'])?>
					</td>
					<td>
						<a class="fa fa-pencil" title="<?=gettext("Edit virtual ip"); ?>" href="firewall_virtual_ip_edit.php?id=<?=$i?>"></a>
						<a class="fa fa-trash"	title="<?=gettext("Delete virtual ip")?>" href="firewall_virtual_ip.php?act=del&amp;id=<?=$i?>" usepost></a>
					</td>
				</tr>
<?php
	endif;
	$i++;
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="firewall_virtual_ip_edit.php" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<div class="infoblock">
	<?php print_info_box(sprintf(gettext('The virtual IP addresses defined on this page may be used in %1$sNAT%2$s mappings.'), '<a href="firewall_nat.php">', '</a>') . '<br />' .
		sprintf(gettext('Check the status of CARP Virtual IPs and interfaces %1$shere%2$s.'), '<a href="status_carp.php">', '</a>'), 'info', false); ?>
</div>

<?php
include("foot.inc");
