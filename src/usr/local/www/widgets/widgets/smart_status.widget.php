<?php
/*
 * smart_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2012 mkirbst @ pfSense Forum
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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/smart_status.inc");
$specplatform = system_identify_specific_platform();
?>

<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th></th>
			<th><?=gettext("Drive")?></th>
			<th><?=gettext("Ident")?></th>
			<th><?=gettext("S.M.A.R.T. Status")?></th>
		</tr>
	</thead>
	<tbody>
<?php
$devs = array();
## Get all adX, daX, and adaX (IDE, SCSI, and AHCI) devices currently installed
if ($specplatform['name'] != "Hyper-V") {
	$devs = get_smart_drive_list();
}

if (count($devs) > 0)  {
	foreach ($devs as $dev)  { ## for each found drive do
		$dev_ident = exec("diskinfo -v /dev/$dev | grep ident   | awk '{print $1}'"); ## get identifier from drive
		$dev_state = trim(exec("smartctl -H /dev/$dev | awk -F: '/^SMART overall-health self-assessment test result/ {print $2;exit}
/^SMART Health Status/ {print $2;exit}'")); ## get SMART state from drive
		switch ($dev_state) {
			case "PASSED":
			case "OK":
				$color = "text-success";
				$icon = "fa-check";
				break;
			case "":
				$dev_state = gettext("Unknown");
				$color = "text-info";
				$icon = "fa-times-circle";
				break;
			default:
				$color = "text-alert";
				$icon = "fa-question-circle";
				break;
		}
?>
		<tr>
			<td><i class="fa <?=$icon?> <?=$color?>"></i></td>
			<td><?=$dev?></td>
			<td><?=$dev_ident?></td>
			<td><?=ucfirst($dev_state)?></td>
		</tr>
<?php
	}
}
?>
	</tbody>
</table>
