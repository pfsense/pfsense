<?php
/*
	diag_nanobsd.php
	Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>
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
	pfSense_BUILDER_BINARIES:	/sbin/mount	/sbin/glabel	/usr/bin/grep	/usr/bin/cut	/usr/bin/head	/bin/cp
	pfSense_BUILDER_BINARIES:	/usr/sbin/boot0cfg	/bin/mkdir	/sbin/fsck_ufs	/sbin/mount	/sbin/sysctl	/bin/dd	/sbin/tunefs
	pfSense_MODULE:	nanobsd
*/

##|+PRIV
##|*IDENT=page-diagnostics-nanobsd
##|*NAME=Diagnostics: NanoBSD
##|*DESCR=Allow access to the 'Diagnostics: NanoBSD' page.
##|*MATCH=diag_nanobsd.php*
##|-PRIV

ini_set('zlib.output_compression', 0);
ini_set('implicit_flush', 1);
ini_set('max_input_time', '9999');

require_once("guiconfig.inc");
require_once("config.inc");

$pgtitle = array(gettext("Diagnostics"), gettext("NanoBSD"));
include("head.inc");

// Survey slice info
global $SLICE, $OLDSLICE, $TOFLASH, $COMPLETE_PATH, $COMPLETE_BOOT_PATH;
global $GLABEL_SLICE, $UFS_ID, $OLD_UFS_ID, $BOOTFLASH;
global $BOOT_DEVICE, $REAL_BOOT_DEVICE, $BOOT_DRIVE, $ACTIVE_SLICE;
nanobsd_detect_slice_info();

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?=$jsevents["body"]["onload"];?>">

<?php include("fbegin.inc"); ?>

<?php

$NANOBSD_SIZE = nanobsd_get_size();

if($_POST['bootslice']) {
	$statusmsg = gettext("Setting slice information, please wait..."); 
	echo <<<EOF
	 	<div id="loading">
			<img src="/themes/{$g['theme']}/images/misc/loader.gif"> 
			$statusmsg
			<p/>&nbsp;
		</div>
EOF;
	nanobsd_switch_boot_slice();
	$savemsg = gettext("The boot slice has been set to") . " " . nanobsd_get_active_slice();
	// Survey slice info
	nanobsd_detect_slice_info();

}

if($_POST['destslice']) {
	$statusmsg = gettext("Duplicating slice.  Please wait, this will take a moment...");
echo <<<EOF
 	<div id="loading">
		<img src="/themes/{$g['theme']}/images/misc/loader.gif">
		$statusmsg
		<p/>&nbsp;
	</div>
EOF;
	if(nanobsd_clone_slice($_POST['destslice'])) {
		$savemsg = gettext("The slice has been duplicated.") . "<p/>" . gettext("If you would like to boot from this newly duplicated slice please set it using the bootup information area.");
	} else {
		$savemsg = gettext("There was an error while duplicating the slice.  Operation aborted.");
	}
	// Re-Survey slice info
	nanobsd_detect_slice_info();
}

if ($_POST['changero']) {
	if (is_writable("/")) {
		conf_mount_ro();
	} else {
		conf_mount_rw();
	}
}

if ($_POST['setrw']) {
	conf_mount_rw();
	if (isset($_POST['nanobsd_force_rw']))
		$config['system']['nanobsd_force_rw'] = true;
	else
		unset($config['system']['nanobsd_force_rw']);

	write_config("Changed Permanent Read/Write Setting");
	conf_mount_ro();
}

if ($savemsg)
	print_info_box($savemsg)

?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
			<!-- tabs here if you want them -->
		</td>
	</tr>
	<tr>
		<td id="mainarea">
			<div class="tabcont">
				<span class="vexpl">
					<span class="red">
						<strong><?=gettext("NOTE:")?>&nbsp;</strong>
					</span>
					<?=gettext("The options on this page are intended for use by advanced users only.")?>
					<br />&nbsp;
				</span>
				<p/>
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Bootup information");?></td>
					</tr>
					<tr>						
						<td width="22%" valign="top" class="vncell"><?=gettext("NanoBSD Image size");?></td>
						<td width="78%" class="vtable">
							<?php echo $NANOBSD_SIZE; ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Bootup");?></td>
						<td width="78%" class="vtable">
							<form action="diag_nanobsd.php" method="post" name="iform">
								<?=gettext("Bootup slice is currently:");?> <?php echo $ACTIVE_SLICE; ?>
								<br /><br /><?=gettext("This will switch the bootup slice to the alternate slice.");?>
								<br />
								<input type='hidden' name='bootslice' value='switch'>
								<input type='submit' value='Switch Slice'></form>
						</td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="">&nbsp;</td>
					</tr>					
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Media Read/Write Status");?></td>
					</tr>
					<tr>
						<td valign="top" class="vncell">Current Read/Write Status:</td>
						<td valign="top" class="vncell">
							<form action="diag_nanobsd.php" method="post" name="iform">
							<?php if (is_writable("/")) {
								$refcount = refcount_read(1000);
								/* refcount_read returns -1 when shared memory section does not exist */
								if ($refcount == 1 || $refcount == -1) {
									$refdisplay = "";
								} else {
									$refdisplay = " (reference count " . $refcount . ")";
								}
								echo gettext("Read/Write") . $refdisplay;
								if (!isset($config['system']['nanobsd_force_rw']))
									echo "<br /><input type='submit' name='changero' value='" . gettext("Switch to Read-Only") . "'>";
							} else {
								echo gettext("Read-Only");
								if (!isset($config['system']['nanobsd_force_rw']))
									echo "<br /><input type='submit' name='changero' value='" . gettext("Switch to Read/Write") . "'>";
							} ?>
							</form>
							<br /><?php echo gettext("NOTE: This setting is only temporary, and can be switched dynamically in the background."); ?>
						</td>
					</tr>
					<tr>
						<td valign="top" class="vncell">Permanent Read/Write:</td>
						<td valign="top" class="vncell">
							<form action="diag_nanobsd.php" method="post" name="iform">
								<input type="checkbox" name="nanobsd_force_rw" <?php if (isset($config['system']['nanobsd_force_rw'])) echo "checked"; ?>> <?php echo gettext("Keep media mounted read/write at all times.") ?>
								<br /><input type='submit' name='setrw' value='<?php echo gettext("Save") ?>'>
							</form>
						</td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="">&nbsp;</td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Duplicate bootup slice to alternate");?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Duplicate bootup slice");?></td>
						<td width="78%" class="vtable">
							<form action="diag_nanobsd.php" method="post" name="iform">
								<?=gettext("Destination slice:");?>
								<select name='destslice'>
									<option value='<?php echo $COMPLETE_PATH; ?>'>
										<?php echo "{$COMPLETE_BOOT_PATH} -> {$TOFLASH}"; ?>
									</option>
								</select>
								<br />
								<?=gettext("This will duplicate the bootup slice to the alternate slice.  Use this if you would like to duplicate the known good working boot partition to the alternate.");?>
						</td>
					</tr>
					<tr>
						<td valign="top" class="">&nbsp;</td><td><br /><input type='submit' value='Duplicate slice'></form></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="">&nbsp;</td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Periodic Data Backup");?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("RRD/DHCP Backup");?></td>
						<td width="78%" class="vtable">
							<?=gettext("These options have been relocated to");?> <a href="system_advanced_misc.php"><?=gettext("System > Advanced, Miscellaneous tab")?></a>.
						</td>
					</tr>
<?php if(file_exists("/conf/upgrade_log.txt")): ?>
					<tr>
						<td colspan="2" valign="top" class="">&nbsp;</td>
					</tr>					
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("View upgrade log");?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("View previous upgrade log");?></td>
						<td width="78%" class="vtable">
						<?php
							if ($_POST['viewupgradelog']) {
								echo "<textarea name='log' cols='80' rows='40'>";
								echo str_ireplace("pfsense", $g['product_name'], file_get_contents("/conf/upgrade_log.txt"));
								echo "\nFile list:\n";
								echo str_ireplace("pfsense", $g['product_name'], file_get_contents("/conf/file_upgrade_log.txt"));
								echo "\nMisc log:\n";
								echo str_ireplace("pfsense", $g['product_name'], file_get_contents("/conf/firmware_update_misc_log.txt"));
								echo "\nfdisk/bsdlabel log:\n";
								echo str_ireplace("pfsense", $g['product_name'], file_get_contents("/conf/fdisk_upgrade_log.txt"));
								echo "</textarea>";
							} else {
								echo "<form action='diag_nanobsd.php' method='post' name='iform'>";
								echo "<input type='submit' name='viewupgradelog' value='View upgrade log'>";
							}
						?>
						</td>
					</tr>
<?php endif; ?>
					<tr>
						<td colspan="2" valign="top" class="">&nbsp;</td>
					</tr>					
				</table>
			</div>
		</td>
	</tr>
</table>
<?php require("fend.inc"); ?>
</body>
</html>

<?php

// Clear the loading indicator
echo "<script type=\"text/javascript\">";
echo "jQuery('#loading').html('');";
echo "</script>";	

?>
