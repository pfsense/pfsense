<?php
/*
        $Id$
        Copyright 2007 Scott Dale
        Part of pfSense widgets (www.pfsense.com)
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

$nocsrf = true;

require_once("functions.inc");
require_once("guiconfig.inc");
require_once('notices.inc');

if($_REQUEST['getupdatestatus']) {
	if(isset($config['system']['firmware']['alturl']['enable']))
		$updater_url = "{$config['system']['firmware']['alturl']['firmwareurl']}";
	else 
		$updater_url = $g['update_url'];

	$nanosize = "";
	if ($g['platform'] == "nanobsd") {
		$nanosize = "-nanobsd-" . strtolower(trim(file_get_contents("/etc/nanosize.txt")));
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
				echo "<br/><span class=\"red\" id=\"updatealert\"><b>Update available. </b></span><a href=\"/system_firmware_check.php\">Click Here</a> to view update.";
				echo "<script type=\"text/javascript\">";
				echo "Effect.Pulsate('updatealert', { pulses: 30, duration: 10});";
				echo "</script>";
			} else
				echo "<br />You are on the latest version.";
		}
	}
	exit;
}

$curcfg = $config['system']['firmware'];

?>
</script>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
	<tbody>
		<tr>
			<td width="25%" class="vncellt">Name</td>
			<td width="75%" class="listr"><?php echo $config['system']['hostname'] . "." . $config['system']['domain']; ?></td>
		</tr>
		<tr>
			<td width="25%" valign="top" class="vncellt">Version</td>
			<td width="75%" class="listr">
				<strong><?php readfile("/etc/version"); ?></strong>
				(<?php echo php_uname("m"); ?>)
				<br />
				built on <?php readfile("/etc/version.buildtime"); ?>
                <br />
                <div name="uname" id="uname"><a href="#" onClick='swapuname(); return false;'><?php echo php_uname("s") . " " . php_uname("r"); ?></a></div>
                <div id='updatestatus'><br/>Obtaining update status...</div>
			</td>
		</tr>
		<?php if(!$g['hideplatform']): ?>
		<tr>
			<td width="25%" class="vncellt">Platform</td>
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
			?>
		<tr>
			<td width="25%" class="vncellt">NanoBSD Boot Slice</td>
			<td width="75%" class="listr">
				<?=htmlspecialchars(nanobsd_friendly_slice_name($BOOT_DEVICE));?> / <?=htmlspecialchars($BOOTFLASH);?>
				<?php if ($BOOTFLASH != $ACTIVE_SLICE): ?>
				<br/><br/>Next Boot:<br/>
				<?=htmlspecialchars(nanobsd_friendly_slice_name($GLABEL_SLICE));?> / <?=htmlspecialchars($ACTIVE_SLICE);?>
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt">CPU Type</td>
			<td width="75%" class="listr">
			<?php 
				$cpumodel = "";
				exec("/sbin/sysctl -n hw.model", $cpumodel);
				$cpumodel = implode(" ", $cpumodel);
				echo (htmlspecialchars($cpumodel));

				$cpufreqs = "";
				exec("/sbin/sysctl -n dev.cpu.0.freq_levels", $cpufreqs);
				$cpufreqs = explode(" ", trim($cpufreqs[0]));
				$maxfreq = explode("/", $cpufreqs[0]);
				$maxfreq = $maxfreq[0];
				$curfreq = "";
				exec("/sbin/sysctl -n dev.cpu.0.freq", $curfreq);
				$curfreq = trim($curfreq[0]);
				if ($curfreq != $maxfreq)
					echo "<br/>Current: {$curfreq} MHz, Max: {$maxfreq} MHz";
			?>
			</td>
		</tr>
		<?php if ($hwcrypto): ?>
		<tr>
			<td width="25%" class="vncellt">Hardware crypto</td>
			<td width="75%" class="listr"><?=htmlspecialchars($hwcrypto);?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt">Uptime</td>
			<td width="75%" class="listr"><input style="border: 0px solid white;" size="30" name="uptime" id="uptime" value="<?= htmlspecialchars(get_uptime()); ?>" /></td>
		</tr>
        <tr>
            <td width="25%" class="vncellt"><?=gettext("Current date/time");?></td>
            <td width="75%" class="listr">
                <div id="datetime"><?= date("D M j G:i:s T Y"); ?></div>
            </td>
        </tr>			
		 <tr>
             <td width="30%" class="vncellt">DNS server(s)</td>
             <td width="70%" class="listr">
					<?php
						$dns_servers = get_dns_servers();
						foreach($dns_servers as $dns) {
							echo "{$dns}<br>";
						}
					?>
			</td>
		</tr>	
		<?php if ($config['revision']): ?>
		<tr>
			<td width="25%" class="vncellt">Last config change</td>
			<td width="75%" class="listr"><?= htmlspecialchars(date("D M j G:i:s T Y", intval($config['revision']['time'])));?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt">State table size</td>
			<td width="75%" class="listr">
				<input style="border: 0px solid white;" size="30" name="pfstate" id="pfstate" value="<?= htmlspecialchars(get_pfstate()); ?>" />
		    	<br />
		    	<a href="diag_dump_states.php">Show states</a>
			</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt">MBUF Usage</td>
			<td width="75%" class="listr">
				<?php
					$mbufs_output=`netstat -mb | grep "mbuf clusters in use" | awk '{ print $1 }'`;
					list( $mbufs_current, $mbufs_cache, $mbufs_total, $mbufs_max ) = explode( "/", $mbufs_output);
				?>
				<?= $mbufs_total ?>/<?= $mbufs_max ?>
			</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt">CPU usage</td>
			<td width="75%" class="listr">
				<?php $cpuUsage = "0"; ?>
				<img src="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_blue.gif" height="15" name="cpuwidtha" id="cpuwidtha" width="<?= $cpuUsage; ?>" border="0" align="middle" alt="red bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif" height="15" name="cpuwidthb" id="cpuwidthb" width="<?= (100 - $cpuUsage); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
				&nbsp;
				<input style="border: 0px solid white;" size="30" name="cpumeter" id="cpumeter" value="(Updating in 10 seconds)" />
			</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt">Memory usage</td>
			<td width="75%" class="listr">
				<?php $memUsage = mem_usage(); ?>
				<img src="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_blue.gif" height="15" name="memwidtha" id="memwidtha" width="<?= $memUsage; ?>" border="0" align="middle" alt="red bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif" height="15" name="memwidthb" id="memwidthb" width="<?= (100 - $memUsage); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
				&nbsp;
				<input style="border: 0px solid white;" size="30" name="memusagemeter" id="memusagemeter" value="<?= $memUsage.'%'; ?>" />
			</td>
		</tr>
		<?php if($showswap == true): ?>
		<tr>
			<td width="25%" class="vncellt">SWAP usage</td>
			<td width="75%" class="listr">
				<?php $swapusage = swap_usage(); ?>
				<img src="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_blue.gif" height="15" width="<?= $swapusage; ?>" border="0" align="middle" alt="red bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif" height="15" width="<?= (100 - $swapusage); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
				&nbsp;
				<input style="border: 0px solid white;" size="30" name="swapusagemeter" id="swapusagemeter" value="<?= $swapusage.'%'; ?>" />
			</td>
		</tr>
		<?php endif; ?>
<?php
		if(has_temp()):
?>
		<tr>
			<td width='25%' class='vncellt'>Temperature</td>
			<td width='75%' class='listr'>
				<?php $temp = get_temp(); ?>
				<img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_blue.gif" height="15" name="tempwidtha" id="tempwidtha" width="<?= $temp; ?>" border="0" align="middle" alt="red bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_gray.gif" height="15" name="tempwidthb" id="tempwidthb" width="<?= (100 - $temp); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
				&nbsp;
				<input style="border: 0px solid white;" size="30" name="tempmeter" id="tempmeter" value="<?= $temp."C"; ?>" />
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt">Disk usage</td>
			<td width="75%" class="listr">
				<?php $diskusage = disk_usage(); ?>
				<img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_blue.gif" height="15" width="<?= $diskusage; ?>" border="0" align="middle" alt="red bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_gray.gif" height="15" width="<?= (100 - $diskusage); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
				&nbsp;
				<input style="border: 0px solid white;" size="30" name="diskusagemeter" id="diskusagemeter" value="<?= $diskusage.'%'; ?>" />
			</td>
		</tr>
	</tbody>
</table>
<script type="text/javascript">
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
		jQuery('#updatestatus').html(transport.responseText);
	}
	function swapuname() {
		jQuery('#uname').html("<?php echo php_uname("a"); ?>");
	}
	setTimeout('getstatus()', 4000);
</script>
