<?php
/*
 * system_information.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2007 Scott Dale
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once('notices.inc');
require_once('system.inc');

/*
 * Validate the "widgetkey" value.
 * When this widget is present on the Dashboard, $widgetkey is defined before
 * the Dashboard includes the widget. During other types of requests, such as
 * saving settings or AJAX, the value may be set via $_POST or similar.
 */
if ($_POST['widgetkey'] || $_GET['widgetkey']) {
	$rwidgetkey = isset($_POST['widgetkey']) ? $_POST['widgetkey'] : (isset($_GET['widgetkey']) ? $_GET['widgetkey'] : null);
	if (is_valid_widgetkey($rwidgetkey, $user_settings, __FILE__)) {
		$widgetkey = $rwidgetkey;
	} else {
		print gettext("Invalid Widget Key");
		exit;
	}
}

$sysinfo_items = array(
	'name' => gettext('Name'),
	'user' => gettext('User'),
	'system' => gettext('System'),
	'bios' => gettext('BIOS'),
	'version' => gettext('Version'),
	'cpu_type' => gettext('CPU Type'),
	'hwcrypto' => gettext('Hardware Crypto'),
	'pti' => gettext('Kernel PTI'),
	'mds' => gettext('MDS Mitigation'),
	'uptime' => gettext('Uptime'),
	'current_datetime' => gettext('Current Date/Time'),
	'dns_servers' => gettext('DNS Server(s)'),
	'last_config_change' => gettext('Last Config Change'),
	'state_table_size' => gettext('State Table Size'),
	'mbuf_usage' => gettext('MBUF Usage'),
	'temperature' => gettext('Temperature'),
	'load_average' => gettext('Load Average'),
	'cpu_usage' => gettext('CPU Usage'),
	'memory_usage' => gettext('Memory Usage'),
	'swap_usage' => gettext('Swap Usage')
	);

// Declared here so that JavaScript can access it
$updtext = sprintf(gettext("Obtaining update status %s"), "<i class='fa-solid fa-rotate fa-spin'></i>");
$state_tt = gettext("Adaptive state handling is enabled, state timeouts are reduced to ");

if ($_REQUEST['getupdatestatus']) {
	require_once("pkg-utils.inc");

	$cache_file = g_get('version_cache_file');

	if (config_path_enabled('system/firmware', 'disablecheck')) {
		exit;
	}

	/* If $_REQUEST['getupdatestatus'] == 2, force update */
	$system_version = get_system_pkg_version(false,
		($_REQUEST['getupdatestatus'] == 1),
		false, /* get upgrades from other repos */
		true /* see https://redmine.pfsense.org/issues/15055 */
	);

	unset($error);
	if ($system_version === false || !is_array($system_version)) {
		$error = gettext("<i>Unable to check for updates</i>");
	}
	if (isset($system_version['pkg_busy']) ||
	    isset($system_version['pkg_version_error'])) {
		$error = gettext("<i>Update system is busy, try again later</i>");
	}
	if (!isset($system_version['version']) ||
	    !isset($system_version['installed_version'])) {
		$error = gettext("<i>Error in version information</i>");
	}
	if (isset($error)) {
		print($error);
?>
		    &nbsp;
		    <a id="updver" href="#" class="fa-solid fa-arrows-rotate"></a>
<?php
		exit;
	}

	switch ($system_version['pkg_version_compare']) {
	case '<':
?>
		<div>
			<?=gettext("Version ")?>
			<span class="text-success"><?=$system_version['version']?></span> <?=gettext("is available.")?>
			<a class="fa-solid fa-cloud-arrow-down fa-lg" href="/pkg_mgr_install.php?id=firmware"></a>
		</div>
<?php
		break;
	case '=':
		printf('<span class="text-success">%s</span>' . "\n",
		    gettext("The system is on the latest version."));
		break;
	case '>':
		printf("%s\n", gettext(
		    "The system is on a later version than official release."));
		break;
	default:
		printf("<i>%s</i>\n", gettext(
		    "Error comparing installed with latest version available"));
		break;
	}

	if (file_exists($cache_file)):
?>
	<div>
		<?printf("%s %s", gettext("Version information updated at"),
		    date("D M j G:i:s T Y", filemtime($cache_file)));?>
		    &nbsp;
		    <a id="updver" href="#" class="fa-solid fa-rotate"></a>
	</div>
<?php
	endif;

	exit;
} elseif ($_POST['widgetkey']) {
	set_customwidgettitle($user_settings);

	$validNames = array();

	foreach ($sysinfo_items as $sysinfo_item_key => $sysinfo_item_name) {
		array_push($validNames, $sysinfo_item_key);
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', $validNames);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved System Information Widget Filter via Dashboard."));
	header("Location: /index.php");
}

$hwcrypto = get_cpu_crypto_support();

$skipsysinfoitems = explode(",", $user_settings['widgets'][$widgetkey]['filter']);

$rows_displayed = false;
// use the preference of the first thermal sensor widget, if it's available (false == empty)
$temp_use_f = (isset($user_settings['widgets']['thermal_sensors-0']) && !empty($user_settings['widgets']['thermal_sensors-0']['thermal_sensors_widget_show_fahrenheit']));
?>

<div class="table-responsive">
<table class="table table-hover table-striped table-condensed">
	<tbody>
<?php
	if (!in_array('name', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("Name");?></th>
			<td><?php echo htmlspecialchars(config_get_path('system/hostname') . "." . config_get_path('system/domain')); ?></td>
		</tr>
<?php
	endif;
	if (!in_array('user', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("User");?></th>
			<td><?php echo htmlspecialchars(get_config_user()); ?></td>
<?php
	endif;
	if (!in_array('system', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("System");?></th>
			<td>
<?php
				$platform = system_identify_specific_platform();
				if (isset($platform['descr'])) {
					echo $platform['descr'];
				} else {
					echo gettext('Unknown system');
				}

				$serial = system_get_serial();
				if (!empty($serial)) {
					print("<br />" . gettext("Serial:") .
					    " <strong>{$serial}</strong>\n");
				}

				// If the uniqueID is available, display it here
				$uniqueid = system_get_uniqueid();
				if (!empty($uniqueid)) {
					print("<br />" .
					    gettext("Netgate Device ID:") .
					    " <strong>{$uniqueid}</strong>");
				}
?>
			</td>
		</tr>
<?php
	endif;
	if (!in_array('bios', $skipsysinfoitems)):
		$rows_displayed = true;
		unset($biosvendor);
		unset($biosversion);
		unset($biosdate);
		unset($bootmethod);
		$_gb = exec('/bin/kenv -q smbios.bios.vendor 2>/dev/null', $biosvendor);
		$_gb = exec('/bin/kenv -q smbios.bios.version 2>/dev/null', $biosversion);
		$_gb = exec('/bin/kenv -q smbios.bios.reldate 2>/dev/null', $biosdate);
		$bootmethod = get_single_sysctl("machdep.bootmethod");
		/* Only display BIOS information if there is any to show. */
		if (!empty($biosvendor[0]) || !empty($biosversion[0]) || !empty($biosdate[0])):
?>
		<tr>
			<th><?=gettext("BIOS");?></th>
			<td>
			<?php if (!empty($biosvendor[0])): ?>
				<?=gettext("Vendor: ");?><strong><?=$biosvendor[0];?></strong><br/>
			<?php endif; ?>
			<?php if (!empty($biosversion[0])): ?>
				<?=gettext("Version: ");?><strong><?=$biosversion[0];?></strong><br/>
			<?php endif; ?>
			<?php if (!empty($biosdate[0])): ?>
				<?=gettext("Release Date: ");?><strong><?= date("D M j Y ",strtotime($biosdate[0]));?></strong><br/>
			<?php endif; ?>
			<?php if (!empty($bootmethod)): ?>
				<?=gettext("Boot Method: ");?><strong><?= htmlspecialchars($bootmethod) ?></strong><br/>
			<?php endif; ?>
			</td>
		</tr>
<?php
		endif;
	endif;
	if (!in_array('version', $skipsysinfoitems)):
		$rows_displayed = true;

		try {
			/* Try to get build time from file */
			if (file_exists("/etc/version.buildtime") &&
			    (filesize("/etc/version.buildtime") > 0)) {
				$buildtime = file_get_contents("/etc/version.buildtime");
			} else {
				/* Fall back to getting build timestamp from pkg-static */
				$buildtime = exec("/usr/local/sbin/pkg-static info -A " . g_get('product_name') . " | /usr/bin/awk '/build_timestamp/ {print \$2;}'");
			}

			/* Standardize timestamp format */
			if (!empty($buildtime)) {
				$buildtime = date("D M j G:i:s T Y", strtotime($buildtime));
			} else {
				$buildtime = gettext('Unknown');
			}
		} catch (Exception $e) {
			/* In case any of the above methods fail badly, print a default message. */
			$buildtime = gettext('Unknown');
		}
?>
		<tr>
			<th><?=gettext("Version");?></th>
			<td>
				<strong><?=g_get('product_version_string')?></strong>
				(<?php echo php_uname("m"); ?>)
				<br />
				<?=gettext('built on')?> <?= $buildtime ?>
			<?php if (!g_get('hideuname')): ?>
				<br />
				<span title="<?php echo php_uname("a"); ?>"><?php echo php_uname("s") . " " . php_uname("r"); ?></span>
			<?php endif; ?>
			<?php if (!config_path_enabled('system/firmware', 'disablecheck')): ?>
				<br /><br />
				<div id='updatestatus'><?=$updtext?></div>
			<?php endif; ?>
			</td>
		</tr>
<?php
	endif;
	if (!in_array('cpu_type', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("CPU Type");?></th>
			<td><?=htmlspecialchars(get_single_sysctl("hw.model"))?>
				<div id="cpufreq"><?= get_cpufreq(); ?></div>
		<?php
			$cpucount = get_cpu_count();
			if ($cpucount > 1): ?>
				<div id="cpucount">
					<?= htmlspecialchars($cpucount) ?> <?=gettext('CPUs')?>
<?php
				$cpudetail = get_cpu_count(true);
				if ($cpudetail != $cpucount): ?>
					: <?= htmlspecialchars($cpudetail); ?>
<?php
				endif; ?>
				</div>
		<?php endif; ?>
				<div id="cpucrypto">
					<?= get_cpu_crypto_string($hwcrypto); ?>
				</div>
			</td>
		</tr>
<?php
	endif;
	if (!in_array('hwcrypto', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<?php if ($hwcrypto): ?>
		<tr>
			<th><?=gettext("Hardware crypto");?></th>
			<td><?=htmlspecialchars(crypto_accel_get_algs($hwcrypto));?></td>
		</tr>
		<?php endif; ?>
<?php
	endif;
	$pti = get_single_sysctl('vm.pmap.pti');
	if ((strlen($pti) > 0) && !in_array('pti', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("Kernel PTI");?></th>
			<td><?=($pti == 0) ? gettext("Disabled") : gettext("Enabled");?></td>
		</tr>
<?php
	endif;
	$mds = get_single_sysctl('hw.mds_disable_state');
	if ((strlen($mds) > 0) && !in_array('mds', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("MDS Mitigation");?></th>
			<td><?=ucwords(htmlspecialchars($mds));?></td>
		</tr>
<?php
	endif;
	if (!in_array('uptime', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("Uptime");?></th>
			<td id="uptime"><?= htmlspecialchars(get_uptime()); ?></td>
		</tr>
<?php
	endif;
	if (!in_array('current_datetime', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("Current date/time");?></th>
			<td><div id="datetime"><?= date("D M j G:i:s T Y"); ?></div></td>
		</tr>
<?php
	endif;
	if (!in_array('dns_servers', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("DNS server(s)");?></th>
			<td>
				<ul style="margin-bottom:0px">
				<?php
					$dns_servers = get_dns_nameservers(false, true);
					foreach ($dns_servers as $dns) {
						echo "<li>{$dns}</li>";
					}
				?>
				</ul>
			</td>
		</tr>
<?php
	endif;
	if (!in_array('last_config_change', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<?php if (config_get_path('revision')): ?>
		<tr>
			<th><?=gettext("Last config change");?></th>
			<td><?= htmlspecialchars(date("D M j G:i:s T Y", intval(config_get_path('revision/time'))));?></td>
		</tr>
		<?php endif; ?>
<?php
	endif;


	if (!in_array('state_table_size', $skipsysinfoitems)):
		$rows_displayed = true;

		$pfstatetext = get_pfstate();
		$pfstateusage = get_pfstate(true);

		// Calculate scaling factor
		$adaptive = false;
		$maxstates = (config_get_path('system/maximumstates', 0) > 0) ? config_get_path('system/maximumstates') : pfsense_default_state_size();
		$adaptivestart = (config_get_path('system/adaptivestart', 0) > 0) ? config_get_path('system/adaptivestart') : intval($maxstates * 0.6);
		$adaptiveend = (config_get_path('system/adaptiveend', 0) > 0) ? config_get_path('system/adaptiveend') : intval($maxstates * 1.2);
		$adaptive_text = "";

		if ($pfstatetext > $adaptivestart) {
		    $scalingfactor = round(($adaptiveend - $pfstatetext) / ($adaptiveend - $adaptivestart) * 100, 0);
		    $adaptive = true;
		}
?>
		<tr>
			<th>
<?php
				print(gettext("State table size"));
				// If adaptive state handling is enabled, display the % and provide a tooltip with more details
				print('<span id="scaledstates"><br /><a href="#" data-toggle="tooltip" title="" data-placement="right" data-original-title="' .
					$state_tt . $scalingfactor . '%">' .
					gettext("Scaling ") . $scalingfactor . '%</a></span>');

?>
			</th>
			<td>
				<!-- The color of the progress bar is changed to 'warning' to indicate adaptive state handling is in use -->
				<div class="progress">
					<div id="statePB" class="progress-bar progress-bar-striped <?=$adaptive ? 'progress-bar-warning' : ''?>" role="progressbar" aria-valuenow="<?=$pfstateusage?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$pfstateusage?>%">
					</div>
				</div>

				<span id="pfstateusagemeter"><?=$pfstateusage?>%</span>&nbsp;<span id="pfstate">(<?= htmlspecialchars($pfstatetext)?>)</span>&nbsp;<span><a href="diag_dump_states.php"><?=gettext("Show states");?></a></span>
			</td>
		</tr>
<?php
	endif;
	if (!in_array('mbuf_usage', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("MBUF Usage");?></th>
			<td>
				<?php
					get_mbuf($mbufstext, $mbufusage);
				?>
				<div class="progress">
					<div id="mbufPB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$mbufusage?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$mbufusage?>%">
					</div>
				</div>
				<span id="mbufusagemeter"><?=$mbufusage?>%</span>&nbsp;<span id="mbuf">(<?= htmlspecialchars($mbufstext)?>)</span>
			</td>
		</tr>
<?php
	endif;
	if (!in_array('temperature', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<?php if ($temp_deg_c = get_temp()): ?>
		<tr>
			<th><?=gettext("Temperature");?></th>
			<td>
				<?php $display_temp = ($temp_use_f) ? $temp_deg_c * 1.8 + 32 : $temp_deg_c; ?>
				<div class="progress">
					<div id="tempPB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$display_temp?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$temp_use_f ? $temp_deg_c * .212 : $temp_deg_c?>%">
					</div>
				</div>
				<span id="tempmeter" data-units="<?=$temp_use_f ? 'F' : 'C';?>"><?=$display_temp?></span>&deg;<?=$temp_use_f ? 'F' : 'C';?>
			</td>
		</tr>
		<?php endif; ?>
<?php
	endif;
	if (!in_array('load_average', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("Load average");?></th>
			<td>
				<div id="load_average" title="<?=gettext('Last 1, 5 and 15 minutes')?>"><?= get_load_average(); ?></div>
			</td>
		</tr>
<?php
	endif;
	if (!in_array('cpu_usage', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("CPU usage");?></th>
			<td>
				<div class="progress">
					<div id="cpuPB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
					</div>
				</div>
				<span id="cpumeter"><?=sprintf(gettext("Retrieving CPU data %s"), "<i class=\"fa-solid fa-gear fa-spin\"></i>")?></span>
			</td>
		</tr>
<?php
	endif;
	if (!in_array('memory_usage', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("Memory usage");?></th>
			<td>
				<?php $memUsage = mem_usage(); ?>

				<div class="progress" >
					<div id="memUsagePB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$memUsage?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$memUsage?>%">
					</div>
				</div>
				<span id="memusagemeter"><?=$memUsage?></span><span>% of <?= sprintf("%.0f", get_single_sysctl('hw.physmem') / (1024*1024)) ?> MiB</span>
			</td>
		</tr>
<?php
	endif;
	if (!in_array('swap_usage', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<?php if ($showswap == true): ?>
		<tr>
			<th><?=gettext("SWAP usage");?></th>
			<td>
				<?php $swapusage = swap_usage(); ?>
				<div class="progress">
					<div class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$swapusage?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$swapusage?>%">
					</div>
				</div>
				<span><?=$swapusage?>% of <?= sprintf("%.0f", `/usr/sbin/swapinfo -m | /usr/bin/tail -1 | /usr/bin/awk '{ print $2;}'`) ?> MiB</span>
			</td>
		</tr>
		<?php endif; ?>

<?php
	endif;
	if (!$rows_displayed):
?>
		<tr>
			<td class="text-center">
				<?=gettext('All System Information items are hidden.');?>
			</td>
		</tr>
<?php
	endif;
?>

	</tbody>
</table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<form action="/widgets/widgets/system_information.widget.php" method="post" class="form-horizontal">
    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
			<div class="table responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Item")?></th>
							<th><?=gettext("Show")?></th>
						</tr>
					</thead>
					<tbody>
<?php
				foreach ($sysinfo_items as $sysinfo_item_key => $sysinfo_item_name):
?>
						<tr>
							<td><?=gettext($sysinfo_item_name)?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$sysinfo_item_key?>" type="checkbox" <?=(!in_array($sysinfo_item_key, $skipsysinfoitems) ? 'checked':'')?>></td>
						</tr>
<?php
				endforeach;
?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa-solid fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			<button id="<?=$widget_showallnone_id?>" type="button" class="btn btn-info"><i class="fa-solid fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
		</div>
	</div>
</form>

<script type="text/javascript">
//<![CDATA[
<?php if ($widget_first_instance): ?>

var lastTotal = 0;
var lastUsed = 0;

// Collect some PHP values required by the states calculation
<?php if (!in_array('state_table_size', $skipsysinfoitems)): ?>
var adaptiveend = <?=$adaptiveend?>;
var adaptivestart = <?=$adaptivestart?>;
var maxstates = <?=$maxstates?>;
var state_tt = "<?=$state_tt?>";
<?php else: ?>
var adaptiveend = 0;
var adaptivestart = 0;
var maxstates = 0;
var state_tt = "";
<?php endif; ?>

function setProgress(barName, percent) {
	$('[id="' + barName + '"]').css('width', percent + '%').attr('aria-valuenow', percent);
}

function stats(x) {
	var values = x.split("|");
	if ($.each(values,function(key,value) {
		if (value == 'undefined' || value == null)
			return true;
		else
			return false;
	}))

	if (lastTotal === 0) {
		lastTotal = values[0];
		lastUsed = values[1];
	} else {
		updateCPU(values[0], values[1]);
	}

	updateUptime(values[3]);
	updateDateTime(values[6]);
	updateMemory(values[2]);
	updateState(values[4]);
	updateTemp(values[5]);
	updateCpuFreq(values[7]);
	updateLoadAverage(values[8]);
	updateMbuf(values[9]);
	updateMbufMeter(values[10]);
	updateStateMeter(values[11]);
}

function updateMemory(x) {
	if ($('#memusagemeter')) {
		$('[id="memusagemeter"]').html(x);
	}
	if ($('#memUsagePB')) {
		setProgress('memUsagePB', parseInt(x));
	}
}

function updateMbuf(x) {
	if ($('#mbuf')) {
		$('[id="mbuf"]').html('(' + x + ')');
	}
}

function updateMbufMeter(x) {
	if ($('#mbufusagemeter')) {
		$('[id="mbufusagemeter"]').html(x + '%');
	}
	if ($('#mbufPB')) {
		setProgress('mbufPB', parseInt(x));
	}
}

function updateCPU(total, used) {
	if ((lastTotal <= total) && (lastUsed <= used)) { // Just in case it wraps
		// Calculate the total ticks and the used ticks since the last time it was checked
		var d_total = total - lastTotal;
		var d_used = used - lastUsed;

		// Convert to percent
		var x = Math.floor(((d_total - d_used)/d_total) * 100);

		if ($('#cpumeter')) {
			$('[id="cpumeter"]').html(x + '%');
		}

		if ($('#cpuPB')) {
			setProgress('cpuPB', parseInt(x));
		}

		/* Load CPU Graph widget if enabled */
		if (widgetActive('cpu_graphs')) {
			GraphValue(graph[0], x);
		}
	}

	// Update the saved "last" values
	lastTotal = total;
	lastUsed = used;
}

function updateTemp(x) {
	$("#tempmeter").html(function() {
		return this.dataset.units === "F" ? parseInt(x * 1.8 + 32, 10) : x;
	});
	setProgress('tempPB', parseInt(x));
}

function updateDateTime(x) {
	if ($('#datetime')) {
		$('[id="datetime"]').html(x);
	}
}

function updateUptime(x) {
	if ($('#uptime')) {
		$('[id="uptime"]').html(x);
	}
}

function updateState(x) {
	if ($('#pfstate')) {
		$('[id="pfstate"]').html('(' + x + ')');

		// get numeric part of string before the '/'
		x = x.split('/')[0]

		if (x > adaptivestart) {
			var scalingfactor = Math.round((adaptiveend - x) / (adaptiveend - adaptivestart) * 100);
			var disphtml = 	'<br /><a href="#" data-toggle="tooltip" title="" data-placement="right" data-original-title="' +
				state_tt +  scalingfactor + '%">' +
				'Scaling ' + scalingfactor + '%</a>';

			// Only update the display if the tooltip is not visible. Otherwise the tip will go away
			if ($('.tooltip').length == 0) {
				$('#scaledstates').html(disphtml);
			}

			// Renable the tooltip
			$(function () {
				$('[data-toggle="tooltip"]').tooltip()
			})

			$('#statePB').addClass('progress-bar-warning');
		} else {
			$('#scaledstates').html('');
			$('#statePB').removeClass('progress-bar-warning');
		}
	}
}

function updateStateMeter(x) {
	if ($('#pfstateusagemeter')) {
		$('[id="pfstateusagemeter"]').html(x + '%');
	}
	if ($('#statePB')) {
		setProgress('statePB', parseInt(x));
	}
}

function updateCpuFreq(x) {
	if ($('#cpufreq')) {
		$('[id="cpufreq"]').html(x);
	}
}

function updateLoadAverage(x) {
	if ($('#load_average')) {
		$('[id="load_average"]').html(x);
	}
}

function widgetActive(x) {
	var widget = $('#' + x + '-container');
	if ((widget != null) && (widget.css('display') != null) && (widget.css('display') != "none")) {
		return true;
	} else {
		return false;
	}
}


<?php endif; // $widget_first_instance ?>

events.push(function() {

	$('#scaledstates').html('');

	// Enable tooltips
	$(function () {
		$('[data-toggle="tooltip"]').tooltip()
	})

	// --------------------- Centralized widget refresh system ------------------------------

	// Callback function called by refresh system when data is retrieved
	function meters_callback(s) {
		stats(s);
	}

	// POST data to send via AJAX
	var postdata = {
		ajax: "ajax",
		skipitems: <?=json_encode($skipsysinfoitems)?>
	 };

	// Create an object defining the widget refresh AJAX call
	var metersObject = new Object();
	metersObject.name = "Meters";
	metersObject.url = "/getstats.php";
	metersObject.callback = meters_callback;
	metersObject.parms = postdata;
	metersObject.freq = 5;

	// Register the AJAX object
	register_ajax(metersObject);

<?php if (!config_path_enabled('system/firmware', 'disablecheck')): ?>

	// Callback function called by refresh system when data is retrieved
	function version_callback(s) {
		$('[id^=widget-system_information] #updatestatus').html(s);

		// The click handler has to be attached after the div is updated
		$('#updver').click(function() {
			updver_ajax();
		});
	}

	// POST data to send via AJAX
	var postdata = {
		ajax: "ajax",
		getupdatestatus: "1",
		skipitems: <?=json_encode($skipsysinfoitems)?>
	 };

	// Create an object defining the widget refresh AJAX call
	var versionObject = new Object();
	versionObject.name = "Version";
	versionObject.url = "/widgets/widgets/system_information.widget.php";
	versionObject.callback = version_callback;
	versionObject.parms = postdata;
	versionObject.freq = 100;

	//Register the AJAX object
	register_ajax(versionObject);
<?php endif; ?>
	// ---------------------------------------------------------------------------------------------------

	set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=show]", "<?=$widget_showallnone_id?>");

	// AJAX function to update the version display with non-cached data
	function updver_ajax() {

		// Display the "updating" message
		$('[id^=widget-system_information] #updatestatus').html("<?=$updtext?>"); // <?=$updtext?>");

		$.ajax({
			type: 'POST',
			url: "/widgets/widgets/system_information.widget.php",
			dataType: 'html',
			data: {
				ajax: "ajax",
				getupdatestatus: "2",
				skipitems: <?=json_encode($skipsysinfoitems)?>
			},

			success: function(data){
				// Display the returned data
				$('[id^=widget-system_information] #updatestatus').html(data);

				// Re-attach the click handler (The binding was lost when the <div> content was replaced)
				$('#updver').click(function() {
					updver_ajax();
				});
			},

			error: function(e){
			}
		});
	}
});
//]]>
</script>
