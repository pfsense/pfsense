<?php
/*
 * system_information.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

require_once("functions.inc");
require_once("guiconfig.inc");
require_once('notices.inc');
require_once('system.inc');
include_once("includes/functions.inc.php");

$sysinfo_items = array(
	'name' => gettext('Name'),
	'system' => gettext('System'),
	'version' => gettext('Version'),
	'cpu_type' => gettext('CPU Type'),
	'hwcrypto' => gettext('Hardware Crypto'),
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
	'swap_usage' => gettext('Swap Usage'),
	'disk_usage' => gettext('Disk Usage')
	);

if ($_REQUEST['getupdatestatus']) {
	require_once("pkg-utils.inc");

	if (isset($config['system']['firmware']['disablecheck'])) {
		exit;
	}

	$system_version = get_system_pkg_version();

	if ($system_version === false) {
		print(gettext("<i>Unable to check for updates</i>"));
		exit;
	}

	if (!is_array($system_version) ||
	    !isset($system_version['version']) ||
	    !isset($system_version['installed_version'])) {
		print(gettext("<i>Error in version information</i>"));
		exit;
	}

	$version_compare = pkg_version_compare(
	    $system_version['installed_version'], $system_version['version']);

	switch ($version_compare) {
	case '<':
?>
		<div>
			<?=gettext("Version ")?>
			<span class="text-success"><?=$system_version['version']?></span> <?=gettext("is available.")?>
			<a class="fa fa-cloud-download fa-lg" href="/pkg_mgr_install.php?id=firmware"></a>
		</div>
<?php
		break;
	case '=':
		printf('<span class="text-success">%s</span>', gettext("The system is on the latest version."));
		break;
	case '>':
		print(gettext("The system is on a later version than<br />the official release."));
		break;
	default:
		print(gettext( "<i>Error comparing installed version<br />with latest available</i>"));
		break;
	}

	exit;
} elseif ($_POST['widgetkey']) {

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

/*   Adding one second to the system widet update period
 *   will ensure that we update the GUI right after the stats are updated.
 */
$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;
$widgetperiod += 1000;

$filesystems = get_mounted_filesystems();

$skipsysinfoitems = explode(",", $user_settings['widgets'][$widgetkey]['filter']);
$rows_displayed = false;
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
			<td><?php echo htmlspecialchars($config['system']['hostname'] . "." . $config['system']['domain']); ?></td>
		</tr>
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
			?>
			<br />
			<?=gettext("Serial: ");?><strong><?=system_get_serial();?></strong>
			</td>
		</tr>
<?php
	endif;
	if (!in_array('version', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("Version");?></th>
			<td>
				<strong><?=$g['product_version_string']?></strong>
				(<?php echo php_uname("m"); ?>)
				<br />
				<?=gettext('built on')?> <?php readfile("/etc/version.buildtime"); ?>
			<?php if (!$g['hideuname']): ?>
				<br />
				<span title="<?php echo php_uname("a"); ?>"><?php echo php_uname("s") . " " . php_uname("r"); ?></span>
			<?php endif; ?>
			<?php if (!isset($config['system']['firmware']['disablecheck'])): ?>
				<br /><br />
				<div id='updatestatus'><?php echo gettext("Obtaining update status "); ?><i class="fa fa-cog fa-spin"></i></div>
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
					<?= htmlspecialchars($cpucount) ?> <?=gettext('CPUs')?>: <?= htmlspecialchars(get_cpu_count(true)); ?>
				</div>
		<?php endif; ?>
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
			<td><?=htmlspecialchars($hwcrypto);?></td>
		</tr>
		<?php endif; ?>
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
					$dns_servers = get_dns_servers();
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
		<?php if ($config['revision']): ?>
		<tr>
			<th><?=gettext("Last config change");?></th>
			<td><?= htmlspecialchars(date("D M j G:i:s T Y", intval($config['revision']['time'])));?></td>
		</tr>
		<?php endif; ?>
<?php
	endif;
	if (!in_array('state_table_size', $skipsysinfoitems)):
		$rows_displayed = true;
?>
		<tr>
			<th><?=gettext("State table size");?></th>
			<td>
				<?php
					$pfstatetext = get_pfstate();
					$pfstateusage = get_pfstate(true);
				?>
				<div class="progress">
					<div id="statePB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$pfstateusage?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$pfstateusage?>%">
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
					$mbufstext = get_mbuf();
					$mbufusage = get_mbuf(true);
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
		<?php if (get_temp() != ""): ?>
		<tr>
			<th><?=gettext("Temperature");?></th>
			<td>
				<?php $temp_deg_c = get_temp(); ?>
				<div class="progress">
					<div id="tempPB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$temp_deg_c?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$temp_deg_c?>%">
					</div>
				</div>
				<span id="tempmeter"><?= $temp_deg_c . "&deg;C"; ?></span>
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
				<?php $update_period = (!empty($config['widgets']['period'])) ? $config['widgets']['period'] : "10"; ?>
				<span id="cpumeter"><?=sprintf(gettext("Updating in %s seconds"), $update_period)?></span>
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
				<span><?=$swapusage?>% of <?= sprintf("%.0f", `/usr/sbin/swapinfo -m | /usr/bin/grep -v Device | /usr/bin/awk '{ print $2;}'`) ?> MiB</span>
			</td>
		</tr>
		<?php endif; ?>

<?php
	endif;
	if (!in_array('disk_usage', $skipsysinfoitems)):
		$rows_displayed = true;
		$diskidx = 0;
		foreach ($filesystems as $fs):
?>
		<tr>
			<th><?=gettext("Disk usage");?>&nbsp;( <?=$fs['mountpoint']?> )</th>
			<td>
				<div class="progress" >
					<div id="diskspace<?=$diskidx?>" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$fs['percent_used']?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$fs['percent_used']?>%">
					</div>
				</div>
				<span><?=$fs['percent_used']?>%<?=gettext(" of ")?><?=$fs['total_size']?>iB - <?=$fs['type'] . ("md" == substr(basename($fs['device']), 0, 2) ? " " . gettext("in RAM") : "")?></span>
			</td>
		</tr>
<?php
			$diskidx++;
		endforeach;
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
			<input type="hidden" name="widgetkey" value="<?=$widgetkey; ?>">
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
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			<button id="<?=$widget_showallnone_id?>" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
		</div>
	</div>
</form>

<script type="text/javascript">
//<![CDATA[
<?php if ($widget_first_instance): ?>
<?php if (!isset($config['system']['firmware']['disablecheck'])): ?>
function systemStatusGetUpdateStatus() {
	$.ajax({
		type: 'get',
		url: '/widgets/widgets/system_information.widget.php',
		data: 'getupdatestatus=1',
		dataFilter: function(raw){
			// We reload the entire widget, strip this block of javascript from it
			return raw.replace(/<script>([\s\S]*)<\/script>/gi, '');
		},
		dataType: 'html',
		success: function(data){
			$('[id^=widget-system_information] #updatestatus').html(data);
		}
	});
}

setTimeout('systemStatusGetUpdateStatus()', 4000);
<?php endif; ?>

function updateMeters() {
	url = '/getstats.php';

	$.ajax(url, {
		type: 'get',
		success: function(data) {
			response = data || "";
			if (response != "")
				stats(data);
		}
	});

	setTimer();

}

var update_interval = "<?=$widgetperiod?>";

function setProgress(barName, percent) {
	$('[id="' + barName + '"]').css('width', percent + '%').attr('aria-valuenow', percent);
}

function setTimer() {
	timeout = window.setTimeout('updateMeters()', update_interval);
}

function stats(x) {
	var values = x.split("|");
	if ($.each(values,function(key,value) {
		if (value == 'undefined' || value == null)
			return true;
		else
			return false;
	}))

	updateUptime(values[2]);
	updateDateTime(values[5]);
	updateCPU(values[0]);
	updateMemory(values[1]);
	updateState(values[3]);
	updateTemp(values[4]);
	updateInterfaceStats(values[6]);
	updateInterfaces(values[7]);
	updateCpuFreq(values[8]);
	updateLoadAverage(values[9]);
	updateMbuf(values[10]);
	updateMbufMeter(values[11]);
	updateStateMeter(values[12]);
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

function updateCPU(x) {

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

function updateTemp(x) {
	if ($("#tempmeter")) {
		$('[id="tempmeter"]').html(x + '&deg;' + 'C');
	}
	if ($('#tempPB')) {
		setProgress('tempPB', parseInt(x));
	}
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

function updateInterfaceStats(x) {
	if (widgetActive("interface_statistics")) {
		statistics_split = x.split(",");
		var counter = 1;
		for (var y=0; y<statistics_split.length-1; y++) {
			if ($('#stat' + counter)) {
				$('[id="stat' + counter + '"]').html(statistics_split[y]);
				counter++;
			}
		}
	}
}

function updateInterfaces(x) {
	if (widgetActive("interfaces")) {
		interfaces_split = x.split("~");
		interfaces_split.each(function(iface){
			details = iface.split("^");
			if (details[2] == '') {
				ipv4_details = '';
			} else {
				ipv4_details = details[2] + '<br />';
			}
			switch (details[1]) {
				case "up":
					$('[id="' + details[0] + '-up"]').css("display","inline");
					$('[id="' + details[0] + '-down"]').css("display","none");
					$('[id="' + details[0] + '-block"]').css("display","none");
					$('[id="' + details[0] + '-ip"]').html(ipv4_details);
					$('[id="' + details[0] + '-ipv6"]').html(details[3]);
					$('[id="' + details[0] + '-media"]').html(details[4]);
					break;
				case "down":
					$('[id="' + details[0] + '-down"]').css("display","inline");
					$('[id="' + details[0] + '-up"]').css("display","none");
					$('[id="' + details[0] + '-block"]').css("display","none");
					$('[id="' + details[0] + '-ip"]').html(ipv4_details);
					$('[id="' + details[0] + '-ipv6"]').html(details[3]);
					$('[id="' + details[0] + '-media"]').html(details[4]);
					break;
				case "block":
					$('[id="' + details[0] + '-block"]').css("display","inline");
					$('[id="' + details[0] + '-down"]').css("display","none");
					$('[id="' + details[0] + '-up"]').css("display","none");
					break;
			}
		});
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

/* start updater */
events.push(function(){
	setTimer();
});
<?php endif; // $widget_first_instance ?>
events.push(function(){
	set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=show]", "<?=$widget_showallnone_id?>");
});
//]]>
</script>
