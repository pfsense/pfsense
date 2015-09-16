<?php
/*
	status_services.php
	Copyright (C) 2004, 2005 Scott Ullrich
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/openvpn	/usr/bin/killall	/bin/ps
	pfSense_MODULE:	services
*/

##|+PRIV
##|*IDENT=page-status-services
##|*NAME=Status: Services page
##|*DESCR=Allow access to the 'Status: Services' page.
##|*MATCH=status_services.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("service-utils.inc");
require_once("shortcuts.inc");

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

if ($savemsg)
	print_info_box($savemsg, 'success');

$services = get_services();

if (count($services) > 0) {
?>
<form action="status_services.php" method="post">
	<div class="panel-body panel-default">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
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

	foreach($services as $service) {
		if (empty($service['name']))
			continue;

		if (empty($service['description']))
			$service['description'] = get_pkg_descr($service['name']);
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

		if (get_service_status($service))
			$running = true;
?>
						<td>
							<?=$running ? '<font color="green">Running</font>':'<font color="red">Stopped</font>'?>
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
</form>
<?php
} else {
	print_info_box(gettext("No services found"), 'danger');
}

include("foot.inc");