<?php
/*
	services_status.widget.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2004, 2005 Scott Ullrich
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

if(isset($_POST['servicestatusfilter'])) {
	$validNames = array();
	foreach ($services as $service)
		array_push($validNames, $service['name']);

	$config['widgets']['servicestatusfilter'] = implode(',', array_intersect($validNames, $_POST['servicestatusfilter']));
	write_config("Saved Service Status Filter via Dashboard");
	header("Location: /");
}
?>
<table class="table table-striped table-hover">
<thead>
	<tr>
		<th></th>
		<th>Service</td>
		<th>Description</td>
		<th>Action</td>
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
			<td><i class="icon icon-<?=get_service_status($service)? 'ok' : 'remove'?>-sign"></i></td>
			<td><?=$service['name']?></td>
			<td><?=$service_desc[0]?></td>
			<td><?=get_service_control_GET_links($service)?></td>
		</tr>
<?php
	}
} else {
	echo "<tr><td colspan=\"3\" align=\"center\">" . gettext("No services found") . " . </td></tr>\n";
}
?>
</tbody>
</table>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div class="panel-footer collapse">

<form action="/widgets/widgets/services_status.widget.php" method="post" class="form-horizontal">
	<div class="form-group">
		<label for="inputPassword3" class="col-sm-3 control-label">Hidden services</label>
		<div class="col-sm-6">
			<select multiple="multiple" name="servicestatusfilter[]" class="form-control" height="5">
			<?php foreach ($services as $service): ?>
				<option <?=(in_array($service['name'], $skipservices)?'selected="selected"':'')?>><?=$service['name']?></option>
			<?php endforeach; ?>
			</select>
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-default">Save</button>
		</div>
	</div>
</form>