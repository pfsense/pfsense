<?php
/* $Id$ */
/*
	system_usermanager_addprivs.php

	Copyright (C) 2006 Daniel S. Haischt.
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
	pfSense_MODULE:	auth
*/

##|+PRIV
##|*IDENT=page-system-usermanager-addprivs
##|*NAME=System: User Manager: Add Privileges page
##|*DESCR=Allow access to the 'System: User Manager: Add Privileges' page.
##|*MATCH=system_usermanager_addprivs.php*
##|-PRIV

function admusercmp($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

require("guiconfig.inc");

$pgtitle = array("System","User manager","Add privileges");

if (is_numericint($_GET['userid']))
	$userid = $_GET['userid'];
if (isset($_POST['userid']) && is_numericint($_POST['userid']))
	$userid = $_POST['userid'];

if (!isset($config['system']['user'][$userid]) && !is_array($config['system']['user'][$userid])) {
	pfSenseHeader("system_usermanager.php");
	exit;
}

$a_user = & $config['system']['user'][$userid];

if (!is_array($a_user['priv']))
	$a_user['priv'] = array();

if ($_POST) {
	conf_mount_rw();

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "sysprivs");
	$reqdfieldsn = array(gettext("Selected priveleges"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	/* if this is an AJAX caller then handle via JSON */
	if(isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	if (!$input_errors) {

		if (!is_array($pconfig['sysprivs']))
			$pconfig['sysprivs'] = array();

		if (!count($a_user['priv']))
			$a_user['priv'] = $pconfig['sysprivs'];
		else
			$a_user['priv'] = array_merge($a_user['priv'], $pconfig['sysprivs']);

		$a_user['priv'] = sort_user_privs($a_user['priv']);
		local_user_set($a_user);
		$retval = write_config();
		$savemsg = get_std_save_message($retval);
		conf_mount_ro();
		
		post_redirect("system_usermanager.php", array('act' => 'edit', 'userid' => $userid));
		
		exit;
	}
	conf_mount_ro();
}

/* if ajax is calling, give them an update message */
if(isAjax())
	print_info_box_np($savemsg);

include("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[

<?php

if (is_array($priv_list)) {
	$id = 0;

	$jdescs = "var descs = new Array();\n";
	foreach($priv_list as $pname => $pdata) {
		if (in_array($pname, $a_user['priv']))
			continue;
		$desc = addslashes(preg_replace("/pfSense/i", $g['product_name'], $pdata['descr']));
		$jdescs .= "descs[{$id}] = '{$desc}';\n";
		$id++;
	}

	echo $jdescs;
}

?>

function update_description() {
	var index = document.iform.sysprivs.selectedIndex;
	document.getElementById("pdesc").innerHTML = descs[index];
}

//]]>
</script>
<?php
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="user manager add priveleges">
	<tr>
		<td>
		<?php
			$tab_array = array();
			$tab_array[] = array(gettext("Users"), true, "system_usermanager.php");
			$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
			$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
			$tab_array[] = array(gettext("Servers"), false, "system_authservers.php");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
	<tr>
		<td id="mainarea">
			<div class="tabcont">
				<form action="system_usermanager_addprivs.php" method="post" name="iform" id="iform">
					<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("System Privileges");?></td>
							<td width="78%" class="vtable">
								<select name="sysprivs[]" id="sysprivs" class="formselect" onchange="update_description();" multiple="multiple" size="35">
									<?php
										foreach($priv_list as $pname => $pdata):
											if (in_array($pname, $a_user['priv']))
												continue;
									?>
									<option value="<?=$pname;?>"><?=$pdata['name'];?></option>
									<?php endforeach; ?>
								</select>
								<br />
								<?=gettext("Hold down CTRL (pc)/COMMAND (mac) key to select multiple items");?>
							</td>
						</tr>
						<tr height="60">
							<td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
							<td width="78%" valign="top" class="vtable" id="pdesc">
								<em><?=gettext("Select a privilege from the list above for a description"); ?></em>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top">&nbsp;</td>
							<td width="78%">
								<input id="submitt"  name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
								<input id="cancelbutton" class="formbtn" type="button" value="<?=gettext("Cancel");?>" onclick="history.back()" />
								<?php if (isset($userid)): ?>
								<input name="userid" type="hidden" value="<?=htmlspecialchars($userid);?>" />
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</form>
			</div>
		</td>
	</tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
