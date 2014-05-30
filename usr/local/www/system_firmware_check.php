<?php
/* $Id$ */
/*
	system_firmware.php
	Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
	pfSense_MODULE:	firmware
*/

##|+PRIV
##|*IDENT=page-system-firmware-autoupdate
##|*NAME=System: Firmware: Auto Update page
##|*DESCR=Allow access to the 'System: Firmware: Auto Update' page.
##|*MATCH=system_firmware_check.php*
##|-PRIV

$d_isfwfile = 1;
require("guiconfig.inc");
require_once("pfsense-utils.inc");

$curcfg = $config['system']['firmware'];
$pgtitle=array(gettext("System"), gettext("Firmware"), gettext("Auto Update"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>

<form action="system_firmware_auto.php" method="post">
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="firmware check">
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
			<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="">
				<tr>
					<td align="center">
						<!-- progress bar -->
						<table style="height:15;colspacing:0" width="420" border="0" cellpadding="0" cellspacing="0" summary="images">

							<tr>
								<td style="background:url('./themes/<?=$g['theme'];?>/images/misc/bar_left.gif')" height="15" width="5"></td>
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
						<!-- command output box -->
						<script type="text/javascript">
						//<![CDATA[
						window.onload=function(){
							document.getElementById("output").wrap='hard';
						}
						//]]>
						</script>
						<textarea style="border:1;bordercolordark:#000000;bordercolorlight:#000000" cols="90" rows="9" name="output" id="output"></textarea>
						<div id="backupdiv" style="visibility:hidden">
							<?php if ($g['hidebackupbeforeupgrade'] === false): ?>
							<br /><input type="checkbox" name="backupbeforeupgrade" id="backupbeforeupgrade" /><?=gettext("Perform full backup prior to upgrade");?>
							<?php endif; ?>
						</div>
						<input id='invokeupgrade' style='visibility:hidden' type="submit" value="<?=gettext("Invoke Auto Upgrade"); ?>" />
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<p>

<?php

/* Define necessary variables. */
if(isset($curcfg['alturl']['enable']))
	$updater_url = "{$config['system']['firmware']['alturl']['firmwareurl']}";
else
	$updater_url = $g['update_url'];
$needs_system_upgrade = false;
$static_text .= gettext("Downloading new version information...");

$nanosize = "";
if ($g['platform'] == "nanobsd") {
	if (file_exists("/etc/nano_use_vga.txt"))
		$nanosize = "-nanobsd-vga-";
	else
		$nanosize = "-nanobsd-";

	$nanosize .= strtolower(trim(file_get_contents("/etc/nanosize.txt")));
}

if(download_file_with_progress_bar("{$updater_url}/version{$nanosize}", "/tmp/{$g['product_name']}_version", 'read_body', 5, 5) === true)
	$remote_version = trim(@file_get_contents("/tmp/{$g['product_name']}_version"));
$static_text .= gettext("done") . "\\n";
if (!$remote_version) {
	$static_text .= gettext("Unable to check for updates.") . "\\n";
	if(isset($curcfg['alturl']['enable']))
		$static_text .= gettext("Could not contact custom update server.") . "\\n";
	else
		$static_text .= sprintf(gettext('Could not contact %1$s update server %2$s%3$s'), $g['product_name'], $updater_url, "\\n");
} else {
	$static_text .= gettext("Obtaining current version information...");
	update_output_window($static_text);

	$current_installed_buildtime = trim(file_get_contents("/etc/version.buildtime"));
	$current_installed_version = trim(file_get_contents("/etc/version"));

	$static_text .= "done\\n";
	update_output_window($static_text);

	if (pfs_version_compare($current_installed_buildtime, $current_installed_version, $remote_version) == -1) {
		$needs_system_upgrade = true;
	} else {
		$static_text .= "\\n" . gettext("You are on the latest version.") . "\\n";
	}
}

update_output_window($static_text);
if ($needs_system_upgrade == false) {
	echo "</p>";
	echo "</form>";
	require("fend.inc");
	echo "</body>";
	echo "</html>";
	exit;
}

echo "\n<script type=\"text/javascript\">\n";
echo "//<![CDATA[\n";
echo "jQuery('#invokeupgrade').css('visibility','visible');\n";
echo "//]]>\n";
echo "</script>\n";
echo "\n<script type=\"text/javascript\">\n";
echo "//<![CDATA[\n";
echo "jQuery('#backupdiv').css('visibility','visible');\n";
echo "//]]>\n";
echo "</script>\n";

$txt  = gettext("A new version is now available") . "\\n\\n";
$txt .= gettext("Current version") .": ". $current_installed_version . "\\n";
if ($g['platform'] == "nanobsd") {
	$txt .= "  " . gettext("NanoBSD Size") . " : " . trim(file_get_contents("/etc/nanosize.txt")) . "\\n";
}
$txt .= "       " . gettext("Built On") .": ".  $current_installed_buildtime . "\\n";
$txt .= "    " . gettext("New version") .": ".  htmlspecialchars($remote_version, ENT_QUOTES | ENT_HTML401). "\\n\\n";
$txt .= "  " . gettext("Update source") .": ".  $updater_url . "\\n";
update_output_window($txt);
?>
</p>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
