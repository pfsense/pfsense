<?php
/*
	status_carp.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
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
unset($carp_interface_count_cache);
unset($interface_ip_arr_cache);

$status = get_carp_status();
$status = intval($status);

if ($_POST['carp_maintenancemode'] != "") {
	interfaces_carp_set_maintenancemode(!isset($config["virtualip_carp_maintenancemode"]));
}

if ($_POST['disablecarp'] != "") {
	if ($status > 0) {
		set_single_sysctl('net.inet.carp.allow', '0');
		if (is_array($config['virtualip']['vip'])) {
			$viparr = &$config['virtualip']['vip'];
			foreach ($viparr as $vip) {
				switch ($vip['mode']) {
					case "carp":
						interface_vip_bring_down($vip);

						/*
						 * Reconfigure radvd when necessary
						 * XXX: Is it the best way to do it?
						 */
						if (isset($config['dhcpdv6']) && is_array($config['dhcpdv6'])) {
							foreach ($config['dhcpdv6'] as $dhcpv6if => $dhcpv6ifconf) {
								if ($dhcpv6if !== $vip['interface'] ||
								    $dhcpv6ifconf['ramode'] === "disabled") {
									continue;
								}

								services_radvd_configure();
								break;
							}
						}

						sleep(1);
						break;
				}
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
						sleep(1);
						break;
					case 'ipalias':
						if (strpos($vip['interface'], '_vip')) {
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
	set_single_sysctl("net.inet.carp.demotion", "-{$carp_detected_problems}");
	sleep(1);
	$carp_detected_problems = get_single_sysctl("net.inet.carp.demotion");
}

$pgtitle = array(gettext("Status"), gettext("CARP"));
$shortcut_section = "carp";

include("head.inc");
if ($savemsg) {
	print_info_box($savemsg, 'success');
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

?>

<?php
if ($carpcount == 0) {
	print_info_box(gettext('No CARP interfaces have been defined.') . '<br />' .
				   '<a href="system_hasync.php" class="alert-link">' .
				   gettext("You can configure high availability sync settings here") .
				   '</a>');
} else {
?>
<form action="status_carp.php" method="post">
<?php
	if ($status > 0) {
		$carp_enabled = true;
	} else {
		$carp_enabled = false;
	}

	// Sadly this needs to be here so that it is inside the form
	if ($carp_detected_problems > 0) {
		print_info_box(
			gettext("CARP has detected a problem and this unit has been demoted to BACKUP status.") . "<br/>" .
			gettext("Check the link status on all interfaces with configured CARP VIPs.") . "<br/>" .
			gettext("Search the") .
			" <a href=\"/status_logs.php?filtertext=carp%3A+demoted+by\">" .
			gettext("system log") .
			"</a> " .
			gettext("for CARP demotion-related events.") . "<br/><br/>" .
			'<input type="submit" class="btn btn-warning" name="resetdemotion" id="resetdemotion" value="' .
			gettext("Reset CARP Demotion Status") .
			'" />', 'danger'
		);
	}

?>
	<input type="submit" class="btn btn-warning" name="disablecarp" value="<?=($carp_enabled ? gettext("Temporarily Disable CARP") : gettext("Enable CARP"))?>" />
	<input type="submit" class="btn btn-info" name="carp_maintenancemode" id="carp_maintenancemode" value="<?=(isset($config["virtualip_carp_maintenancemode"]) ? gettext("Leave Persistent CARP Maintenance Mode") : gettext("Enter Persistent CARP Maintenance Mode"))?>" />

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

		$ipaddress = $carp['subnet'];
		$vhid = $carp['vhid'];
		$status = get_carp_interface_status("_vip{$carp['uniqid']}");

		if ($carp_enabled == false) {
			$icon = 'times-circle';
			$status = "DISABLED";
		} else {
			if ($status == "MASTER") {
				$icon = 'check-circle';
			} else if ($status == "BACKUP") {
				$icon = 'check-circle-o';
			} else if ($status == "INIT") {
				$icon = 'question-circle';
			}
		}
?>
					<tr>
						<td><?=convert_friendly_interface_to_friendly_descr($carp['interface'])?>@<?=$vhid?></td>
						<td><?=$ipaddress?></td>
						<td><i class="fa fa-<?=$icon?>"></i>&nbsp;<?=$status?></td>
					</tr>
<?php }?>
				</tbody>
			</table>
		</div>
	</div>
</form>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('pfSync nodes')?></h2></div>
	<div class="panel-body">
		<ul>
<?php
	foreach (explode("\n", exec_command("/sbin/pfctl -vvss | /usr/bin/grep creator | /usr/bin/cut -d\" \" -f7 | /usr/bin/sort -u")) as $node) {
		echo '<li>'. $node .'</li>';
	}
?>
		</ul>
	</div>
</div>

<?php
}

include("foot.inc");
