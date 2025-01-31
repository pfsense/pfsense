<?php
/*
 * carp_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2007 Sam Wenham
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
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
require_once("/usr/local/www/widgets/include/carp_status.inc");

$carp_enabled = get_carp_status();

?>
<div class="content">
<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th><?=gettext("CARP Interface")?></th>
			<th><?=gettext("IP Address")?></th>
			<th><?=gettext("Status")?></th>
		</tr>
	</thead>
	<tbody>
<?php

	$carpint=0;
	foreach (config_get_path('virtualip/vip', []) as $carp) {
		if ($carp['mode'] != "carp") {
			continue;
		}
		$carpint++;
		$ipaddress = $carp['subnet'];
		$netmask = $carp['subnet_bits'];
		$vhid = $carp['vhid'];
		$status = get_carp_interface_status("_vip{$carp['uniqid']}");
?>
		<tr>
			<td>
				<span title="<?=htmlspecialchars($carp['descr'])?>">
					<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($carp['interface']) . "@{$vhid}");?>
				</span>
			</td>
<?php		
		if ($carp_enabled == false) {
			$icon = 'fa-solid fa-times-circle';
			$status = "DISABLED";
		} else {
			if ($status == "MASTER") {
				$icon = 'fa-solid fa-play-circle text-success';
			} else if ($status == "BACKUP") {
				$icon = 'fa-solid fa-pause-circle text-warning';
			} else if ($status == "INIT") {
				$icon = 'fa-solid fa-question-circle text-danger';
			}
		}
		if ($ipaddress) {
?>
				<td><?=htmlspecialchars($ipaddress);?></td>
				<td><i class="<?=$icon?>"></i>&nbsp;<?= htmlspecialchars($status) ?></td>
<?php
		} else {
?>
				<td colspan="2">
<?php
		}
?>
		</tr>
<?php
	}
	if ($carpint === 0) {
?>
		<tr><td colspan="3"><?=gettext('No CARP Interfaces Defined.')?> <?=sprintf(gettext('Click %1$shere%2$s to configure CARP.'), '<a href="status_carp.php">', '</a>')?></td></tr>
<?php
	}
?>
	</tbody>
</table>
</div>
