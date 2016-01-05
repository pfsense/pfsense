<?php
/*
	status_services.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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

##|+PRIV
##|*IDENT=page-status-services
##|*NAME=Status: Services
##|*DESCR=Allow access to the 'Status: Services' page.
##|*MATCH=status_services.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("service-utils.inc");
require_once("shortcuts.inc");

// Leave GET enabled in case any other pages use it.
// ToDo: Check other pages and remove GET completely
if (!$_GET && $_POST) {
	$_GET = $_POST;
}

$service_name = '';
if (isset($_GET['service'])) {
	$service_name = htmlspecialchars($_GET['service']);
}

if (!empty($service_name)) {
	switch ($_GET['mode']) {
		case "restartservice":
			$savemsg = service_control_restart($service_name, $_GET);
			break;
		case "startservice":
			$savemsg = service_control_start($service_name, $_GET);
			break;
		case "stopservice":
			$savemsg = service_control_stop($service_name, $_GET);
			break;
	}

	sleep(5);
}


/* batch mode, allow other scripts to call this script */
if ($_GET['batch']) {
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
<?php
		// if service is running then listr else listbg
		$bgclass = null;
		$running = false;

		if (get_service_status($service)) {
			$running = true;
		}
?>
						<td>
							<?=$running ? '<span class="text-success">Running</span>':'<span class="text-danger">Stopped</span>'?>
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
	print_info_box(gettext("No services found"), 'danger');
}
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// If a restart button is clicked, populate the hidden inputs and submit the form (via POST)
	$('[id^=restartservice-]').click(function(event) {
		$('#mode').val('restartservice');
		$('#service').val(this.id.replace("restartservice-", ""));
		$(this).parents('form').submit();
	});

	// If a stop button is clicked, populate the hidden inputs and submit the form (via POST)
	$('[id^=stopservice-]').click(function(event) {
		$('#mode').val('stopservice');
		$('#service').val(this.id.replace("stopservice-", ""));
		$(this).parents('form').submit();
	});

	// If a start button is clicked, populate the hidden inputs and submit the form (via POST)
	$('[id^=startservice-]').click(function(event) {
		$('#mode').val('startservice');
		$('#service').val(this.id.replace("startservice-", ""));
		$(this).parents('form').submit();
	});

	// If an openvpn start button is clicked, populate the hidden inputs and submit the form (via POST)
	$('[id^=openvpn-startservice-]').click(function(event) {
		var args = this.id.split('-');
		$('#mode').val('startservice');
		$('#service').val('openvpn');
		$('#id').val(args[3]);
		$('#vpnmode').val(args[2]);
		$(this).parents('form').submit();
	});

	// If an openvpn restart button is clicked, populate the hidden inputs and submit the form (via POST)
	$('[id^=openvpn-restartservice-]').click(function(event) {
		var args = this.id.split('-');
		$('#mode').val('restartservice');
		$('#service').val('openvpn');
		$('#id').val(args[3]);
		$('#vpnmode').val(args[2]);
		$(this).parents('form').submit();
	});

	// If an openvpn stop button is clicked, populate the hidden inputs and submit the form (via POST)
	$('[id^=openvpn-stopservice-]').click(function(event) {
		var args = this.id.split('-');
		$('#mode').val('stopservice');
		$('#service').val('openvpn');
		$('#id').val(args[3]);
		$('#vpnmode').val(args[2]);
		$(this).parents('form').submit();
	});

	// If a captiveportal start button is clicked, populate the hidden inputs and submit the form (via POST)
	$('[id^=captiveportal-startservice-]').click(function(event) {
		var args = this.id.split('-');
		$('#mode').val('startservice');
		$('#service').val('captiveportal');
		$('#zone').val(args[2]);
		$(this).parents('form').submit();
	});

	// If a captiveportal restart button is clicked, populate the hidden inputs and submit the form (via POST)
	$('[id^=captiveportal-restartservice-]').click(function(event) {
		var args = this.id.split('-');
		$('#mode').val('restartservice');
		$('#service').val('captiveportal');
		$('#zone').val(args[2]);
		$(this).parents('form').submit();
	});

	// If a captiveportal stop button is clicked, populate the hidden inputs and submit the form (via POST)
	$('[id^=captiveportal-stopservice-]').click(function(event) {
		var args = this.id.split('-');
		$('#mode').val('stopservice');
		$('#service').val('captiveportal');
		$('#zone').val(args[2]);
		$(this).parents('form').submit();
	});
});
//]]>
</script>
<?php
include("foot.inc");
