<?php
/*
	system_information.widget.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright 2007 Scott Dale
	Part of pfSense widgets (https://www.pfsense.org)
	originally based on m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
	and Jonathan Watt <jwatt@jwatt.org>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

require_once("functions.inc");
require_once("guiconfig.inc");
require_once('notices.inc');
include_once("includes/functions.inc.php");

if ($_REQUEST['getupdatestatus']) {
	if (isset($config['system']['firmware']['disablecheck'])) {
		exit;
	}
	if (isset($config['system']['firmware']['alturl']['enable'])) {
		$updater_url = "{$config['system']['firmware']['alturl']['firmwareurl']}";
	} else {
		$updater_url = $g['update_url'];
	}

	$nanosize = "";
	if ($g['platform'] == "nanobsd") {
		if (!isset($g['enableserial_force'])) {
			$nanosize = "-nanobsd-vga-";
		} else {
			$nanosize = "-nanobsd-";
		}
		$nanosize .= strtolower(trim(file_get_contents("/etc/nanosize.txt")));
	}

	@unlink("/tmp/{$g['product_name']}_version");
	if (download_file_with_progress_bar("{$updater_url}/version{$nanosize}", "/tmp/{$g['product_name']}_version", 'read_body', 5, 5) === true) {
		$remote_version = trim(@file_get_contents("/tmp/{$g['product_name']}_version"));
	}

	if(empty($remote_version))
		echo "<i>Unable to check for updates</i>";
	else {
		$current_installed_buildtime = trim(file_get_contents("/etc/version.buildtime"));
		$current_installed_version = trim(file_get_contents("/etc/version"));

		if(!$remote_version) {
			echo "<i>Unable to check for updates</i>";
		}
		else {
			$needs_system_upgrade = false;
			$version_compare = pfs_version_compare($current_installed_buildtime, $g['product_version'], $remote_version);
			if ($version_compare == -1) {
?>
<div class="alert alert-warning" role="alert">
	Version <?=$remote_version?> is available. <a href="/system_firmware_check.php" class="alert-link">Click Here to view.</a>
</div>
<?php
			} elseif ($version_compare == 1) {
				echo "You are on a later version than the official release.";
			} else {
				echo "You are on the latest version.";
			}
		}
	}
	exit;
}

$curcfg = $config['system']['firmware'];

$filesystems = get_mounted_filesystems();
?>

<table class="table table-striped table-hover">
	<tbody>
		<tr>
			<th><?=gettext("Name");?></td>
			<td><?php echo $config['system']['hostname'] . "." . $config['system']['domain']; ?></td>
		</tr>
		<tr>
			<th><?=gettext("Version");?></th>
			<td>
				<strong><?php readfile("/etc/version"); ?></strong>
				(<?php echo php_uname("m"); ?>)
				<br />
				built on <?php readfile("/etc/version.buildtime"); ?>
			<?php if(!$g['hideuname']): ?>
				<br />
				<span title="<?php echo php_uname("a"); ?>"><?php echo php_uname("s") . " " . php_uname("r"); ?></span>
			<?php endif; ?>
			<br/><br/>
			<?php if(!isset($config['system']['firmware']['disablecheck'])): ?>
				<div id='updatestatus'><?php echo gettext("Obtaining update status"); ?> ...</div>
			<?php endif; ?>
			</td>
		</tr>
		<?php if (!$g['hideplatform']): ?>
		<tr>
			<th><?=gettext("Platform");?></td>
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
			<th><?=gettext("NanoBSD Boot Slice");?></td>
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
			<th><?=gettext("CPU Type");?></td>
			<td><?=htmlspecialchars(get_single_sysctl("hw.model"))?>
			<div id="cpufreq"><?= get_cpufreq(); ?></div>
		<?php
			$cpucount = get_cpu_count();
			if ($cpucount > 1): ?>
			<div id="cpucount">
				<?= htmlspecialchars($cpucount) ?> CPUs: <?= htmlspecialchars(get_cpu_count(true)); ?></div>
		<?php endif; ?>
			</td>
		</tr>
		<?php if ($hwcrypto): ?>
		<tr>
			<th><?=gettext("Hardware crypto");?></td>
			<td><?=htmlspecialchars($hwcrypto);?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<th><?=gettext("Uptime");?></td>
			<td id="uptime"><?= htmlspecialchars(get_uptime()); ?></td>
		</tr>
		<tr>
			<th><?=gettext("Current date/time");?></td>
			<td><div id="datetime"><?= date("D M j G:i:s T Y"); ?></div></td>
		</tr>
		<tr>
			<th><?=gettext("DNS server(s)");?></td>
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
			<th><?=gettext("Last config change");?></td>
			<td><?= htmlspecialchars(date("D M j G:i:s T Y", intval($config['revision']['time'])));?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<th><?=gettext("State table size");?></td>
			<td>
				<?php	$pfstatetext = get_pfstate();
					$pfstateusage = get_pfstate(true);
				?>
				<div class="progress">
					<div class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$pfstateusage?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$pfstateusage?>%">
						<span><?=$pfstateusage?>% (<?= htmlspecialchars($pfstatetext)?>)</span>
					</div>
				</div>
				<a href="diag_dump_states.php"><?=gettext("Show states");?></a>
			</td>
		</tr>
		<tr>
			<th><?=gettext("MBUF Usage");?></td>
			<td>
				<?php
					$mbufstext = get_mbuf();
					$mbufusage = get_mbuf(true);
				?>
				<div class="progress">
					<div class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$mbufusage?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$mbufusage?>%">
						<span><?=$mbufusage?>% (<?= htmlspecialchars($mbufstext)?>)</span>
					</div>
				</div>
			</td>
		</tr>
		<?php if (get_temp() != ""): ?>
		<tr>
			<th><?=gettext("Temperature");?></td>
			<td>
				<?php $TempMeter = $temp = get_temp(); ?>
				<div id="tempPB"></div>
				<span id="tempmeter"><?= $temp."&#176;C"; ?></span>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<th><?=gettext("Load average");?></td>
			<td>
			<div id="load_average" title="Last 1, 5 and 15 minutes"><?= get_load_average(); ?></div>
			</td>
		</tr>
		<tr>
			<th><?=gettext("CPU usage");?></td>
			<td>
				<div id="cpuPB"></div>
				<span id="cpumeter">(Updating in 10 seconds)</span>
			</td>
		</tr>
		<tr>
			<th><?=gettext("Memory usage");?></td>
			<td>
				<?php $memUsage = mem_usage(); ?>
				<div class="progress">
					<div class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$memUsage?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$memUsage?>%">
						<span><?=$memUsage?>% of <?= sprintf("%.0f", get_single_sysctl('hw.physmem') / (1024*1024)) ?> MB</span>
					</div>
				</div>
			</td>
		</tr>
		<?php if ($showswap == true): ?>
		<tr>
			<th><?=gettext("SWAP usage");?></td>
			<td>
				<?php $swapusage = swap_usage(); ?>
				<div class="progress">
					<div class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$swapusage?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$swapusage?>%">
						<span><?=$swapusage?>% of <?= sprintf("%.0f", `/usr/sbin/swapinfo -m | /usr/bin/grep -v Device | /usr/bin/awk '{ print $2;}'`) ?> MB</span>
					</div>
				</div>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<th><?=gettext("Disk usage");?></td>
			<td>
				<table class="table">
<?PHP foreach ($filesystems as $fs): ?>
				<tr>
					<th><?=$fs['mountpoint']?></th>
					<td><?=$fs['type'] . ("md" == substr(basename($fs['device']), 0, 2) ? " in RAM" : "")?></td>
					<td><?=$fs['total_size']?></td>
					<td>
						<div class="progress">
							<div class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="<?=$fs['percent_used']?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$fs['percent_used']?>%">
								<span><?=$fs['percent_used']?>%</span>
							</div>
						</div>
					</td>
				</tr>
<?PHP endforeach; ?>
				</table>
			</td>
		</tr>
	</tbody>
</table>

<script>
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

events.push(function(){
	setTimeout('systemStatusGetUpdateStatus()', 4000);
});
</script>
