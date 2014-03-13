<?php
/* $Id$ */
/*
	system_firmware_settings.php
       	part of pfSense
		Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
        Copyright (C) 2005 Colin Smith

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
	pfSense_BUILDER_BINARIES:	/usr/bin/fetch
	pfSense_MODULE:	firmware
*/

##|+PRIV
##|*IDENT=page-system-firmware-settings
##|*NAME=System: Firmware: Settings page
##|*DESCR=Allow access to the 'System: Firmware: Settings' page.
##|*MATCH=system_firmware_settings.php*
##|-PRIV

require("guiconfig.inc");

if ($_POST) {
	if (!$input_errors) {
		if($_POST['alturlenable'] == "yes") {
			$config['system']['firmware']['alturl']['enable'] = true;
			$config['system']['firmware']['alturl']['firmwareurl'] = $_POST['firmwareurl'];
		} else {
			unset($config['system']['firmware']['alturl']['enable']);
			unset($config['system']['firmware']['alturl']['firmwareurl']);
			unset($config['system']['firmware']['alturl']);
			unset($config['system']['firmware']);
		}
		if($_POST['allowinvalidsig'] == "yes")
			$config['system']['firmware']['allowinvalidsig'] = true;
		else
			unset($config['system']['firmware']['allowinvalidsig']);

		if($_POST['disablecheck'] == "yes")
			$config['system']['firmware']['disablecheck'] = true;
		else
			unset($config['system']['firmware']['disablecheck']);

		if($_POST['synconupgrade'] == "yes")
			$config['system']['gitsync']['synconupgrade'] = true;
		else
			unset($config['system']['gitsync']['synconupgrade']);
		$config['system']['gitsync']['repositoryurl'] = $_POST['repositoryurl'];
		$config['system']['gitsync']['branch'] = $_POST['branch'];

		write_config();
	}
}

$curcfg = $config['system']['firmware'];
$gitcfg = $config['system']['gitsync'];

$pgtitle = array(gettext("System"),gettext("Firmware"),gettext("Settings"));
$closehead = false;
include("head.inc");

exec("/usr/bin/fetch -q -o {$g['tmp_path']}/manifest \"{$g['update_manifest']}\"");
if(file_exists("{$g['tmp_path']}/manifest")) {
	$preset_urls_split = explode("\n", file_get_contents("{$g['tmp_path']}/manifest"));
}

?>
<script type="text/javascript">
//<![CDATA[


function enable_altfirmwareurl(enable_over) {  	 
	if (document.iform.alturlenable.checked || enable_over) { 	 
		document.iform.firmwareurl.disabled = 0; 	 
	} else { 	 
		document.iform.firmwareurl.disabled = 1;
		document.iform.firmwareurl.value = '';
	} 	 
}

//]]>
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc");?>
<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="system_firmware_settings.php" method="post" name="iform" id="iform">
            <?php if ($savemsg) print_info_box($savemsg); ?>
              <table width="100%" border="0" cellpadding="0" cellspacing="0" summary="firmware settings">
	<tr>
		<td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Manual Update"), false, "system_firmware.php");
	$tab_array[] = array(gettext("Auto Update"), false, "system_firmware_check.php");
	$tab_array[] = array(gettext("Updater Settings"), true, "system_firmware_settings.php");
	if($g['hidedownloadbackup'] == false)
		$tab_array[] = array(gettext("Restore Full Backup"), false, "system_firmware_restorefullbackup.php");
	display_top_tabs($tab_array);
?>
		</td>
	</tr>
	<tr><td><div id="mainarea">
	      <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?=gettext("Firmware Branch"); ?></td>
	</tr>
<?php if(is_array($preset_urls_split)): ?>
	<tr>
		<td valign="top" class="vncell"><?=gettext("Default Auto Update URLs"); ?></td>
		<td class="vtable">
			<select name='preseturls' id='preseturls' onchange="firmwareurl.value = preseturls.value; document.iform.firmwareurl.disabled = 0; alturlenable.checked=true; jQuery('#preseturls').parent().effect('highlight');">
					<option></option>
				<?php 
					foreach($preset_urls_split as $pus) {
						$pus_text = explode("\t", $pus);
						if (empty($pus_text[0]))
							continue;
						if (stristr($pus_text[0], php_uname("m")) !== false) {
							$style = " style=\"font-weight: bold\"";
							$yourarch = " (Current architecture)";
						} else {
							$style = "";
							$yourarch = "";
						}
						echo "<option value='{$pus_text[1]}'{$style}>{$pus_text[0]}{$yourarch}</option>";
					}
				?>
			</select>
		<br/><br/><?php echo sprintf(gettext("Entries denoted by \"Current architecture\" match the architecture of your current installation, such as %s. Changing architectures during an upgrade is not recommended, and may require a manual reboot after the update completes."), php_uname("m")); ?>
		</td>
	</tr>
<?php endif; ?>
	<tr>
		<td valign="top" class="vncell"><?=gettext("Firmware Auto Update URL"); ?></td>
		<td class="vtable">
			<input name="alturlenable" type="checkbox" id="alturlenable" value="yes" onclick="enable_altfirmwareurl()" <?php if(isset($curcfg['alturl']['enable'])) echo "checked=\"checked\""; ?> /> <?=gettext("Use an unofficial server for firmware upgrades") ?><br />
			<table summary="alternative Base URL">
			<tr><td><?=gettext("Base URL:"); ?></td><td><input name="firmwareurl" type="text" class="formfld url" id="firmwareurl" size="64" value="<?php if($curcfg['alturl']['firmwareurl']) echo $curcfg['alturl']['firmwareurl']; else echo $g['']; ?>" /></td></tr>
			</table>
			<span class="vexpl">
				<?=gettext("This is where"); ?> <?php echo $g['product_name'] ?> <?=gettext("will check for newer firmware versions when the"); ?> <a href="system_firmware_check.php"><?=gettext("System: Firmware: Auto Update"); ?></a> <?=gettext("page is viewed."); ?>
				<br/>
				<b><?=gettext("NOTE:"); ?></b> <?php printf(gettext("When a custom URL is configured, the system will not verify the image has an official digital signature")); ?>
				</span>
				</td>
	</tr>
	<tr>
		<td colspan="2" class="list" height="12">&nbsp;</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?=gettext("Updates"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Unsigned images"); ?></td>
		<td width="78%" class="vtable">
			<input name="allowinvalidsig" type="checkbox" id="allowinvalidsig" value="yes" <?php if (isset($curcfg['allowinvalidsig'])) echo "checked=\"checked\""; ?> />
			<br />
			<?=gettext("Allow auto-update firmware images with a missing or invalid digital signature to be used."); ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Dashboard check"); ?></td>
		<td width="78%" class="vtable">
			<input name="disablecheck" type="checkbox" id="disablecheck" value="yes" <?php if (isset($curcfg['disablecheck'])) echo "checked=\"checked\""; ?> />
			<br />
			<?=gettext("Disable the automatic dashboard auto-update check."); ?>
		</td>
	</tr>
<?php if(file_exists("/usr/local/bin/git") && $g['platform'] == "pfSense"): ?>
	<tr>
		<td colspan="2" class="list" height="12">&nbsp;</td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?=gettext("Gitsync"); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Auto sync on update"); ?></td>
		<td width="78%" class="vtable">
			<input name="synconupgrade" type="checkbox" id="synconupgrade" value="yes" <?php if (isset($gitcfg['synconupgrade'])) echo "checked=\"checked\""; ?> />
			<br />
			<?=gettext("After updating, sync with the following repository/branch before reboot."); ?>
		</td>
	</tr>
<?php
	if(is_dir("/root/pfsense/pfSenseGITREPO/pfSenseGITREPO")) {
		exec("cd /root/pfsense/pfSenseGITREPO/pfSenseGITREPO && git config remote.origin.url", $output_str);
		if(is_array($output_str) && !empty($output_str[0]))
			$lastrepositoryurl = $output_str[0];
		unset($output_str);
	}
?>
	<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Repository URL"); ?></td>
		<td width="78%" class="vtable">
			<input name="repositoryurl" type="input" class="formfld url" id="repositoryurl" size="64" value="<?php if ($gitcfg['repositoryurl']) echo $gitcfg['repositoryurl']; ?>" />
<?php if($lastrepositoryurl): ?>
			<br />
			<?=sprintf(gettext("The most recently used repository was %s"), $lastrepositoryurl); ?>
			<br />
			<?=gettext("This will be used if the field is left blank."); ?>
<?php endif; ?>
		</td>
	</tr>
<?php
	if(is_dir("/root/pfsense/pfSenseGITREPO/pfSenseGITREPO")) {
		exec("cd /root/pfsense/pfSenseGITREPO/pfSenseGITREPO && git branch", $output_str);
		if(is_array($output_str)) {
			foreach($output_str as $output_line) {
				if(strstr($output_line, '* ')) {
					$lastbranch = substr($output_line, 2);
					break;
				}
			}
		}
		unset($output_str);
	}
?>
	<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Branch name"); ?></td>
		<td width="78%" class="vtable">
			<input name="branch" type="input" class="formfld unknown" id="branch" size="64" value="<?php if ($gitcfg['branch']) echo $gitcfg['branch']; ?>" />
<?php if($lastbranch): ?>
			<br />
			<?=sprintf(gettext("The most recently used branch was %s"), $lastbranch); ?>
<?php else: ?>
			<br />
			<?=gettext("Usually the branch name is master"); ?>
<?php endif; ?>
			<br />
			<?=gettext("Note: Sync will not be performed if a branch is not specified."); ?>
		</td>
	</tr>
<?php endif; ?>
	<tr><td><script type="text/javascript">
	//<![CDATA[
	enable_altfirmwareurl();
	//]]>
	</script></td></tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
                  </td>
                </tr>
              </table></div></td></tr></table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
