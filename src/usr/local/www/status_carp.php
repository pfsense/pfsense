<?php
/*
 * status_carp.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-status-carp
##|*NAME=Status: CARP
##|*DESCR=Allow access to the 'Status: CARP' page.
##|*MATCH=status_carp.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("globals.inc");

unset($interface_arr_cache);
unset($interface_ip_arr_cache);


function find_ipalias($carpif) {
	global $config;

	$ips = array();
	foreach ($config['virtualip']['vip'] as $vip) {
		if ($vip['mode'] != "ipalias") {
			continue;
		}
		if ($vip['interface'] != $carpif) {
			continue;
		}
		$ips[] = "{$vip['subnet']}/{$vip['subnet_bits']}";
	}

	return ($ips);
}

$status = get_carp_status();

if ($status != 0 && $_POST['carp_maintenancemode'] != "") {
	interfaces_carp_set_maintenancemode(!isset($config["virtualip_carp_maintenancemode"]));
}

if ($_POST['disablecarp'] != "") {
	if ($status != 0) {
		set_single_sysctl('net.inet.carp.allow', '0');
		if (is_array($config['virtualip']['vip'])) {
			$viparr = &$config['virtualip']['vip'];
			foreach ($viparr as $vip) {
				if ($vip['mode'] != "carp" && $vip['mode'] != "ipalias")
					continue;
				if ($vip['mode'] == "ipalias" && substr($vip['interface'], 0, 4) != "_vip")
					continue;

				interface_vip_bring_down($vip);
			}
		}
		$savemsg = sprintf(gettext("%s IPs have been disabled. Please note that disabling does not survive a reboot and some configuration changes will re-enable."), $carp_counter);
		$status = 0;
	} else {
		$savemsg = gettext("CARP has been enabled.");
		if (is_array($config['virtualip']['vip'])) {
			$viparr = &$config['virtualip']['vip'];
			foreach ($viparr as $vip) {
				switch ($vip['mode']) {
					case "carp":
						interface_carp_configure($vip);
						break;
					case 'ipalias':
						if (substr($vip['interface'], 0, 4) == "_vip") {
							interface_ipalias_configure($vip);
						}
						break;
				}
			}
		}
		interfaces_sync_setup();
		set_single_sysctl('net.inet.carp.allow', '1');
		$status = 1;
	}
}

$carp_detected_problems = get_single_sysctl("net.inet.carp.demotion");

if (!empty($_POST['resetdemotion'])) {
	set_single_sysctl("net.inet.carp.demotion", 0 - $carp_detected_problems);
	sleep(1);
	$carp_detected_problems = get_single_sysctl("net.inet.carp.demotion");
}

$pgtitle = array(gettext("Status"), gettext("CARP"));
$shortcut_section = "carp";

include("head.inc");
if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if ($status == 0 && $_POST['carp_maintenancemode'] != "") {
	print_info_box('Please enable CARP before setting the maintenance mode.');
}

$carpcount = 0;
if (is_array($config['virtualip']['vip'])) {
	foreach ($config['virtualip']['vip'] as $carp) {
		if ($carp['mode'] == "carp") {
			$carpcount++;
			break;
		}
	}
}

// If $carpcount > 0 display buttons then display table
// otherwise display error box and quit

if ($carpcount == 0) {
	print_info_box(gettext('No CARP interfaces have been defined.') . '<br />' .
				   '<a href="system_hasync.php" class="alert-link">' .
				   gettext("High availability sync settings can be configured here.") .
				   '</a>');
} else {
?>
<form action="status_carp.php" method="post">
<?php
	if ($status != 0) {
		$carp_enabled = true;
	} else {
		$carp_enabled = false;
	}

	// Sadly this needs to be here so that it is inside the form
	if ($carp_detected_problems != 0) {
		print_info_box(
			gettext("CARP has detected a problem and this unit has a non-zero demotion status.") .
			"<br/>" .
			gettext("Check the link status on all interfaces configured with CARP VIPs and ") .
			sprintf(gettext('search the %1$sSystem Log%2$s for CARP demotion-related events.'), "<a href=\"/status_logs.php?filtertext=carp%3A+demoted+by\">", "</a>") .
			"<br/><br/>" .
			'<button type="submit" class="btn btn-warning" name="resetdemotion" id="resetdemotion" value="' .
			gettext("Reset CARP Demotion Status") .
			'"><i class="fa fa-undo icon-embed-btn"></i>' .
			gettext("Reset CARP Demotion Status") .
			'</button>',
			'danger'
		);
	}

?>
	<button type="submit" class="btn btn-warning" name="disablecarp" value="<?=($carp_enabled ? gettext("Temporarily Disable CARP") : gettext("Enable CARP"))?>" ><i class="fa fa-<?=($carp_enabled) ? 'ban' : 'check' ; ?> icon-embed-btn"></i><?=($carp_enabled ? gettext("Temporarily Disable CARP") : gettext("Enable CARP"))?></button>
	<button type="submit" class="btn btn-info" name="carp_maintenancemode" id="carp_maintenancemode" value="<?=(isset($config["virtualip_carp_maintenancemode"]) ? gettext("Leave Persistent CARP Maintenance Mode") : gettext("Enter Persistent CARP Maintenance Mode"))?>" ><i class="fa fa-wrench icon-embed-btn"></i><?=(isset($config["virtualip_carp_maintenancemode"]) ? gettext("Leave Persistent CARP Maintenance Mode") : gettext("Enter Persistent CARP Maintenance Mode"))?></button>

	<br /><br />

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('CARP Interfaces')?></h2></div>
			<div class="panel-body table-responsive">
				<table class="table table-striped table-condensed table-hover sortable-theme-bootstrap " data-sortable>
					<thead>
						<tr>
							<th><?=gettext("CARP Interface")?></th>
							<th><?=gettext("Virtual IP")?></th>
							<th><?=gettext("Status")?></th>
						</tr>
					</thead>
					<tbody>
<?php
	foreach ($config['virtualip']['vip'] as $carp) {
		if ($carp['mode'] != "carp") {
			continue;
		}

		$icon = '';
		$vhid = $carp['vhid'];
		$status = get_carp_interface_status("_vip{$carp['uniqid']}");
		$aliases = find_ipalias("_vip{$carp['uniqid']}");

		if ($carp_enabled == false) {
			$icon = 'times-circle';
			$status = "DISABLED";
		} else {
			if ($status == "MASTER") {
				$icon = 'play-circle text-success';
			} else if ($status == "BACKUP") {
				$icon = 'pause-circle text-warning';
			} else if ($status == "INIT") {
				$icon = 'question-circle text-danger';
			}
		}
?>
					<tr>
						<td><?=convert_friendly_interface_to_friendly_descr($carp['interface'])?>@<?=$vhid?></td>
						<td>
<?php
		printf("{$carp['subnet']}/{$carp['subnet_bits']}");
		for ($i = 0; $i < count($aliases); $i++) {
			printf("<br>{$aliases[$i]}");
		}
?>
						</td>
						<td><i class="fa fa-<?=$icon?>"></i>&nbsp;<?=$status?></td>
					</tr>
<?php }?>
				</tbody>
			</table>
		</div>
	</div>
</form>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('pfSync Nodes')?></h2></div>
	<div class="panel-body">
		<ul>
<?php
        echo "<br />" . gettext("pfSync nodes") . ":<br />";
        echo "<pre>";
        system("/sbin/pfctl -vvss | /usr/bin/grep creator | /usr/bin/cut -d\" \" -f7 | /usr/bin/sort -u");
        echo "</pre>";
?>

		</ul>
	</div>
</div>

<?php
}

include("foot.inc");
