<?php
/*
	services_status.widget.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Copyright (c)  2007 Sam Wenham
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("captiveportal.inc");
require_once("service-utils.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("/usr/local/www/widgets/include/services_status.inc");

$services = get_services();

if ($_POST) {
	$validNames = array();

	foreach ($services as $service) {
		array_push($validNames, $service['name']);
	}

	if (isset($_POST['servicestatusfilter'])) {
		$config['widgets']['servicestatusfilter'] = implode(',', array_intersect($validNames, $_POST['servicestatusfilter']));
	} else {
		$config['widgets']['servicestatusfilter'] = "";
	}

	write_config("Saved Service Status Filter via Dashboard");
	header("Location: /index.php");
}

?>
<table class="table table-striped table-hover">
<thead>
	<tr>
		<th></th>
		<th>Service</th>
		<th>Description</th>
		<th>Action</th>
	</tr>
</thead>
<tbody>
<?php
$skipservices = explode(",", $config['widgets']['servicestatusfilter']);

if (count($services) > 0) {
	uasort($services, "service_name_compare");
	foreach ($services as $service) {
		if ((!$service['name']) || (in_array($service['name'], $skipservices)) || (!is_service_enabled($service['name']))) {
			continue;
		}
		if (empty($service['description'])) {
			$service['description'] = get_pkg_descr($service['name']);
		}
		$service_desc = explode(".",$service['description']);
?>
		<tr>
			<td><i class="fa fa-<?=get_service_status($service) ? 'check-circle text-success' : 'times-circle text-warning'?>"></i></td>
			<td><?=$service['name']?></td>
			<td><?=$service_desc[0]?></td>
			<td><?=get_service_control_GET_links($service)?></td>
		</tr>
<?php
	}
} else {
	echo "<tr><td colspan=\"3\" class=\"text-center\">" . gettext("No services found") . " . </td></tr>\n";
}
?>
</tbody>
</table>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">

<form action="/widgets/widgets/services_status.widget.php" method="post" class="form-horizontal">
	<div class="form-group">
		<label for="inputPassword3" class="col-sm-3 control-label">Hidden services</label>
		<div class="col-sm-6">
			<select multiple id="servicestatusfilter" name="servicestatusfilter[]" class="form-control">
			<?php
				foreach ($services as $service): 
					if (!empty(trim($service['name'])) || is_numeric($service['name'])) {
			?>
				<option <?=(in_array($service['name'], $skipservices)?'selected':'')?>><?=$service['name']?></option>
			<?php
					}
				endforeach;
			?>
			</select>
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary">Save</button>
			<button id="clearall" type="button" class="btn btn-default">Clear</button>
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
