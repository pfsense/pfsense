<?php
/*
 * services_captiveportal_ip.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2004 Dinesh Nair <dinesh@alphaque.com>
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
##|*IDENT=page-services-captiveportal-allowedips
##|*NAME=Services: Captive Portal: Allowed IPs
##|*DESCR=Allow access to the 'Services: Captive Portal: Allowed IPs' page.
##|*MATCH=services_captiveportal_ip.php*
##|-PRIV

$directionicons = array('to' => '&#x2192;', 'from' => '&#x2190;', 'both' => '&#x21c4;');

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");

$cpzone = strtolower(htmlspecialchars($_REQUEST['zone']));

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

init_config_arr(array('captiveportal'));
$a_cp = &$config['captiveportal'];

if (isset($cpzone) && !empty($cpzone) && isset($a_cp[$cpzone]['zoneid'])) {
	$cpzoneid = $a_cp[$cpzone]['zoneid'];
}

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), $a_cp[$cpzone]['zone'], gettext("Allowed IP Addresses"));
$pglinks = array("", "services_captiveportal_zones.php", "services_captiveportal.php?zone=" . $cpzone, "@self");
$shortcut_section = "captiveportal";

if ($_POST['act'] == "del" && !empty($cpzone)) {
	init_config_arr(array('captiveportal', $cpzone, 'allowedip'));
	$a_allowedips = &$config['captiveportal'][$cpzone]['allowedip'];

	if ($a_allowedips[$_POST['id']]) {
		$ipent = $a_allowedips[$_POST['id']];

		if (isset($config['captiveportal'][$cpzone]['enable'])) {
			$mask = (!empty($ipent['sn'])) ? $ipent['sn'] : 32;

			$rule = pfSense_ipfw_table_lookup("{$cpzone}_allowed_up", "{$ipent['ip']}/{$mask}");

			pfSense_ipfw_table("{$cpzone}_allowed_up", IP_FW_TABLE_XDEL, "{$ipent['ip']}/{$mask}");
			pfSense_ipfw_table("{$cpzone}_allowed_down", IP_FW_TABLE_XDEL, "{$ipent['ip']}/{$mask}");

			if (is_array($rule) && !empty($rule['pipe'])) {
				captiveportal_free_dn_ruleno($rule['pipe']);
				pfSense_ipfw_pipe("pipe delete {$rule['pipe']}");
				pfSense_ipfw_pipe("pipe delete " . ($rule['pipe']+1));
			}
		}

		unset($a_allowedips[$_POST['id']]);
		write_config("Captive portal allowed IPs saved");
		header("Location: services_captiveportal_ip.php?zone={$cpzone}");
		exit;
	}
}

include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Configuration"), false, "services_captiveportal.php?zone={$cpzone}");
$tab_array[] = array(gettext("MACs"), false, "services_captiveportal_mac.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed IP Addresses"), true, "services_captiveportal_ip.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
$tab_array[] = array(gettext("High Availability"), false, "services_captiveportal_hasync.php?zone={$cpzone}");
$tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
display_top_tabs($tab_array, true);

?>
<div class="table-responsive">
	<table class="table table-hover table-striped table-condensed table-rowdblclickedit sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th><?=gettext("IP Addresses"); ?></th>
				<th><?=gettext("Description"); ?></th>
				<th data-sortable="false"><?=gettext("Actions"); ?></th>
			</tr>
		</thead>

<?php
if (is_array($a_cp[$cpzone]['allowedip'])): ?>
		<tbody>
<?php
	$i = 0;
	foreach ($a_cp[$cpzone]['allowedip'] as $ip): ?>
			<tr>
				<td>
					<?=$directionicons[$ip['dir']]?>&nbsp;<?=$ip['ip']?>
					<?=($ip['sn'] != "32" && is_numeric($ip['sn'])) ? '/' . $ip['sn'] : ''?>
				</td>
				<td >
					<?=htmlspecialchars($ip['descr'])?>
				</td>
				<td>
					<a class="fa fa-pencil"	title="<?=gettext("Edit IP"); ?>" href="services_captiveportal_ip_edit.php?zone=<?=$cpzone?>&amp;id=<?=$i?>"></a>
					<a class="fa fa-trash"	title="<?=gettext("Delete IP")?>" href="services_captiveportal_ip.php?zone=<?=$cpzone?>&amp;act=del&amp;id=<?=$i?>" usepost></a>
				</td>
			</tr>
<?php
	$i++;
	endforeach; ?>
		<tbody>
	</table>

	<?=$directionicons['to']   . ' = ' . sprintf(gettext('All connections %1$sto%2$s the address are allowed'), '<u>', '</u>') . ', '?>
	<?=$directionicons['from'] . ' = ' . sprintf(gettext('All connections %1$sfrom%2$s the address are allowed'), '<u>', '</u>') . ', '?>
	<?=$directionicons['both'] . ' = ' . sprintf(gettext('All connections %1$sto or from%2$s are allowed'), '<u>', '</u>')?>
<?php
else:
?>
		</tbody>
	</table>
<?php
endif;
?>
</div>

<nav class="action-buttons">
	<a href="services_captiveportal_ip_edit.php?zone=<?=$cpzone?>&amp;act=add" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<div class="infoblock">
<?php print_info_box(gettext('Adding allowed IP addresses will allow IP access to/from these addresses through the captive portal without being taken to the portal page. ' .
					   'This can be used for a web server serving images for the portal page or a DNS server on another network, for example.'), 'info', false); ?>
</div>

<?php
include("foot.inc");
