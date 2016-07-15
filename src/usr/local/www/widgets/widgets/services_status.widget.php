<?php
/*
 * services_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2007 Sam Wenham
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("captiveportal.inc");
require_once("service-utils.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("/usr/local/www/widgets/include/services_status.inc");

$services = get_services();

$numsvcs = count($services);

for ($idx=0; $idx<$numsvcs; $idx++) {
	$services[$idx]['dispname'] = $services[$idx]['name'];
}

// If there are any duplicated names, add an incrementing suffix
for ($idx=1; $idx < $numsvcs; $idx++) {
	$name = $services[$idx]['name'];

	for ($chk = $idx +1, $sfx=2; $chk <$numsvcs; $chk++) {
		if ($services[$chk]['dispname'] == $name) {
			$services[$chk]['dispname'] .= '_' . $sfx++;
		}
	}
}

if ($_POST) {

	$validNames = array();

	foreach ($services as $service) {
		array_push($validNames, $service['dispname']);
	}

	if (isset($_POST['servicestatusfilter'])) {
		$config['widgets']['servicestatusfilter'] = implode(',', array_intersect($validNames, $_POST['servicestatusfilter']));
	} else {
		$config['widgets']['servicestatusfilter'] = "";
	}

	write_config(gettext("Saved Service Status Filter via Dashboard"));
	header("Location: /index.php");
}

?>
<table class="table table-striped table-hover">
<thead>
	<tr>
		<th></th>
		<th><?=gettext('Service')?></th>
		<th><?=gettext('Description')?></th>
		<th><?=gettext('Action')?></th>
	</tr>
</thead>
<tbody>
<?php
$skipservices = explode(",", $config['widgets']['servicestatusfilter']);

if (count($services) > 0) {
	uasort($services, "service_dispname_compare");

	foreach ($services as $service) {
		if ((!$service['dispname']) || (in_array($service['dispname'], $skipservices)) || (!is_service_enabled($service['dispname']))) {
			continue;
		}

		if (empty($service['description'])) {
			$service['description'] = get_pkg_descr($service['name']);
		}

		$service_desc = explode(".",$service['description']);
?>
		<tr>
			<td><i class="fa fa-<?=get_service_status($service) ? 'check-circle text-success' : 'times-circle text-warning'?>"></i></td>
			<td><?=$service['dispname']?></td>
			<td><?=$service_desc[0]?></td>
			<td><?=get_service_control_links($service)?></td>
		</tr>
<?php
	}
} else {
	echo "<tr><td colspan=\"3\" class=\"text-center\">" . gettext("No services found") . ". </td></tr>\n";
}
?>
</tbody>
</table>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">

<form action="/widgets/widgets/services_status.widget.php" method="post" class="form-horizontal">
	<div class="form-group">
		<label class="col-sm-3 control-label"><?=gettext('Hidden services')?></label>
		<div class="col-sm-6">
			<select multiple id="servicestatusfilter" name="servicestatusfilter[]" class="form-control">
			<?php
				foreach ($services as $service):
					if (!empty(trim($service['dispname'])) || is_numeric($service['dispname'])) {
			?>
				<option <?=(in_array($service['dispname'], $skipservices)?'selected':'')?>><?=$service['dispname']?></option>
			<?php
					}
				endforeach;
			?>
			</select>
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			<button id="clearall" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('Clear')?></button>
		</div>
	</div>
</form>

<script>
//<![CDATA[
events.push(function(){
	$("select[multiple] option").mousedown(function(){
	   var $self = $(this);

	   if ($self.prop("selected"))
	          $self.prop("selected", false);
	   else
	       $self.prop("selected", true);

	   return false;
	});

    $("#clearall").click(function() {
        $('select#servicestatusfilter option').removeAttr("selected");
    });
});
//]]>
</script>
