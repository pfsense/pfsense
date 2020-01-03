<?php
/*
 * status_services.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-status-services
##|*NAME=Status: Services
##|*DESCR=Allow access to the 'Status: Services' page.
##|*MATCH=status_services.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("service-utils.inc");
require_once("shortcuts.inc");

if ($_POST['ajax']) {
	if (isset($_POST['service'])) {
		$service_name = htmlspecialchars($_REQUEST['service']);
	}

	if (!empty($service_name)) {
		switch ($_POST['mode']) {
			case "restartservice":
				$savemsg = service_control_restart($service_name, $_REQUEST);
				break;
			case "startservice":
				$savemsg = service_control_start($service_name, $_REQUEST);
				break;
			case "stopservice":
				$savemsg = service_control_stop($service_name, $_REQUEST);
				break;
		}
		sleep(5);
	}

	exit;
}

$pgtitle = array(gettext("Status"), gettext("Services"));
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$services = get_services();

// $debugsvcs = array('name' => 'captiveportal', 'description' => 'Captive Portal', 'zone' => '14');
// array_push($services, $debugsvcs);

if (count($services) > 0) {
?>
<form action="status_services.php" method="post">
	<input id="mode" type="hidden" name="mode" value=""/>
	<input id="vpnmode" type="hidden" name="vpnmode" value=""/>
	<input id="service" type="hidden" name="service" value=""/>
	<input id="id" type="hidden" name="id" value=""/>
	<input id="zone" type="hidden" name="zone" value=""/>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Services')?></h2></div>
	<div class="panel-body">

	<div class="panel-body panel-default">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Service")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Status")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
<?php

	uasort($services, "service_name_compare");

	foreach ($services as $service) {
		if (empty($service['name'])) {
			continue;
		}

		if (empty($service['description'])) {
			$service['description'] = get_pkg_descr($service['name']);
		}
?>
					<tr>
						<td>
							<?=$service['name']?>
						</td>
						<td>
							<?=$service['description']?>
						</td>
						<td>
							<?= get_service_status_icon($service, false, true, false, "state"); ?>
						</td>
						<td>
							<?=get_service_control_links($service)?>

<?php
		$scut = get_shortcut_by_service_name($service['name']);

		if (!empty($scut)) {
			echo get_shortcut_main_link($scut, true, $service);
			echo get_shortcut_status_link($scut, true, $service);
			echo get_shortcut_log_link($scut, true);
		}
?>
						</td>
					</tr>
<?php
	}
?>
				</tbody>
			</table>
		</div>
	</div>

	</div>
</div>

</form>
<?php
} else {
	print_info_box(gettext("No services found."), 'danger');
}

include("foot.inc");
