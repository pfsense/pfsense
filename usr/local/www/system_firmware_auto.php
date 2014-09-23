<?php
/* $Id$ */
/*
	system_firmware_auto.php
	Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2005 Scott Ullrich

	Based originally on system_firmware.php
	(C)2003-2004 Manuel Kasper
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
/*
	pfSense_BUILDER_BINARIES:	/usr/bin/tar	/usr/bin/nohup	/bin/cat	/sbin/sha256
	pfSense_MODULE:	firmware
*/

##|+PRIV
##|*IDENT=page-system-firmware-checkforupdate
##|*NAME=System: Firmware: Check For Update page
##|*DESCR=Allow access to the 'System: Firmware: Check For Update' page.
##|*MATCH=system_firmware_auto.php*
##|-PRIV

$nocsrf = true;

require("guiconfig.inc");
require_once("pfsense-utils.inc");

$curcfg = $config['system']['firmware'];

if(isset($curcfg['alturl']['enable']))
	$updater_url = "{$config['system']['firmware']['alturl']['firmwareurl']}";
else
	$updater_url = $g['update_url'];

if($_POST['backupbeforeupgrade'])
	touch("/tmp/perform_full_backup.txt");

$closehead = false;
$pgtitle = array(gettext("Diagnostics"),gettext("Firmware"),gettext("Auto Update"));
include("head.inc");

?>

<meta http-equiv="Content-Type" content="text/html; charset=<?=system_get_language_codeset();?>" />
<link href="gui.css" rel="stylesheet" type="text/css" />
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>

<form action="system_firmware_auto.php" method="post">
<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="firmware auto-check">
	<tr>
		<td>
		<?php
			$tab_array = array();
			$tab_array[] = array(gettext("Manual Update"), false, "system_firmware.php");
			$tab_array[] = array(gettext("Auto Update"), true, "system_firmware_check.php");
			$tab_array[] = array(gettext("Updater Settings"), false, "system_firmware_settings.php");
			if($g['hidedownloadbackup'] == false)
				$tab_array[] = array(gettext("Restore Full Backup"), false, "system_firmware_restorefullbackup.php");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
	<tr>
		<td class="tabcont">
			<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="outer">
				<tr>
					<td class="tabcont">
						<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="inner">
							<tr>
								<td align="center">
									<table style="height:15;colspacing:0" width="420" border="0" cellpadding="0" cellspacing="0" summary="images">

										<tr>
											<td style="background:url('./themes/<?=$g['theme'];?>/images/misc/bar_left.gif')" height="15" width="5">	</td>
											<td>
											<table id="progholder" style="height:15;colspacing:0" width="410" border="0" cellpadding="0" cellspacing="0" summary="">
												<tr><td style="background:url('./themes/<?=$g['theme'];?>/images/misc/bar_gray.gif')" valign="top" align="left">
												<img src="./themes/<?=$g['theme'];?>/images/misc/bar_blue.gif" width="0" height="15" name="progressbar" id="progressbar" alt="" />
												</td></tr>
											</table>
											</td>
											<td style="background:url('./themes/<?=$g['theme'];?>/images/misc/bar_right.gif')" height="15" width="5"></td>
										</tr>
									</table>
									<br />
									<script type="text/javascript">
									//<![CDATA[
									window.onload=function(){
										document.getElementById("status").wrap='hard';
										document.getElementById("output").wrap='hard';
									}
									//]]>
									</script>
									<!-- status box -->
									<textarea cols="90" rows="1" name="status" id="status"><?=gettext("Beginning firmware upgrade"); ?>.</textarea>
									<br />
									<!-- command output box -->
									<textarea cols="90" rows="25" name="output" id="output"></textarea>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</form>

<?php include("fend.inc"); ?>

<?php

update_status(gettext("Downloading current version information") . "...");
$nanosize = "";
if ($g['platform'] == "nanobsd") {
	if (file_exists("/etc/nano_use_vga.txt"))
		$nanosize = "-nanobsd-vga-";
	else
		$nanosize = "-nanobsd-";

	$nanosize .= strtolower(trim(file_get_contents("/etc/nanosize.txt")));
}

@unlink("/tmp/{$g['product_name']}_version");
download_file_with_progress_bar("{$updater_url}/version{$nanosize}", "/tmp/{$g['product_name']}_version");
$latest_version = str_replace("\n", "", @file_get_contents("/tmp/{$g['product_name']}_version"));
if(!$latest_version) {
	update_output_window(gettext("Unable to check for updates."));
	require("fend.inc");
	exit;
} else {
	$current_installed_buildtime = trim(file_get_contents("/etc/version.buildtime"));
	$current_installed_version = trim(file_get_contents("/etc/version"));
	$latest_version = trim(@file_get_contents("/tmp/{$g['product_name']}_version"));
	$latest_version_pfsense = strtotime($latest_version);
	if(!$latest_version) {
		update_output_window(gettext("Unable to check for updates."));
		require("fend.inc");
		exit;
	} else {
		if (pfs_version_compare($current_installed_buildtime, $current_installed_version, $latest_version) == -1) {
			update_status(gettext("Downloading updates") . "...");
			conf_mount_rw();
			if ($g['platform'] == "nanobsd") {
				$update_filename = "latest{$nanosize}.img.gz";
			} else {
				$update_filename = "latest.tgz";
			}
			$status = download_file_with_progress_bar("{$updater_url}/{$update_filename}", "{$g['upload_path']}/latest.tgz", "read_body_firmware");
			$status = download_file_with_progress_bar("{$updater_url}/{$update_filename}.sha256", "{$g['upload_path']}/latest.tgz.sha256");
			conf_mount_ro();
			update_output_window("{$g['product_name']} " . gettext("download complete."));
		} else {
			update_output_window(gettext("You are on the latest version."));
			require("fend.inc");
			exit;
		}
	}
}

/* launch external upgrade helper */
$external_upgrade_helper_text = "/etc/rc.firmware ";

if($g['platform'] == "nanobsd")
	$external_upgrade_helper_text .= "pfSenseNanoBSDupgrade ";
else
	$external_upgrade_helper_text .= "pfSenseupgrade ";

$external_upgrade_helper_text .= "{$g['upload_path']}/latest.tgz";

$downloaded_latest_tgz_sha256 = str_replace("\n", "", `/sbin/sha256 -q {$g['upload_path']}/latest.tgz`);
$upgrade_latest_tgz_sha256 = str_replace("\n", "", `/bin/cat {$g['upload_path']}/latest.tgz.sha256 | awk '{ print $4 }'`);

$sigchk = 0;

if(!isset($curcfg['alturl']['enable']))
	$sigchk = verify_digital_signature("{$g['upload_path']}/latest.tgz");

$exitstatus = 0;
if ($sigchk == 1) {
	$sig_warning = gettext("The digital signature on this image is invalid.");
	$exitstatus = 1;
} else if ($sigchk == 2) {
	$sig_warning = gettext("This image is not digitally signed.");
	if (!isset($config['system']['firmware']['allowinvalidsig']))
		$exitstatus = 1;
} else if (($sigchk >= 3)) {
	$sig_warning = gettext("There has been an error verifying the signature on this image.");
	$exitstatus = 1;
}

if ($exitstatus) {
	update_status($sig_warning);
	update_output_window(gettext("Update cannot continue.  You can disable this check on the Updater Settings tab."));
	require("fend.inc");
	exit;
} else if ($sigchk == 2) {
	update_status("Upgrade in progress...");
	update_output_window("\n" . gettext("Upgrade Image does not contain a signature but the system has been configured to allow unsigned images. One moment please...") . "\n");
}

if (!verify_gzip_file("{$g['upload_path']}/latest.tgz")) {
	update_status(gettext("The image file is corrupt."));
	update_output_window(gettext("Update cannot continue"));
	if (file_exists("{$g['upload_path']}/latest.tgz")) {
		conf_mount_rw();
		unlink("{$g['upload_path']}/latest.tgz");
		conf_mount_ro();
	}
	require("fend.inc");
	exit;
}

if($downloaded_latest_tgz_sha256 <> $upgrade_latest_tgz_sha256) {
	update_status(gettext("Downloading complete but sha256 does not match."));
	update_output_window(gettext("Auto upgrade aborted.") . "  \n\n" . gettext("Downloaded SHA256") . ": " . $downloaded_latest_tgz_sha256 . "\n\n" . gettext("Needed SHA256") . ": " . $upgrade_latest_tgz_sha256);
} else {
	update_output_window($g['product_name'] . " " . gettext("is now upgrading.") . "\\n\\n" . gettext("The firewall will reboot once the operation is completed."));
	echo "\n<script type=\"text/javascript\">";
	echo "\n//<![CDATA[";
	echo "\ndocument.progressbar.style.visibility='hidden';";
	echo "\n//]]>";
	echo "\n</script>";
	mwexec_bg($external_upgrade_helper_text);
}

/*
	Helper functions
*/

function read_body_firmware($ch, $string) {
	global $fout, $file_size, $downloaded, $counter, $version, $latest_version, $current_installed_version;
	$length = strlen($string);
	$downloaded += intval($length);
	$downloadProgress = round(100 * (1 - $downloaded / $file_size), 0);
	$downloadProgress = 100 - $downloadProgress;
	$a = $file_size;
	$b = $downloaded;
	$c = $downloadProgress;
	$text  = "  " . gettext("Auto Update Download Status") . "\\n";
	$text .= "----------------------------------------------------\\n";
	$text .= "  " . gettext("Current Version") . " : {$current_installed_version}\\n";
	$text .= "  " . gettext("Latest Version") . "  : {$latest_version}\\n";
	$text .= "  " . gettext("File size") . "       : {$a}\\n";
	$text .= "  " . gettext("Downloaded") . "      : {$b}\\n";
	$text .= "  " . gettext("Percent") . "         : {$c}%\\n";
	$text .= "----------------------------------------------------\\n";
	$counter++;
	if($counter > 150) {
		update_output_window($text);
		update_progress_bar($downloadProgress);
		$counter = 0;
	}
	fwrite($fout, $string);
	return $length;
}

?>

</body>
</html>
