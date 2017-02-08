<?php
/*
 * services_ntpd_gps.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 Dagorlad
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
##|*IDENT=page-services-ntpd-gps
##|*NAME=Services: NTP Serial GPS
##|*DESCR=Allow access to the 'Services: NTP Serial GPS' page.
##|*MATCH=services_ntpd_gps.php*
##|-PRIV

require_once("guiconfig.inc");

if (!is_array($config['ntpd'])) {
	$config['ntpd'] = array();
}
	
if (!is_array($config['ntpd']['gpss'])) {
	$config['ntpd']['gpss'] = array();
}

if (is_array($config['ntpd']['gps'])) {
	// convert old style
	if (!isset($config['ntpd']['gps']['speed'])) {
		$config['ntpd']['gps']['speed']=0;
	}
	if (!isset($config['ntpd']['gps']['prefer'])) {
		$config['ntpd']['gps']['prefer']='yes';
	}
	$config['ntpd']['gpss'][] = $config['ntpd']['gps'];
	unset($config['ntpd']['gps']);
	write_config(gettext("Upgraded GPS Settings"));
	system_ntp_configure();
	pfSenseHeader("services_ntpd_gps.php");
	exit;
}

$a_gps = &$config['ntpd']['gpss'];

if ($_GET['act'] == "toggle_prefer") {
	if ($a_gps[$_GET['id']]) {
		if (isset($a_gps[$_GET['id']]['prefer'])){
			unset($a_gps[$_GET['id']]['prefer']);
		} else {
			$a_gps[$_GET['id']]['prefer'] = 'yes';
			if (isset($a_gps[$_GET['id']]['noselect'])){
				unset($a_gps[$_GET['id']]['noselect']);
			}
		}
		write_config(gettext("Updated NTP GPS Settings"));
		system_ntp_configure();
		pfSenseHeader("services_ntpd_gps.php");
		exit;
	}
}
	
if ($_GET['act'] == "toggle_no_select") {
	if ($a_gps[$_GET['id']]) {
		if (isset($a_gps[$_GET['id']]['noselect'])){
			unset($a_gps[$_GET['id']]['noselect']);
		} else {
			$a_gps[$_GET['id']]['noselect'] = 'yes';
			if (isset($a_gps[$_GET['id']]['prefer'])){
				unset($a_gps[$_GET['id']]['prefer']);
			}
		}
		write_config(gettext("Updated NTP GPS Settings"));
		system_ntp_configure();
		pfSenseHeader("services_ntpd_gps.php");
		exit;
	}
}
	
if ($_GET['act'] == "swap") {
	if ($a_gps[$_GET['id1']] && $a_gps[$_GET['id2']]) {
		$temp = $a_gps[$_GET['id1']];
		$a_gps[$_GET['id1']] = $a_gps[$_GET['id2']];
		$a_gps[$_GET['id2']] = $temp;
		write_config(gettext("Updated NTP GPS Settings"));
		system_ntp_configure();
		pfSenseHeader("services_ntpd_gps.php");
		exit;
	}
}

if ($_GET['act'] == "del") {
	if ($a_gps[$_GET['id']]) {
		unlink_if_exists('/dev/gps' . $_GET['id']);
		/* Remove old /etc/remote entry if it exists */
		$gps_shortname = 'gps' . $_GET['id'];
		if (intval(`grep -c "^{$gps_shortname}" /etc/remote`) != 0) {
			mwexec("sed -n '/'{$gps_shortname}'/!p' /etc/remote > /etc/remote.new");
			mwexec('mv /etc/remote.new /etc/remote');
		}
		unset($a_gps[$_GET['id']]);
		write_config(gettext("Deleted GPS setting"));
		system_ntp_configure();
		pfSenseHeader("services_ntpd_gps.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("NTP"), gettext("Serial GPS"));
$pglinks = array("", "services_ntpd.php", "@self");
$shortcut_section = "ntp";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "services_ntpd.php");
$tab_array[] = array(gettext("ACLs"), false, "services_ntpd_acls.php");
$tab_array[] = array(gettext("Serial GPS"), true, "services_ntpd_gps.php");
$tab_array[] = array(gettext("PPS"), false, "services_ntpd_pps.php");
display_top_tabs($tab_array);
$baud=[0 => '4800', 16 => '9600', 32 => '19200', 48 => '38400', 64 => '57600', 80 => '115200'];
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('GPSs')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
			<thead>
				<tr>
					<th><?=gettext("ID")?></th>
					<th><?=gettext("Type")?></th>
					<th><?=gettext("Port")?></th>
					<th><?=gettext("Baud")?></th>
					<th><?=gettext("Prefer")?></th>
					<th><?=gettext("No Select")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($a_gps as $i => $gps): ?>
					<tr>
						<td>
							127.127.20.<?=$i;?>
						</td>
						<td>
							<?=$gps['type'];?>
						</td>
						<td>
							<?=$gps['port'];?>
						</td>
						<td>
							<?=$baud[$gps['speed']];?>
						</td>
						<td>
							<input type="checkbox" id="frc<?=$i;?>" onClick="document.location='services_ntpd_gps.php?act=toggle_prefer&amp;id=<?=$i;?>';" <?=($gps['prefer'] ? 'checked':'')?>/>
						</td>
						<td>
							<input type="checkbox" id="frc<?=$i;?>" onClick="document.location='services_ntpd_gps.php?act=toggle_no_select&amp;id=<?=$i;?>';" <?=($gps['noselect'] ?'checked':'')?>/>
						</td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit Device')?>"	href="services_ntpd_gps_edit.php?id=<?=$i?>"></a>
							<?php if ($i > 0): ?>
								<a class="fa fa-arrow-up"	title="<?=gettext('Move Up')?>"	href="services_ntpd_gps.php?act=swap&amp;id1=<?=$i-1;?>&amp;id2=<?=$i;?>"></a>
							<?php endif?>
							<?php if ($i < (count($a_gps) - 1)): ?>
								<a class="fa fa-arrow-down"	title="<?=gettext('Move Down')?>"	href="services_ntpd_gps.php?act=swap&amp;id1=<?=$i;?>&amp;id2=<?=$i+1;?>"></a>
							<?php endif?>
							<a class="fa fa-trash"	title="<?=gettext('Delete Device')?>" href="services_ntpd_gps.php?act=del&amp;id=<?=$i?>"></a>
						</td>
					</tr>
				<?php endforeach?>
			</tbody>
		</table>
	</div>
</div>

<?php if (count($a_gps) < 4): ?>
	<nav class="action-buttons">
		<a href="services_ntpd_gps_edit.php" class="btn btn-sm btn-success">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext('Add')?>
		</a>
	</nav>
<?php endif?>

<?php include("foot.inc");