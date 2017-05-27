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

$sysinfo_categories = array(
	'router_firmware' => array('cattitle' => gettext('Router'), 'catsort' => 1),
	'physical_platform' => array('cattitle' => gettext('Hardware Platform'), 'catsort' => 2),
	'router_status' => array('cattitle' => gettext('System Status'), 'catsort' => 3),
	'security' => array('cattitle' => gettext('Security'), 'catsort' => 4),
	'resource_usage' => array('cattitle' => gettext('Resource Usage'), 'catsort' => 5)
	);

// Within categories, items are sorted by their sort #
$sysinfo_items = array(
	'name' => array('itemtitle' => gettext('Name'), 'category' => 'router_firmware', 'itemsort' => 1),
	'system' => array('itemtitle' => gettext('System'), 'category' => 'router_firmware', 'itemsort' => 2),
	'firmware' => array('itemtitle' => gettext('Firmware'), 'category' => 'router_firmware', 'itemsort' => 3),
	'identifiers' => array('itemtitle' => gettext('Install IDs'), 'category' => 'router_firmware', 'itemsort' => 4),
	'platform' => array('itemtitle' => gettext('Platform'), 'category' => 'physical_platform', 'itemsort' => 5),
	'baseboard' => array('itemtitle' => gettext('Baseboard'), 'category' => 'physical_platform', 'itemsort' => 6),
	'cpu_type' => array('itemtitle' => gettext('CPU Type'), 'category' => 'physical_platform', 'itemsort' => 8),
	'hwcrypto' => array('itemtitle' => gettext('Hardware Crypto'), 'category' => 'physical_platform', 'itemsort' => 9),
	'uptime' => array('itemtitle' => gettext('Uptime'), 'category' => 'router_status', 'itemsort' => 10),
	'current_datetime' => array('itemtitle' => gettext('Current Date/Time'), 'category' => 'router_status', 'itemsort' => 11),
	'dns_servers' => array('itemtitle' => gettext('DNS Server(s)'), 'category' => 'router_status', 'itemsort' => 12),
	'last_config_change' => array('itemtitle' => gettext('Last Config Change'), 'category' => 'router_status', 'itemsort' => 13),
	'state_table_size' => array('itemtitle' => gettext('State Table Size'), 'category' => 'resource_usage', 'itemsort' => 14),
	'mbuf_usage' => array('itemtitle' => gettext('MBUF Usage'), 'category' => 'resource_usage', 'itemsort' => 15),
	'temperature' => array('itemtitle' => gettext('Temperature'), 'category' => 'resource_usage', 'itemsort' => 16),
	'load_average' => array('itemtitle' => gettext('Load Average'), 'category' => 'resource_usage', 'itemsort' => 17),
	'cpu_usage' => array('itemtitle' => gettext('CPU Usage'), 'category' => 'resource_usage', 'itemsort' => 18),
	'memory_usage' => array('itemtitle' => gettext('Memory Usage'), 'category' => 'resource_usage', 'itemsort' => 19),
	'swap_usage' => array('itemtitle' => gettext('Swap Usage'), 'category' => 'resource_usage', 'itemsort' => 20),
	'disk_usage' => array('itemtitle' => gettext('Disk Usage'), 'category' => 'resource_usage', 'itemsort' => 21),
	'admin_access_methods' => array('itemtitle' => gettext('Admin Lockdown'), 'category' => 'security', 'itemsort' => 22),
	'admin_users' => array('itemtitle' => gettext('Users with Admin Access'), 'category' => 'security', 'itemsort' => 23)
	);

$validNames = array_keys($sysinfo_items);


// get HTML for a sysinfo item


function get_item_html($itemkey) {
	global $config, $g, $sysinfo_items;

	$title_content = $sysinfo_items[$itemkey]['itemtitle']; // correct in almost all cases, override if not
	$data_template = '';
	$args = array();

	switch ($itemkey) {

		case 'name':
			$data_template = "<strong>%s</strong>";
			$args[] = htmlspecialchars($config['system']['hostname'] . "." . $config['system']['domain']);
			break;


		case 'identifiers':
			$data_template = "%s%s";
			$args[] = sprintf('%s: <strong>%s</strong>', gettext("Serial"), system_get_serial());
			$idfile = "/var/db/uniqueid";
			$args[] = (file_exists($idfile) ? $args[] = sprintf("<br />\n%s: <strong>%s</strong>", gettext("Netgate Unique ID"), file_get_contents($idfile)) : "");
			break;


		case 'baseboard':
			$_gb = exec('/bin/kenv -q smbios.bios.vendor 2>/dev/null', $biosvendor);
			$_gb = exec('/bin/kenv -q smbios.bios.version 2>/dev/null', $biosversion);
			$_gb = exec('/bin/kenv -q smbios.bios.reldate 2>/dev/null', $biosdate);
			$_gb = exec('/bin/kenv -q smbios.planar.maker 2>/dev/null', $boardmaker);
			$_gb = exec('/bin/kenv -q smbios.planar.product 2>/dev/null', $boardmodel);
			$_gb = exec('/bin/kenv -q smbios.planar.version 2>/dev/null', $boardversion);
			$_gb = exec('/bin/kenv -q smbios.planar.serial 2>/dev/null', $boardserial);

			/* Only display information if there is any to show. */
			if (strlen($boardmaker[0] . $boardmodel[0] . $boardserial[0]) != 0) {
				$data_template = '%s<br/>%s%s';
				$args[] = gettext('Vendor') . ': <strong>' . (empty($boardmaker[0]) ? gettext("Unknown") : $boardmaker[0]) . "</strong>";
				$args[] = gettext('Model') . ': <strong>' . (empty($boardmodel[0]) ? gettext("Unknown") : $boardmodel[0] . (!empty($boardversion[0]) ? " ({$boardversion[0]})" : '')) . "</strong>";
				$args[] = (empty($boardserial[0]) ? '' : '<br/>' . gettext('Serial') . ': <strong>' . $boardserial[0]  . "</strong>");
			}
			if (!empty($biosvendor[0]) || !empty($biosversion[0]) || !empty($biosdate[0])) {
				$data_template .= '<br/>' . gettext('BIOS') . ":<div style='margin-left:20px'>%s<br/>\n%s</div>";
				$args[] = gettext('Vendor') . ': <strong>' . (!empty($biosvendor[0]) ? "{$biosvendor[0]}" : "Unknown") . "</strong>";
				$args[] = gettext('Version') . ': <strong>' . (!empty($biosversion[0]) ? $biosversion[0] : "Unknown") . (!empty($biosdate[0]) ? " ({$biosdate[0]})" : "") . "</strong>";
			}
			//  else nothing shown
			break;


		case 'firmware':
			$data_template = "%s<br/><strong>%s</strong> (%s)<br />\n%s\n%s\n%s";
			$args[] = $g['product_name'];
			$args[] = $g['product_version_string'];
			$args[] = php_uname("m");
			$args[] = gettext('built on') . ' '. file_get_contents("/etc/version.buildtime");
			$args[] = (!$g['hideuname'] ? '<br /><span title="' . php_uname("a") . '">(' . php_uname("s") . ' ' . php_uname("r") . ")</span>" : "");
			$args[] = (!isset($config['system']['firmware']['disablecheck']) ? "<br /><br /><div id='system-information-widget-updatestatus'>" . gettext("Obtaining update status ") . '<i class="fa fa-cog fa-spin"></i></div>' : "");
			break;


		case 'platform':
			if (!$g['hideplatform']) {
				$is_nano = ($g['platform'] == "nanobsd");
				// there's extra info (boot slice) if it's nanobsd
				$data_template = "%s" . ($is_nano ? "\n<br/>nanobsd: <strong>%s</strong>:<br/>\n%s\n%s\n%s" : '');
				$platform = system_identify_specific_platform();
				$args[] = htmlspecialchars($platform['descr']) . (($g['platform'] == "nanobsd" && file_exists("/etc/nanosize.txt")) ? " (" . htmlspecialchars(trim(file_get_contents("/etc/nanosize.txt"))) . ")" : "");
				if ($is_nano) {
					global $SLICE, $OLDSLICE, $TOFLASH, $COMPLETE_PATH, $COMPLETE_BOOT_PATH;
					global $GLABEL_SLICE, $UFS_ID, $OLD_UFS_ID, $BOOTFLASH;
					global $BOOT_DEVICE, $REAL_BOOT_DEVICE, $BOOT_DRIVE, $ACTIVE_SLICE;
					nanobsd_detect_slice_info();
					$rw = is_writable("/") ? "(rw)" : "(ro)";
					$args[] = gettext("NanoBSD Boot Slice");
					$args[] = htmlspecialchars(nanobsd_friendly_slice_name($BOOT_DEVICE)) . ' / ' . htmlspecialchars($BOOTFLASH) . $rw;
					$args[] = ($BOOTFLASH != $ACTIVE_SLICE ? "<br /><br />" . gettext('Next Boot') . ":<br />" . htmlspecialchars(nanobsd_friendly_slice_name($GLABEL_SLICE)) . ' / ' . htmlspecialchars($ACTIVE_SLICE) : "");
				}
			}
			break;


		case 'cpu_type':
			$data_template = "%s\n<div id='cpufreq'>%s</div>\n%s";
			$args[] = htmlspecialchars(get_single_sysctl("hw.model"));
			$args[] = get_cpufreq();
			$cpucount = get_cpu_count();
			$args[] = ($cpucount > 1 ? '<div id="cpucount">' . htmlspecialchars($cpucount) . ' ' . gettext('CPUs') . ': ' . htmlspecialchars(get_cpu_count(true)) . '</div>' : "");
			break;


		case 'hwcrypto':
			$data_template ="%s";
			$args[] = htmlspecialchars($hwcrypto);
			break;


		case 'uptime':
			$data_template = "<span id='uptime'>%s</span>";
			$args[] = htmlspecialchars(get_uptime());
			break;


		case 'current_datetime':
			$data_template = "<div id='datetime'>%s</div>";
			$args[] = date("D M j G:i:s T Y");
			break;


		case 'dns_servers':
			$data_template ="<ul style='margin-bottom:0px'>\n%s\n</ul>";
			$svr_list_html = '';
			foreach (get_dns_servers() as $dns) {
				$svr_list_html .= "\t\t\t\t<li>{$dns}</li>\n";
			}
			$args[] = $svr_list_html;
			break;


		case 'last_config_change':
			if ($config['revision']) {
				$data_template = "%s";
				$args[] = htmlspecialchars(date("D M j G:i:s T Y", intval($config['revision']['time'])));
			}
			break;


		case 'state_table_size':
			$data_template = <<<END
	<div class="progress">
		<div id="statePB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="%s" aria-valuemin="0" aria-valuemax="100" style="width: %s">
		</div>
	</div>
	<span id="pfstateusagemeter">%s</span>&nbsp;<span id="pfstate">(%s)</span>&nbsp;<span><a href="diag_dump_states.php">%s</a></span>
END;
			$pfstatetext = get_pfstate();
			$pfstateusage = get_pfstate(true);
			$args[] = $pfstateusage;
			$args[] = $pfstateusage . '%';
			$args[] = $pfstateusage . '%';
			$args[] = htmlspecialchars($pfstatetext);
			$args[] = gettext("Show states");
			break;


		case 'mbuf_usage':
			$data_template = <<<END
	<div class="progress">
		<div id="mbufPB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="%s" aria-valuemin="0" aria-valuemax="100" style="width: %s">
		</div>
	</div>
	<span id="mbufusagemeter">%s</span>&nbsp;<span id="mbuf">(%s)</span>
END;
			$mbufstext = get_mbuf();
			$mbufusage = get_mbuf(true);
			$args[] = $mbufusage;
			$args[] = $mbufusage . '%';
			$args[] = $mbufusage . '%';
			$args[] = htmlspecialchars($mbufstext);
			break;


		case 'temperature':
			if (get_temp() != "") {
				$data_template = <<<END
	<div class="progress">
		<div id="tempPB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="%s" aria-valuemin="0" aria-valuemax="100" style="width: %s">
		</div>
	</div>
	<span id="tempmeter">%s&deg;C</span>
END;
				$temp_deg_c = get_temp();
				$args[] = $temp_deg_c;
				$args[] = $temp_deg_c . '%';
				$args[] = $temp_deg_c;
			}
			break;


		case 'load_average':
			$data_template = "<div id='load_average' title='%s'>%s</div>";
			$args[] = gettext('Last 1, 5 and 15 minutes');
			$args[] = get_load_average();
			break;



		case 'cpu_usage':
			$data_template = <<<END
	<div class="progress">
		<div id="cpuPB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
		</div>
	</div>
	<span id="cpumeter">%s</span>
END;
			$update_period = !empty($config['widgets']['period']) ? $config['widgets']['period'] : "10";
			$args[] = sprintf(gettext("Updating in %s seconds"), $update_period);
			break;



		case 'memory_usage':
			$data_template = <<<END
	<div class="progress" >
		<div id="memUsagePB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="%s" aria-valuemin="0" aria-valuemax="100" style="width: %s">
		</div>
	</div>
	<span id="memusagemeter">%s</span><span>%% of %s MiB</span>
END;
			$memUsage = mem_usage();
			$args[] = $memUsage;
			$args[] = $memUsage . '%';
			$args[] = $memUsage;
			$args[] = sprintf("%.0f", get_single_sysctl('hw.physmem') / (1024*1024));
			break;


		case 'swap_usage':
			if ($showswap == true) {
				$data_template = <<<END
	<div class="progress">
		<div class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="%s" aria-valuemin="0" aria-valuemax="100" style="width: %s">
		</div>
	</div>
	<span>%s of %s MiB</span>
END;
				$swapusage = swap_usage();
				$args[] = $swapusage;
				$args[] = $swapusage . '%';
				$args[] = $swapusage . '%';
				$args[] = sprintf("%.0f", `/usr/sbin/swapinfo -m | /usr/bin/grep -v Device | /usr/bin/awk '{ print $2;}'`);
			}
			break;


		case 'disk_usage':
			$diskidx = 0;
			foreach ($filesystems as $fs) {
				$data_template .= <<<END
				<strong>%s  %s:</strong><br />
					<div class="progress" >
						<div id="diskspace<?=$diskidx?>" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="%s" aria-valuemin="0" aria-valuemax="100" style="width: %s">
						</div>
					</div>
					<span>%s %s %siB - %s</span><br />
END;
				$args[] = gettext('Mountpoint');
				$args[] = $fs['mountpoint'];
				$args[] = $fs['percent_used'];
				$args[] = $fs['percent_used'] . '%';
				$args[] = $fs['percent_used'] . '%';
				$args[] = gettext("of");
				$args[] = $fs['total_size'];
				$args[] = $fs['type'] . ("md" == substr(basename($fs['device']), 0, 2) ? " " . gettext("in RAM") : "");
				$diskidx++;
			}
			break;


		case 'ALL_HIDDEN':
			$data_template = "<div class='text-center'>\n%s</div>";
			$args[] = gettext('All System Information items are hidden or can not be shown.');
			break;
	}

	// merge data into html template, indent HTML, and return

	$data = vsprintf($data_template, $args);
	if (strlen($data) > 0) {
		$html = "<th><span style='font-weight:normal; margin-left:20px'>{$title_content}</span></th>\n" .
			"<td>\n{$data}\n</td>\n";
		return str_replace("\n", "\n\t\t\t", $html);
	} else {
		return '';
	}
}



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

/*   Adding one second to the system widet update period
 *   will ensure that we update the GUI right after the stats are updated.
 */
$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;
$widgetperiod += 1000;

$filesystems = get_mounted_filesystems();

if (strlen($user_settings['widgets']['system_information']['filter']) > 0) {
	$itemsshown = array_diff($validNames, explode(",", $user_settings['widgets']['system_information']['filter']));
} else {
	$itemsshown = $validNames;
}

$data = array();
foreach ($itemsshown as $itemkey) {
	$item_html = get_item_html($itemkey);  // get sysinfo data for this item
	// Handle if an item returns blank data (eg can't be used, or isn't available on platform)
	if (strlen($item_html) > 0) {
		$itemdata = $sysinfo_items[$itemkey];
		$itemsort = $itemdata['itemsort'];
		$catdata = $sysinfo_categories[$itemdata['category']];
		$catsort = $catdata['catsort'];
		$data[$catsort]['cattitle'] = $catdata['cattitle']; // add key for category and get category title
		$data[$catsort]['itemstoshow'][$itemsort] = $itemdata;  // create a key for item and add static item data
		$data[$catsort]['itemstoshow'][$itemsort]['item_html'] = '<tr>' . $item_html . '<tr>';
	}
}
if (count($data) == 0) {
	// adds a single item containing the special key for the "no sysinfo selected" item if nothing else will display
	$data[0] = array(
		'cattitle' => '',
		'itemstoshow' => array('itemsort' => '', 'item_html' => '<tr>' . get_item_html('ALL_HIDDEN') . '<tr>')
		);
}

// Now we have the HTML for each category and items within categories, or a "nothing to show" section if none

?>

<div class="table-responsive">
<table class="table table-hover table-striped table-condensed">
	<tbody>
<?php
	// print sysinfo items

	//sort categories
	ksort($data);
	foreach ($data as $cat => $cat_data) {
		// display title for this category
		echo "<tr><th><strong>{$cat_data['cattitle']}</strong></th>\n<td>&nbsp;</td></tr>";
		// sort and output items within category
		ksort($cat_data);
		foreach($cat_data['itemstoshow'] as $itemsort => $itemdata) {
			echo "\n\n\t\t<!-- {$cat_data['cattitle']} -> {$itemdata['itemtitle']} -->\n";
			echo $itemdata['item_html'];
		}
	}
?>

	</tbody>
</table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<form action="/widgets/widgets/system_information.widget.php" method="post" class="form-horizontal">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
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
				foreach ($sysinfo_items as $sysinfo_item_key => $sysinfo_item_data):
?>
						<tr>
							<td><?=$sysinfo_item_data['itemtitle']?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$sysinfo_item_key?>" type="checkbox" <?=(in_array($sysinfo_item_key, $itemsshown) ? 'checked':'')?>></td>
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
			<button id="showallsysinfoitems" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
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
var updateMeters_running = false;
function updateMeters() {
	if (updateMeters_running) {
		return;
	}
	updateMeters_running = true;
	url = '/getstats.php';

	$.ajax(url, {
		type: 'get',
		success: function(data) {
			response = data || "";
			if (response != "")
				stats(data);
			updateMeters_running = false;
		}
	});
}

var update_interval = "<?=$widgetperiod?>";

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

	updateUptime(values[2]);
	updateDateTime(values[5]);
	updateCPU(values[0]);
	updateMemory(values[1]);
	updateState(values[3]);
	updateTemp(values[4]);
	updateCpuFreq(values[6]);
	updateLoadAverage(values[7]);
	updateMbuf(values[8]);
	updateMbufMeter(values[9]);
	updateStateMeter(values[10]);
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
	timeout = window.setInterval(updateMeters, update_interval);
});
<?php endif; // $widget_first_instance ?>
events.push(function(){
	set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=show]", "<?=$widget_showallnone_id?>");
});
//]]>
</script>
