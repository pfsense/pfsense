<?php
/*
 * status_captiveportal_vouchers.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2007 Marcel Wiget <mwiget@mac.com>
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
##|*IDENT=page-status-captiveportal-vouchers
##|*NAME=Status: Captive Portal Vouchers
##|*DESCR=Allow access to the 'Status: Captive Portal Vouchers' page.
##|*MATCH=status_captiveportal_vouchers.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");
require_once("voucher.inc");

$cpzone = strtolower($_REQUEST['zone']);

init_config_arr(array('captiveportal'));
$a_cp = &$config['captiveportal'];

/* If the zone does not exist, do not display the invalid zone */
if (!array_key_exists($cpzone, $a_cp)) {
	$cpzone = "";
}

if (empty($cpzone)) {
	header("Location: status_captiveportal.php");
	exit;
}

$pgtitle = array(gettext("Status"), gettext("Captive Portal"), htmlspecialchars($a_cp[$cpzone]['zone']), gettext("Active Vouchers"));
$pglinks = array("", "status_captiveportal.php", "status_captiveportal.php?zone=" . $cpzone, "@self");
$shortcut_section = "captiveportal-vouchers";

init_config_arr(array('voucher', $cpzone, 'roll'));

$a_roll = $config['voucher'][$cpzone]['roll'];

$db = array();

foreach ($a_roll as $rollent) {
	$roll = $rollent['number'];
	$minutes = $rollent['minutes'];

	if (!file_exists("{$g['vardb_path']}/voucher_{$cpzone}_active_$roll.db")) {
		continue;
	}

	$active_vouchers = file("{$g['vardb_path']}/voucher_{$cpzone}_active_$roll.db", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($active_vouchers as $voucher => $line) {
		list($voucher, $timestamp, $minutes) = explode(",", $line);
		$remaining = (($timestamp + 60*$minutes) - time());

		if ($remaining > 0) {
			$dbent[0] = $voucher;
			$dbent[1] = $roll;
			$dbent[2] = $timestamp;
			$dbent[3] = intval($remaining/60);
			$dbent[4] = $timestamp + 60*$minutes; // expires at
			$db[] = $dbent;
		}
	}
}

include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Active Users"), false, "status_captiveportal.php?zone=" . htmlspecialchars($cpzone));
$tab_array[] = array(gettext("Active Vouchers"), true, "status_captiveportal_vouchers.php?zone=" . htmlspecialchars($cpzone));
$tab_array[] = array(gettext("Voucher Rolls"), false, "status_captiveportal_voucher_rolls.php?zone=" . htmlspecialchars($cpzone));
$tab_array[] = array(gettext("Test Vouchers"), false, "status_captiveportal_test.php?zone=" . htmlspecialchars($cpzone));
$tab_array[] = array(gettext("Expire Vouchers"), false, "status_captiveportal_expire.php?zone=" . htmlspecialchars($cpzone));
display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=sprintf(gettext("Vouchers in Use (%d)"), count($db))?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Voucher"); ?></th>
					<th><?=gettext("Roll"); ?></th>
					<th><?=gettext("Activated at"); ?></th>
					<th><?=gettext("Expires in"); ?></th>
					<th><?=gettext("Expires at"); ?></th>
				</tr>
			</thead>
			<tbody>
<?php
foreach ($db as $dbent):
?>
				<tr>
					<td><?=htmlspecialchars($dbent[0])?></td>
					<td><?=htmlspecialchars($dbent[1])?></td>
					<td><?=htmlspecialchars(date("m/d/Y H:i:s", $dbent[2]))?></td>
					<td><?=htmlspecialchars($dbent[3])?><?=gettext("min"); ?></td>
					<td><?=htmlspecialchars(date("m/d/Y H:i:s", $dbent[4]))?></td>
				</tr>
<?php
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<?php include("foot.inc");
