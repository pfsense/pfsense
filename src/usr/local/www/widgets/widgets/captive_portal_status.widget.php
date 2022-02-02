<?php
/*
 * captive_portal_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2007 Sam Wenham
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
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

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

init_config_arr(array('captiveportal'));
$a_cp = &$config['captiveportal'];

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}
$cpzone = strtolower($cpzone);

if (isset($cpzone) && !empty($cpzone) && isset($a_cp[$cpzone]['zoneid'])) {
	$cpzoneid = $a_cp[$cpzone]['zoneid'];
}

if (($_GET['act'] == "del") && !empty($cpzone) && isset($cpzoneid)) {
	captiveportal_disconnect_client($_GET['id'], 6);
}
unset($cpzone);

flush();

if (!function_exists('clientcmp')) {
	function clientcmp($a, $b) {
		global $order;
		return strcmp($a[$order], $b[$order]);
	}
}

$cpdb_all = array();

foreach ($a_cp as $cpzone => $cp) {
	$cpdb = captiveportal_read_db();
	foreach ($cpdb as $cpent) {
		$cpent[10] = $cpzone;
		$cpent[11] = captiveportal_get_last_activity($cpent[2]);
		$cpdb_all[] = $cpent;
	}
}

?>
<div class="table-responsive">
	<table class="table table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
		<tr>
			<th><?=gettext("IP address");?></th>
			<th><?=gettext("MAC address");?></th>
			<th><?=gettext("Username");?></th>
			<th><?=gettext("Session start");?></th>
			<th><?=gettext("Last activity");?></th>
			<th>&nbsp;</th>
		</tr>
		</thead>
		<tbody>
	<?php foreach ($cpdb_all as $cpent): ?>
		<tr>
			<td><?=$cpent[2];?></td>
			<td><?=$cpent[3];?></td>
			<td><?=$cpent[4];?></td>
			<td><?=date("m/d/Y H:i:s", $cpent[0]);?></td>
			<td>
<?php
			if ($cpent[11] && ($cpent[11] > 0)):
				echo date("m/d/Y H:i:s", $cpent[11]);
			else:
				echo "&nbsp;";
			endif;
?>
			</td>
			<td>
				<a href="?order=<?=htmlspecialchars($_GET['order']);?>&amp;showact=<?=$showact;?>&amp;act=del&amp;zone=<?=$cpent[10];?>&amp;id=<?=$cpent[5];?>">
					<i class="fa fa-trash" title="<?=gettext("delete");?>"></i>
				</a>
			</td>
		</tr>
	<?php
	endforeach;
	?>
		</tbody>
	</table>
</div>
