<?php
/*
 * status_captiveportal_voucher_rolls.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-status-captiveportal-voucher-rolls
##|*NAME=Status: Captive Portal Voucher Rolls
##|*DESCR=Allow access to the 'Status: Captive Portal Voucher Rolls' page.
##|*MATCH=status_captiveportal_voucher_rolls.php*
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
	header("Location: services_captiveportal_zones.php");
	exit;
}

$pgtitle = array(gettext("Status"), gettext("Captive Portal"), htmlspecialchars($a_cp[$cpzone]['zone']), gettext("Voucher Rolls"));
$pglinks = array("", "status_captiveportal.php", "status_captiveportal.php?zone=" . $cpzone, "@self");
$shortcut_section = "captiveportal-vouchers";

if (!is_array($config['voucher'][$cpzone]['roll'])) {
	$config['voucher'][$cpzone]['roll'] = array();
}

init_config_arr(array('voucher', $cpzone, 'roll'));
$a_roll = &$config['voucher'][$cpzone]['roll'];

include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Active Users"), false, "status_captiveportal.php?zone=" . htmlspecialchars($cpzone));
$tab_array[] = array(gettext("Active Vouchers"), false, "status_captiveportal_vouchers.php?zone=" . htmlspecialchars($cpzone));
$tab_array[] = array(gettext("Voucher Rolls"), true, "status_captiveportal_voucher_rolls.php?zone=" . htmlspecialchars($cpzone));
$tab_array[] = array(gettext("Test Vouchers"), false, "status_captiveportal_test.php?zone=" . htmlspecialchars($cpzone));
$tab_array[] = array(gettext("Expire Vouchers"), false, "status_captiveportal_expire.php?zone=" . htmlspecialchars($cpzone));
display_top_tabs($tab_array);
?>

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
    			<tr>
    				<th><?=gettext("Roll#"); ?></th>
    				<th><?=gettext("Minutes/Ticket"); ?></th>
    				<th><?=gettext("# of Tickets"); ?></th>
    				<th><?=gettext("Comment"); ?></th>
    				<th><?=gettext("used"); ?></th>
    				<th><?=gettext("active"); ?></th>
    				<th><?=gettext("ready"); ?></th>
    			</tr>
		</thead>
		<tbody>
<?php
			$voucherlck = lock("vouche{$cpzone}r");
			$i = 0;
			foreach ($a_roll as $rollent):
				$used = voucher_used_count($rollent['number']);
				$active = count(voucher_read_active_db($rollent['number']), $rollent['minutes']);
				$ready = $rollent['count'] - $used;
				/* used also count active vouchers, remove them */
				$used = $used - $active;
?>
    			<tr>
    				<td><?=htmlspecialchars($rollent['number'])?></td>
    				<td><?=htmlspecialchars($rollent['minutes'])?></td>
    				<td><?=htmlspecialchars($rollent['count'])?></td>
    				<td><?=htmlspecialchars($rollent['comment'])?></td>
    				<td><?=htmlspecialchars($used)?></td>
    				<td><?=htmlspecialchars($active)?></td>
    				<td><?=htmlspecialchars($ready)?></td>
    			</tr>
<?php
				$i++;
			endforeach;

			unlock($voucherlck)?>
		</tbody>
	</table>
</div>
<?php include("foot.inc");
