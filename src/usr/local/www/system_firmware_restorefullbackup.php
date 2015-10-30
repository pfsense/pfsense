<?php
/* $Id$ */
/*
	system_firmware_restorefullbackup.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Originally from m0n0wall, copyright 2004 Manuel Kasper (BSD 2 clause)
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
 *	====================================================================
 *
 */
/*
	pfSense_BUILDER_BINARIES:	/etc/rc.restore_full_backup
	pfSense_MODULE: backup
*/

##|+PRIV
##|*IDENT=page-diagnostics-restore-full-backup
##|*NAME=Diagnostics: Restore full backup
##|*DESCR=Allow access to the 'Diagnostics: Restore Full Backup' page.
##|*MATCH=system_firmware_restorefullbackup.php
##|-PRIV

// Don't really restore or reboot while testing. Should be 'false' for production of course
define(DEBUG, false);

/* Allow additional execution time 0 = no limit. */
ini_set('max_execution_time', '0');
ini_set('max_input_time', '0');

require_once("functions.inc");
require("guiconfig.inc");
require_once("filter.inc");
require_once("shaper.inc");

if ($_POST['overwriteconfigxml']) {
	touch("/tmp/do_not_restore_config.xml");
}

if ($_GET['backupnow']) {
	mwexec_bg("/etc/rc.create_full_backup");
}

if($_POST['downloadbackup']) {
	$filename = basename($_POST['downloadbackup']);
	
	if(DEBUG)
		print_info_box('DEBUG: Simulating download of ' . htmlspecialchars($filename));
	else {
		$path = "/root/{$filename}";
		if(file_exists($path)) {
			session_write_close();
			ob_end_clean();
			session_cache_limiter('public');
			//$fd = fopen("/root/{$filename}", "rb");
			$filesize = filesize("/root/{$filename}");
			header("Cache-Control: ");
			header("Pragma: ");
			header("Content-Type: application/octet-stream");
			header("Content-Length: " .(string)(filesize($path)) );
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			header("Content-Transfer-Encoding: binary\n");
			if($file = fopen("/root/{$filename}", 'rb')){
				while( (!feof($file)) && (connection_status()==0) ){
					print(fread($file, 1024*8));
					flush();
				}
				fclose($file);
			}

			exit;
		}
	}
}
else if ($_POST['deletefile']) {
	$filename = basename($_POST['deletefile']);
	if(DEBUG)
		print_info_box('DEBUG: Simulating deletion of ' . htmlspecialchars($filename));
	else {
		if(file_exists("/root/{$filename}") && (preg_match("/pfSense-full-backup-\d+-\d+\.tgz/", $filename) == 1)) {
			unlink("/root/" . $filename);
			$savemsg = htmlspecialchars($filename) . " " . gettext("has been deleted.");
		} else {
			$savemsg = htmlspecialchars($filename) . " " . gettext("has not been been deleted (invalid backup file or file does not exist).");
		}
	}
}
else if ($_POST['restorefile']) {
	$filename = basename($_POST['restorefile']);
	if(DEBUG)
	   print_info_box('DEBUG: Restoration of ' . $filename . ' simulated');
	else {
		if(file_exists("/root/{$filename}") && (preg_match("/pfSense-full-backup-\d+-\d+\.tgz/", $filename) == 1)) {
			mwexec_bg("/etc/rc.restore_full_backup /root/" . escapeshellcmd($filename));
			$savemsg = gettext("The firewall is currently restoring") . " " . htmlspecialchars($filename);
		} else {
			$savemsg = htmlspecialchars($filename) . " " . gettext("has not been been restored (invalid backup file or file does not exist).");
		}
	}
}

$pgtitle = array(gettext("Diagnostics"), gettext("Restore full backup"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg);

if (is_subsystem_dirty('restore')) {
 ?>
	<form action="reboot.php" method="post">
		<input name="Submit" type="hidden" value="Yes" />
<?php	print_info_box(gettext("The firewall configuration has been changed. The firewall is now rebooting.")) ?>
	</form>
<?php
}
?>

<?php
$tab_array = array();
$tab_array[] = array(gettext("Manual Update"), false, "system_firmware.php");
$tab_array[] = array(gettext("Auto Update"), false, "system_firmware_check.php");
$tab_array[] = array(gettext("Updater Settings"), false, "system_firmware_settings.php");
if($g['hidedownloadbackup'] == false)
	$tab_array[] = array(gettext("Restore Full Backup"), true, "system_firmware_restorefullbackup.php");

display_top_tabs($tab_array);
?>

<form action="system_firmware_restorefullbackup.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Available backup files')?></h2></div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="table table-hover table-striped table-condensed">
					<thead>
						<tr>
							<th><?=gettext("File to restore")?></th>
							<th><?=gettext("Date")	?></th>
							<th><?=gettext("Size")	?></th>
							<th><?=gettext("Action")?></th>
						</tr>
					</thead>
					<tbody>
<?php
					$home = getcwd();
					chdir("/root");
					$available_restore_files = glob("pfSense-full-backup-*");
					$counter = 0;
					foreach($available_restore_files as $arf) {
						$counter++;
						$size = exec("gzip -l /root/$arf | grep -v compressed | awk '{ print $2 }'");
?>
						<tr>
							<td>
								<input type="radio" class="radio-inline" name="restorefile" value="<?=$arf?>" /> <?=$arf?>
							</td>
							<td>
								<?=date ("F d Y H:i:s", filemtime($arf))?>
							</td>
							<td>
								<?=format_bytes($size)?>
							</td>
							<td>
								<button class="btn btn-xs btn-danger" type="submit" name="deletefile" value="<?=$arf?>" title="Delete backup file">Delete</button>
								<button class="btn btn-xs btn-default" type="submit" name="downloadbackup" value="<?=$arf?>" title="Download backup file">Download</button>
							</td>
						</tr>
<?php
					} // e-o-foreach backup file

					chdir($home);  // Je me souvien
?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
<?php
	if($counter == 0)
		print_info_box(gettext("Could not locate any previous backups."));
	else {
?>
		<p><input type="checkbox" name="overwriteconfigxml" id="overwriteconfigxml" checked="checked"/> <?=gettext(" When checked, DO NOT restore the config.xml file."); ?></p>
		<p><button name="Restore" type="submit" class="btn btn-danger" id="restore" value="<?=gettext("Restore")?>"><?=gettext("Restore")?></button></p>
<?php
	}
?>
</form>

<script type="text/javascript">
//<![CDATA[
encrypt_change();
decrypt_change();
//]]>
</script>

<?php

if (is_subsystem_dirty('restore')) {
	system_reboot();
}

include("foot.inc");?>