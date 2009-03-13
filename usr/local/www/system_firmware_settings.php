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
		write_config();
	}
}

$curcfg = $config['system']['firmware'];

$pgtitle = array("System","Firmware","Settings");
include("head.inc");

exec("fetch -q -o /tmp/manifest \"{$g['update_manifest']}\"");
if(file_exists("/tmp/manifest")) {
	$preset_urls_split = split("\n", file_get_contents("/tmp/manifest"));
}

?>
<script language="JavaScript">
<!--


function enable_altfirmwareurl(enable_over) {  	 
	if (document.iform.alturlenable.checked || enable_over) { 	 
		document.iform.firmwareurl.disabled = 0; 	 
	} else { 	 
		document.iform.firmwareurl.disabled = 1;
		document.iform.firmwareurl.value = '';
	} 	 
}

// -->
</script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc");?>
<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="system_firmware_settings.php" method="post" name="iform" id="iform">
            <?php if ($savemsg) print_info_box($savemsg); ?>
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Manual Update", false, "system_firmware.php");
	$tab_array[1] = array("Auto Update", false, "system_firmware_check.php");
	$tab_array[2] = array("Updater Settings", true, "system_firmware_settings.php");
	display_top_tabs($tab_array);
?>
		</td>
	</tr>
	<tr><td><div id=mainarea>
	      <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td colspan="2" valign="top" class="listtopic">Firmware Branch</td>
	</tr>
<?php if(is_array($preset_urls_split)): ?>
	<tr>
		<td valign="top" class="vncell">Default Auto Update URLs</td>
		<td class="vtable">
			<select name='preseturls' id='preseturls' onChange="firmwareurl.value = preseturls.value; document.iform.firmwareurl.disabled = 0; alturlenable.checked=true; new Effect.Highlight(this.parentNode, { startcolor: '#ffff99', endcolor: '#fffffff' });">
					<option></option>
				<?php 
					foreach($preset_urls_split as $pus) {
						$pus_text = split("\t", $pus);
						if($pus_text[0])
							echo "<option value='{$pus_text[1]}'>{$pus_text[0]}</option>";
					}
				?>
			</select>
		</td>
	</tr>
<?php endif; ?>
	<tr>
		<td valign="top" class="vncell">Firmware Auto Update URL</td>
		<td class="vtable">
			<input name="alturlenable" type="checkbox" id="alturlenable" value="yes" onClick="enable_altfirmwareurl()" <?php if(isset($curcfg['alturl']['enable'])) echo "checked"; ?>> Use a different URL server for firmware upgrades other than <?php echo $g['product_website']; ?><br>
			<table>
			<tr><td>Base URL:</td><td><input name="firmwareurl" type="input" class="formfld url" id="firmwareurl" size="64" value="<?php if($curcfg['alturl']['firmwareurl']) echo $curcfg['alturl']['firmwareurl']; else echo $g['']; ?>"></td></tr>
			</table>
			<span class="vexpl">
				This is where <?php echo $g['product_name'] ?> will check for newer firmware versions when the <a href="system_firmware_check.php">System: Firmware: Auto Update</a> page is viewed.
				<p/>
				<b>NOTE:</b> When a custom URL is enabled the system will not verify the digital signature from <?php echo $g['product_website'] ?>.
				</span>
				</td>
	</tr>
	<script>enable_altfirmwareurl();</script>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="Submit" type="submit" class="formbtn" value="Save">
                  </td>
                </tr>
              </table></div></td></tr></table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
