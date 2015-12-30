<?php
/*
	system_information.widget.php
*/
/*
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2007 Scott Dale
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
 */

require_once("functions.inc");
require_once("guiconfig.inc");
require_once('notices.inc');
include_once("includes/functions.inc.php");

if ($_REQUEST['getupdatestatus']) {
	require_once("pkg-utils.inc");

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
		print(gettext("You are on the latest version."));
		break;
	case '>':
		print(gettext("You are on a later version than<br />the official release."));
		break;
	default:
		print(gettext( "<i>Error comparing installed version<br />with latest available</i>"));
		break;
	}

	exit;
}

$filesystems = get_mounted_filesystems();
?>

<table class="table table-striped table-hover">
	<tbody>
		<tr>
			<th><?=gettext("Name");?></th>
			<td><?php echo htmlspecialchars($config['system']['hostname'] . "." . $config['system']['domain']); ?></td>
		</tr>
		<tr>
			<th><?=gettext("Version");?></th>
			<td>
				<strong><?=$g['product_version']?></strong>
				(<?php echo php_uname("m"); ?>)
				<br />
				built on <?php readfile("/etc/version.buildtime"); ?>
			<?php if (!$g['hideuname']): ?>
				<br />
				<span title="<?php echo php_uname("a"); ?>"><?php echo php_uname("s") . " " . php_uname("r"); ?></span>
			<?php endif; ?>
			<br/><br/>
			<?php if (!isset($config['system']['firmware']['disablecheck'])): ?>
				<div id='updatestatus'><?php echo gettext("Obtaining update status "); ?><i class="fa fa-cog fa-spin"></i></div>
			<?php endif; ?>
			</td>
		</tr>
		<?php if (!$g['hideplatform']): ?>
		<tr>
			<th><?=gettext("Platform");?></th>
			<td>
				<?=htmlspecialchars($g['platform']);?>
				<?php if (($g['platform'] == "nanobsd") && (file_exists("/etc/nanosize.txt"))) {
					echo " (" . htmlspecialchars(trim(file_get_contents("/etc/nanosize.txt"))) . ")";
				} ?>
			</td>
		</tr>
		<?php endif; ?>
		<?php if ($g['platform'] == "nanobsd"): ?>
			<?
			global $SLICE, $OLDSLICE, $TOFLASH, $COMPLETE_PATH, $COMPLETE_BOOT_PATH;
			global $GLABEL_SLICE, $UFS_ID, $OLD_UFS_ID, $BOOTFLASH;
			global $BOOT_DEVICE, $REAL_BOOT_DEVICE, $BOOT_DRIVE, $ACTIVE_SLICE;
			nanobsd_detect_slice_info();
			$rw = is_writable("/") ? "(rw)" : "(ro)";
			?>
		<tr>
			<th><?=gettext("NanoBSD Boot Slice");?></th>
			<td>
				<?=htmlspecialchars(nanobsd_friendly_slice_name($BOOT_DEVICE));?> / <?=htmlspecialchars($BOOTFLASH);?><?php echo $rw; ?>
				<?php if ($BOOTFLASH != $ACTIVE_SLICE): ?>
				<br /><br />Next Boot:<br />
				<?=htmlspecialchars(nanobsd_friendly_slice_name($GLABEL_SLICE));?> / <?=htmlspecialchars($ACTIVE_SLICE);?>
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<th><?=gettext("CPU Type");?></th>
			<td><?=htmlspecialchars(get_single_sysctl("hw.model"))?>
				<div id="cpufreq"><?= get_cpufreq(); ?></div>
		<?php
			$cpucount = get_cpu_count();
			if ($cpucount > 1): ?>
				<div id="cpucount">
					<?= htmlspecialchars($cpucount) ?> CPUs: <?= htmlspecialchars(get_cpu_count(true)); ?>
				</div>
		<?php endif; ?>
			</td>
		</tr>
		<?php if ($hwcrypto): ?>
		<tr>
			<th><?=gettext("Hardware crypto");?></th>
			<td><?=htmlspecialchars($hwcrypto);?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<th><?=gettext("Uptime");?></th>
			<td id="uptime"><?= htmlspecialchars(get_uptime()); ?></td>
		</tr>
		<tr>
			<th><?=gettext("Current date/time");?></th>
			<td><div id="datetime"><?= date("D M j G:i:s T Y"); ?></div></td>
		</tr>
		<tr>
			<th><?=gettext("DNS server(s)");?></th>
			<td>
				<ul>
				<?php
					$dns_servers = get_dns_servers();
					foreach($dns_servers as $dns) {
						echo "<li>{$dns}</li>";
					}
				?>
				</ul>
		</td>
		</tr>
		<?php if ($config['revision']): ?>
		<tr>
			<th><?=gettext("Last config change");?></th>
			<td><?= htmlspecialchars(date("D M j G:i:s T Y", intval($config['revision']['time'])));?></td>
		</tr>
		<?php endif; ?>
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
		<?php if (get_temp() != ""): ?>
		<tr>
			<th><?=gettext("Temperature");?></th>
			<td>
				<?php $TempMeter = $temp = get_temp(); ?>
				<div id="tempPB"></div>
				<span id="tempmeter"><?= $temp."&#176;C"; ?></span>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<th><?=gettext("Load average");?></th>
			<td>
				<div id="load_average" title="Last 1, 5 and 15 minutes"><?= get_load_average(); ?></div>
			</td>
		</tr>
		<tr>
			<th><?=gettext("CPU usage");?></th>
			<td>
				<div class="progress">
					<div id="cpuPB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
					</div>
				</div>
				<span id="cpumeter">(Updating in 10 seconds)</span>
			</td>
		</tr>
		<tr>
			<th><?=gettext("Memory usage");?></th>
			<td>
				<?php $memUsage = mem_usage(); ?>

				<div class="progress" >
					<div id="memUsagePB" class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$memUsage?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$memUsage?>%">
					</div>
				</div>
				<span id="memusagemeter"><?=$memUsage?></span><span>% of <?= sprintf("%.0f", get_single_sysctl('hw.physmem') / (1024*1024)) ?> MB</span>
			</td>
		</tr>
		<?php if ($showswap == true): ?>
		<tr>
			<th><?=gettext("SWAP usage");?></th>
			<td>
				<?php $swapusage = swap_usage(); ?>
				<div class="progress">
					<div class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$swapusage?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$swapusage?>%">
					</div>
				</div>
				<span><?=$swapusage?>% of <?= sprintf("%.0f", `/usr/sbin/swapinfo -m | /usr/bin/grep -v Device | /usr/bin/awk '{ print $2;}'`) ?> MB</span>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<th><?=gettext("Disk usage");?></th>
			<td>
				<table class="table">
<?php foreach ($filesystems as $fs): ?>
					<tr>
						<th><?=$fs['mountpoint']?></th>
						<td><?=$fs['type'] . ("md" == substr(basename($fs['device']), 0, 2) ? " in RAM" : "")?></td>
						<td><?=$fs['total_size']?></td>
						<td>
							<span><?=$fs['percent_used']?>%</span>
						</td>
					</tr>
<?php endforeach; ?>
				</table>
			</td>
		</tr>
	</tbody>
</table>

<script type="text/javascript">
//<![CDATA[
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
			$('#widget-system_information #updatestatus').html(data);
		}
	});
}

function updateMeters() {
	url = '/getstats.php';

	jQuery.ajax(url, {
		type: 'get',
		success: function(data) {
			response = data || "";
			if (response != "")
				stats(data);
		}
	});

	setTimer();

}

events.push(function(){
	setTimeout('systemStatusGetUpdateStatus()', 4000);
});

/*   Most widgets update their backend data every 10 seconds.  11 seconds
 *   will ensure that we update the GUI right after the stats are updated.
 *   Seconds * 1000 = value
 */

var Seconds = 11;
var update_interval = (Math.abs(Math.ceil(Seconds))-1)*1000 + 990;

function setProgress(barName, percent) {
	jQuery('#' + barName).css('width', percent + '%').attr('aria-valuenow', percent);
}

function setTimer() {
	timeout = window.setTimeout('updateMeters()', update_interval);
}

function stats(x) {
	var values = x.split("|");
	if (jQuery.each(values,function(key,value){
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
	updateGatewayStats(values[8]);
	updateCpuFreq(values[9]);
	updateLoadAverage(values[10]);
	updateMbuf(values[11]);
	updateMbufMeter(values[12]);
	updateStateMeter(values[13]);
}

function updateMemory(x) {
	if (jQuery('#memusagemeter')) {
		jQuery("#memusagemeter").html(x);
	}
	if (jQuery('#memUsagePB')) {
		setProgress('memUsagePB', parseInt(x));
	}
}

function updateMbuf(x) {
	if (jQuery('#mbuf')) {
		jQuery("#mbuf").html(x);
	}
}

function updateMbufMeter(x) {
	if (jQuery('#mbufusagemeter')) {
		jQuery("#mbufusagemeter").html(x + '%');
	}
	if (jQuery('#mbufPB')) {
		setProgress('mbufPB', parseInt(x));
	}
}

function updateCPU(x) {

	if (jQuery('#cpumeter')) {
		jQuery("#cpumeter").html(x + '%');
	}
	if (jQuery('#cpuPB')) {
		setProgress('cpuPB', parseInt(x));
	}

	/* Load CPU Graph widget if enabled */
	if (widgetActive('cpu_graphs')) {
		GraphValue(graph[0], x);
	}
}

function updateTemp(x) {
	if (jQuery("#tempmeter")) {
		jQuery("#tempmeter").html(x + '\u00B0' + 'C');
	}
	if (jQuery('#tempPB')) {
		jQuery("#tempPB").progressbar( { value: parseInt(x) } );
	}
}

function updateDateTime(x) {
	if (jQuery('#datetime')) {
		jQuery("#datetime").html(x);
	}
}

function updateUptime(x) {
	if (jQuery('#uptime')) {
		jQuery("#uptime").html(x);
	}
}

function updateState(x) {
	if (jQuery('#pfstate')) {
		jQuery("#pfstate").html('(' + x + ')');
	}
}

function updateStateMeter(x) {
	if (jQuery('#pfstateusagemeter')) {
		jQuery("#pfstateusagemeter").html(x + '%');
	}
	if (jQuery('#statePB')) {
		setProgress('statePB', parseInt(x));
	}
}

function updateGatewayStats(x) {
	if (widgetActive("gateways")) {
		gateways_split = x.split(",");
		for (var y=0; y<gateways_split.length; y++) {
			gateways_field_split = gateways_split[y].split("^");
			if (jQuery('#gateway' + (y + 1))) {
				jQuery('#gateway' + (y + 1)).html(gateways_field_split[0]);
				if (gateways_field_split[1]) {
					jQuery('#gateway' + (y + 1)).css('background-color',gateways_field_split[1]);
				}
			}
		}
	}
}

function updateCpuFreq(x) {
	if (jQuery('#cpufreq')) {
		jQuery("#cpufreq").html(x);
	}
}

function updateLoadAverage(x) {
	if (jQuery('#load_average')) {
		jQuery("#load_average").html(x);
	}
}

function updateInterfaceStats(x) {
	if (widgetActive("interface_statistics")) {
		statistics_split = x.split(",");
		var counter = 1;
		for (var y=0; y<statistics_split.length-1; y++) {
			if (jQuery('#stat' + counter)) {
				jQuery('#stat' + counter).html(statistics_split[y]);
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
			switch(details[1]) {
				case "up":
					jQuery('#' + details[0] + '-up').css("display","inline");
					jQuery('#' + details[0] + '-down').css("display","none");
					jQuery('#' + details[0] + '-block').css("display","none");
					jQuery('#' + details[0] + '-ip').html(ipv4_details);
					jQuery('#' + details[0] + '-ipv6').html(details[3]);
					jQuery('#' + details[0] + '-media').html(details[4]);
					break;
				case "down":
					jQuery('#' + details[0] + '-down').css("display","inline");
					jQuery('#' + details[0] + '-up').css("display","none");
					jQuery('#' + details[0] + '-block').css("display","none");
					jQuery('#' + details[0] + '-ip').html(ipv4_details);
					jQuery('#' + details[0] + '-ipv6').html(details[3]);
					jQuery('#' + details[0] + '-media').html(details[4]);
					break;
				case "block":
					jQuery('#' + details[0] + '-block').css("display","inline");
					jQuery('#' + details[0] + '-down').css("display","none");
					jQuery('#' + details[0] + '-up').css("display","none");
					break;
			}
		});
	}
}

function widgetActive(x) {
	var widget = jQuery('#' + x + '-container');
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
//]]>
</script>
