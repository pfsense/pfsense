#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	system_advanced.php
        part of pfSense
        Copyright (C) 2005 Scott Ullrich

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

require("guiconfig.inc");

if ($_POST) {
	$config['system']['firmware']['branch'] = $_POST['branch'];
	write_config();
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

// -->
</script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<p class="pgtitle"><?=$pgtitle?></p>
<form action="system_firmware_settings.php" method="post" name="iform" id="iform">
<?php
include("fbegin.inc");
?>
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
	<tr><td class="tabcont"><table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td colspan="2" valign="top" class="listtopic">Firmware Branch</td>
	</tr>
	<tr>
                  <td width="22%" valign="top" class="vncell">Firmware Branch</td>
                  <td width="78%" class="vtable">
			<select onChange="update_description(this.selectedIndex);" name="branch" id="branch">
			<option value="stable"<?php if($curcfg['branch']=="stable") echo " SELECTED"; ?>>Stable</option>
			<option value="beta"<?php if($curcfg['branch']=="beta") echo " SELECTED"; ?>>Beta</option>
			<option value="alpha"<?php if($config['branch']=="alpha") echo " SELECTED"; ?>>Alpha</option>
			</select>
			<textarea cols="60" rows="2" id="branchinfo" name="branchinfo"style="border:1px dashed #000066; background-color: #ffffff; color: #000000; font-size: 8pt;">
			</textarea>
			<script language="javascript">
			update_description(document.forms[0].branch.selectedIndex);
			</script>
			<br><span class="vexpl"><b>Select the update branch you would like this system to track</b></td>
                </tr>
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
                <tr>
                  <td colspan="2" class="list" height="12"></td>
                </tr>
              </table></td></tr></table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
