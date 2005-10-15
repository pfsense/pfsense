#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	system_firmware_settings.php
        part of pfSense
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

require("guiconfig.inc");

if ($_POST) {
	/* input validation */
	if($_POST['firmwareurl'] && !is_string($_POST['firmwareurl'])) {
		$input_errors[] = "The base XMLRPC URL must be a string.";
	}
	if($_POST['firmwarepath'] && !is_string($_POST['firmwarepath'])) {
		$input_errors[] = "The XMLRPC path must be a string.";
	}
	if (!$input_errors) {
		$config['system']['firmware']['branch'] = $_POST['branch'];
		if($_POST['alturlenable'] == "yes") {
			$config['system']['firmware']['alturl']['enable'] = "";
			$config['system']['firmware']['alturl']['firmwareurl'] = $_POST['firmwareurl'];
			$config['system']['firmware']['alturl']['firmwarepath'] = $_POST['firmwarepath'];
		} else {
			unset($config['system']['firmware']['alturl']['enable']);
		}
		write_config();
	}
}

$curcfg = $config['system']['firmware'];

$pgtitle = "System: Firmware: Settings";
include("head.inc");

?>

<script language="JavaScript">
<!--
var systemdescs=new Array(4);
systemdescs[0]="This patch system uses a combination of unified and binary diffs. This system requires the least bandwidth, but is less forgiving of errors.";
systemdescs[1]="This patch system uses tar files to update the system. This requires the most bandwidth, but is more reliable.";
systemdescs[2]="This patch system uses tar files for the kernel and base system, and unified diffs for other components.";

var branchinfo=new Array(4);
branchinfo[0]="The stable branch contains only those updates believed to be stable by the developers.";
branchinfo[1]="This branch contains both stable updates as well as those believed to be fairly stable.";
branchinfo[2]="This branch contains all released updates, regardless of stability.";

function update_description(itemnum) {
        document.forms[0].branchinfo.value=branchinfo[itemnum];
}

function enable_altfirmwareurl(enable_over) {  	 
	if (document.iform.alturlenable.checked || enable_over) { 	 
		document.iform.firmwareurl.disabled = 0; 	 
		document.iform.firmwarepath.disabled = 0; 	 
	} else { 	 
		document.iform.firmwareurl.disabled = 1; 	 
		document.iform.firmwarepath.disabled = 1; 	 
	} 	 
}

// -->
</script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc");?>
<p class="pgtitle"><?=$pgtitle?></p>
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
	<tr>
                  <td valign="top" class="vncell">Firmware Branch</td>
                  <td class="vtable">
			<select onChange="update_description(this.selectedIndex);" name="branch" id="branch">
			<option value="stable"<?php if($curcfg['branch']=="stable") echo " SELECTED"; ?>>Stable</option>
			<option value="beta"<?php if($curcfg['branch']=="beta") echo " SELECTED"; ?>>Beta</option>
			<option value="alpha"<?php if($curcfg['branch']=="alpha") echo " SELECTED"; ?>>Alpha</option>
			</select>
			<br>
			<textarea cols="60" rows="2" id="branchinfo" name="branchinfo"style="border:1px dashed #000066; background-color: #ffffff; color: #000000; font-size: 8pt;">
			</textarea>
			<script language="javascript">
			update_description(document.forms[0].branch.selectedIndex);
			</script>
			<br><span class="vexpl">Select the update branch you would like this system to track.</td>
	</tr>
	<tr>
		<td valign="top" class="vncell">Firmware XMLRPC URL</td>
		<td class="vtable">
			<input name="alturlenable" type="checkbox" id="alturlenable" value="yes" onClick="enable_altfirmwareurl()" <?php if(isset($curcfg['alturl']['enable'])) echo "checked"; ?>> Use a different XMLRPC server for firmware upgrades<br>
			<table>
			<tr><td>Base URL:</td><td><input name="firmwareurl" type="input" id="firmwareurl" size="64" value="<?php if($curcfg['alturl']['firmwareurl']) echo $curcfg['alturl']['firmwareurl']; else echo $g['xmlrpcbaseurl']; ?>"></td></tr>
			<tr><td>Path:</td><td><input name="firmwarepath" type="input" id="firmwarepath" size="64" value="<?php if($curcfg['alturl']['firmwarepath']) echo $curcfg['alturl']['firmwarepath']; else echo $g['xmlrpcpath']; ?>"></td></tr>
			</table>
			<span class="vexpl">This is where pfSense will check for newer firmware versions when the <a href="system_firmware_check.php">System: Firmware: Auto Update</a> page is viewed.</span></td>
	</tr>
	<script>enable_altfirmwareurl();</script>
<!--
	<tr>
                  <td width="22%" valign="top" class="vncell">Update Preference</td>
                  <td width="78%" class="vtable">
			<select onChange="update_description(branchinfo, this.selectedIndex);" name="branch" id="branch">
			<option value="patches"<?php if($curcfg['updates']=="diffs") echo " SELECTED"; ?>>Patches</option>
			<option value="full"<?php if($curcfg['updates']=="full") echo " SELECTED"; ?>>Full Updates</option>
			<option value="combination"<?php if($config['updates']=="combination") echo " SELECTED"; ?>>Combination</option>
			</select>
			<textarea cols="60" rows="2" id="info" name="info"style="border:1px dashed #000066; background-color: #ffffff; color: #000000; font-size: 8pt;">
			</textarea>
			<script language="javascript">
			update_description(branchinfo, document.forms[0].optimization.selectedIndex);
			</script>
			<br><span class="vexpl"><b>Select the update branch you would like this system to track</b></td>
                </tr>
-->
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
