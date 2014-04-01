<?php
/*
        $Id$
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

if($_REQUEST['getupdatestatus']) {
	if(isset($config['system']['firmware']['disablecheck'])) {
		exit;
	}
	if(isset($config['system']['firmware']['alturl']['enable']))
		$updater_url = "{$config['system']['firmware']['alturl']['firmwareurl']}";
	else 
		$updater_url = $g['update_url'];

	$nanosize = "";
	if ($g['platform'] == "nanobsd") {
		if (file_exists("/etc/nano_use_vga.txt"))
			$nanosize = "-nanobsd-vga-";
		else
			$nanosize = "-nanobsd-";
		$nanosize .= strtolower(trim(file_get_contents("/etc/nanosize.txt")));
	}

	@unlink("/tmp/{$g['product_name']}_version");
	if (download_file_with_progress_bar("{$updater_url}/version{$nanosize}", "/tmp/{$g['product_name']}_version", 'read_body', 5, 5) === true)
		$remote_version = trim(@file_get_contents("/tmp/{$g['product_name']}_version"));

	if(empty($remote_version))
		echo "<br /><br />Unable to check for updates.";
	else {
		$current_installed_buildtime = trim(file_get_contents("/etc/version.buildtime"));
		$current_installed_version = trim(file_get_contents("/etc/version"));

		if(!$remote_version) {
			echo "<br /><br />Unable to check for updates.";
		}
		else {
			$needs_system_upgrade = false;
			if (pfs_version_compare($current_installed_buildtime, $current_installed_version, $remote_version) == -1) {
				echo "<br /><span class=\"red\" id=\"updatealert\"><b>Update available. </b></span><a href=\"/system_firmware_check.php\">Click Here</a> to view update.";
				echo "\n<script type=\"text/javascript\">\n";
				echo "//<![CDATA[\n";
				echo "jQuery('#updatealert').effect('pulsate',{times: 30},10000);\n";
				echo "//]]>\n";
				echo "</script>\n";
			} else
				echo "<br />You are on the latest version.";
		}
	}
	exit;
}

$curcfg = $config['system']['firmware'];

?>
<script type="text/javascript">
//<![CDATA[
	jQuery(function() { 
		jQuery("#statePB").progressbar( { value: <?php echo get_pfstate(true); ?> } );
		jQuery("#mbufPB").progressbar( { value: <?php echo get_mbuf(true); ?> } );
		jQuery("#cpuPB").progressbar( { value:false } );
		jQuery("#memUsagePB").progressbar( { value: <?php echo mem_usage(); ?> } );
		jQuery("#diskUsagePB").progressbar( { value: <?php echo disk_usage(); ?> } );

		<?php if($showswap == true): ?>
			jQuery("#swapUsagePB").progressbar( { value: <?php echo swap_usage(); ?> } );
		<?php endif; ?>
		<?php if (get_temp() != ""): ?>
                	jQuery("#tempPB").progressbar( { value: <?php echo get_temp(); ?> } );
		<?php endif; ?>
	});
//]]>
</script>

<table width="100%" border="0" cellspacing="0" cellpadding="0" summary="system information">
	<tbody>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Name");?></td>
			<td width="75%" class="listr"><?php echo $config['system']['hostname'] . "." . $config['system']['domain']; ?></td>
		</tr>
		<tr>
			<td width="25%" valign="top" class="vncellt"><?=gettext("Version");?></td>
			<td width="75%" class="listr">
				<strong><?php readfile("/etc/version"); ?></strong>
				(<?php echo php_uname("m"); ?>)
				<br />
				built on <?php readfile("/etc/version.buildtime"); ?>
		<?php if(!$g['hideuname']): ?>
		<br />
		<div id="uname"><a href="#" onclick='swapuname(); return false;'><?php echo php_uname("s") . " " . php_uname("r"); ?></a></div>
		<?php endif; ?>
		<?php if(!isset($config['system']['firmware']['disablecheck'])): ?>
		<div id='updatestatus'><br /><?php echo gettext("Obtaining update status"); ?> ...</div>
		<?php endif; ?>
			</td>
		</tr>
		<?php if(!$g['hideplatform']): ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Platform");?></td>
			<td width="75%" class="listr">
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
			<td width="25%" class="vncellt"><?=gettext("NanoBSD Boot Slice");?></td>
			<td width="75%" class="listr">
				<?=htmlspecialchars(nanobsd_friendly_slice_name($BOOT_DEVICE));?> / <?=htmlspecialchars($BOOTFLASH);?> <?php echo $rw; ?>
				<?php if ($BOOTFLASH != $ACTIVE_SLICE): ?>
				<br /><br />Next Boot:<br />
				<?=htmlspecialchars(nanobsd_friendly_slice_name($GLABEL_SLICE));?> / <?=htmlspecialchars($ACTIVE_SLICE);?>
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("CPU Type");?></td>
			<td width="75%" class="listr">
			<?php 
				$cpumodel = "";
				exec("/sbin/sysctl -n hw.model", $cpumodel);
				$cpumodel = implode(" ", $cpumodel);
				echo (htmlspecialchars($cpumodel));
			?>
			<div id="cpufreq"><?= get_cpufreq(); ?></div>
		<?php	$cpucount = get_cpu_count();
			if ($cpucount > 1): ?>
			<div id="cpucount">
				<?= htmlspecialchars($cpucount) ?> CPUs: <?= htmlspecialchars(get_cpu_count(true)); ?></div>
		<?php	endif; ?>
			</td>
		</tr>
		<?php if ($hwcrypto): ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Hardware crypto");?></td>
			<td width="75%" class="listr"><?=htmlspecialchars($hwcrypto);?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Uptime");?></td>
			<td width="75%" class="listr" id="uptime"><?= htmlspecialchars(get_uptime()); ?></td>
		</tr>
        <tr>
            <td width="25%" class="vncellt"><?=gettext("Current date/time");?></td>
            <td width="75%" class="listr">
                <div id="datetime"><?= date("D M j G:i:s T Y"); ?></div>
            </td>
        </tr>
		 <tr>
             <td width="30%" class="vncellt"><?=gettext("DNS server(s)");?></td>
             <td width="70%" class="listr">
					<?php
						$dns_servers = get_dns_servers();
						foreach($dns_servers as $dns) {
							echo "{$dns}<br />";
						}
					?>
			</td>
		</tr>	
		<?php if ($config['revision']): ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Last config change");?></td>
			<td width="75%" class="listr"><?= htmlspecialchars(date("D M j G:i:s T Y", intval($config['revision']['time'])));?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("State table size");?></td>
			<td width="75%" class="listr">
				<?php	$pfstatetext = get_pfstate();
					$pfstateusage = get_pfstate(true);
				?>
				<div id="statePB"></div>
				<span id="pfstateusagemeter"><?= $pfstateusage.'%'; ?></span> (<span id="pfstate"><?= htmlspecialchars($pfstatetext); ?></span>)
		    	<br />
		    	<a href="diag_dump_states.php"><?=gettext("Show states");?></a>
			</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("MBUF Usage");?></td>
			<td width="75%" class="listr">
				<?php
					$mbufstext = get_mbuf();
					$mbufusage = get_mbuf(true);
				?>
				<div id="mbufPB"></div>
				<span id="mbufusagemeter"><?= $mbufusage.'%'; ?></span> (<span id="mbuf"><?= $mbufstext ?></span>)
			</td>
		</tr>
                <?php if (get_temp() != ""): ?>
                <tr>
                        <td width="25%" class="vncellt"><?=gettext("Temperature");?></td>
			<td width="75%" class="listr">
				<?php $TempMeter = $temp = get_temp(); ?>
				<div id="tempPB"></div>
				<span id="tempmeter"><?= $temp."&#176;C"; ?></span>
			</td>
                </tr>
                <?php endif; ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Load average");?></td>
			<td width="75%" class="listr">
			<div id="load_average" title="Last 1, 5 and 15 minutes"><?= get_load_average(); ?></div>
			</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("CPU usage");?></td>
			<td width="75%" class="listr">
				<div id="cpuPB"></div>
				<span id="cpumeter">(Updating in 10 seconds)</span>
			</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Memory usage");?></td>
			<td width="75%" class="listr">
				<?php $memUsage = mem_usage(); ?>
				<div id="memUsagePB"></div>
				<span id="memusagemeter"><?= $memUsage.'%'; ?></span> of <?= sprintf("%.0f", `/sbin/sysctl -n hw.physmem` / (1024*1024)) ?> MB
			</td>
		</tr>
		<?php if($showswap == true): ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("SWAP usage");?></td>
			<td width="75%" class="listr">
				<?php $swapusage = swap_usage(); ?>
				<div id="swapUsagePB"></div>
				<span id="swapusagemeter"><?= $swapusage.'%'; ?></span> of <?= sprintf("%.0f", `/usr/sbin/swapinfo -m | /usr/bin/grep -v Device | /usr/bin/awk '{ print $2;}'`) ?> MB
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Disk usage");?></td>
			<td width="75%" class="listr">
				<?php $diskusage = disk_usage(); ?>
				<div id="diskUsagePB"></div>
				<span id="diskusagemeter"><?= $diskusage.'%'; ?></span> of <?= `/bin/df -h / | /usr/bin/grep -v 'Size' | /usr/bin/awk '{ print $2 }'` ?>
			</td>
		</tr>
	</tbody>
</table>
<script type="text/javascript">
//<![CDATA[
	function swapuname() {
		jQuery('#uname').html("<?php echo php_uname("a"); ?>");
	}
	<?php if(!isset($config['system']['firmware']['disablecheck'])): ?>
	function getstatus() {
		scroll(0,0);
		var url = "/widgets/widgets/system_information.widget.php";
		var pars = 'getupdatestatus=yes';
		jQuery.ajax(
			url,
			{
				type: 'get',
				data: pars,
				complete: activitycallback
			});
	}
	function activitycallback(transport) {
		// .html() method process all script tags contained in responseText,
		// to avoid this we set the innerHTML property
		jQuery('#updatestatus').prop('innerHTML',transport.responseText);
	}
	setTimeout('getstatus()', 4000);
	<?php endif; ?>
//]]>
</script>
