<?php
/* $Id$ */
/*
	system_advanced_misc.php
	part of pfSense
	Copyright (C) 2005-2007 Scott Ullrich

	Copyright (C) 2008 Shrew Soft Inc

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
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-system-advanced-sysctl
##|*NAME=System: Advanced: Tunables page
##|*DESCR=Allow access to the 'System: Advanced: Tunables' page.
##|*MATCH=system_advanced_sysctl.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['sysctl']['item']))
	$config['sysctl']['item'] = array();

$a_tunable = &$config['sysctl']['item'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

$act = $_GET['act'];
if (isset($_POST['act']))
	$act = $_POST['act'];

if ($act == "edit") {
	if ($a_tunable[$id]) {
		$pconfig['tunable'] = $a_tunable[$id]['tunable'];
		$pconfig['value'] = $a_tunable[$id]['value'];
		$pconfig['descr'] = $a_tunable[$id]['descr'];
	}
}

if ($act == "del") {
	if ($a_tunable[$id]) {
		/* if this is an AJAX caller then handle via JSON */
		if(isAjax() && is_array($input_errors)) {
			input_errors2Ajax($input_errors);
			exit;
		}
		if (!$input_errors) {
			unset($a_tunable[$id]);
			write_config();
			mark_subsystem_dirty('sysctl');
			pfSenseHeader("system_advanced_sysctl.php");
			exit;
		}
	}
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	if ($_POST['apply']) {
		$retval = 0;
		system_setup_sysctl();		
		$savemsg = get_std_save_message($retval);
		clear_subsystem_dirty('sysctl');
	}

	if ($_POST['Submit'] == gettext("Save")) {
		$tunableent = array();

		$tunableent['tunable'] = $_POST['tunable'];
		$tunableent['value'] = $_POST['value'];
		$tunableent['descr'] = $_POST['descr'];

		if (isset($id) && $a_tunable[$id])
			$a_tunable[$id] = $tunableent;
		else
			$a_tunable[] = $tunableent;

		mark_subsystem_dirty('sysctl');

		write_config();

		pfSenseHeader("system_advanced_sysctl.php");
		exit;
    }
}

$pgtitle = array(gettext("System"),gettext("Advanced: System Tunables"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
	<form action="system_advanced_sysctl.php" method="post">
		<?php
			if ($input_errors)
				print_input_errors($input_errors);
			if ($savemsg)
				print_info_box($savemsg);
			if (is_subsystem_dirty('sysctl') && ($act != "edit" ))
				print_info_box_np(gettext("The firewall tunables have changed.  You must apply the configuration to take affect."));
		?>
	</form>
	<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="system advanced tunables">
		<tr>
			<td>
				<?php
					$tab_array = array();
					$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
					$tab_array[] = array(gettext("Firewall / NAT"), false, "system_advanced_firewall.php");
					$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
					$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
					$tab_array[] = array(gettext("System Tunables"), true, "system_advanced_sysctl.php");
					$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
					display_top_tabs($tab_array);
				?>
			</td>
		</tr>
		<?php if ($act != "edit" ): ?>
		<tr>
			<td id="mainarea">
				<div class="tabcont">
					<span class="vexpl">
						<span class="red">
							<strong><?=gettext("NOTE:"); ?>&nbsp;</strong>
						</span>
						<?=gettext("The options on this page are intended for use by advanced users only."); ?>
						<br />
					</span>
					<br />
					<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
						<tr>
							<td width="20%" class="listhdrr"><?=gettext("Tunable Name"); ?></td>
							<td width="60%" class="listhdrr"><?=gettext("Description"); ?></td>
							<td width="20%" class="listhdrr"><?=gettext("Value"); ?></td>
						</tr>
						<?php $i = 0; foreach ($config['sysctl']['item'] as $tunable): ?>
						<tr>
							<td class="listlr" ondblclick="document.location='system_advanced_sysctl.php?act=edit&amp;id=<?=$i;?>';">
								<?php echo $tunable['tunable']; ?>
							</td>
							<td class="listr" align="left" ondblclick="document.location='system_advanced_sysctl.php?act=edit&amp;id=<?=$i;?>';">
								<?php echo $tunable['descr']; ?>
							</td>
							<td class="listr" align="left" ondblclick="document.location='system_advanced_sysctl.php?act=edit&amp;id=<?=$i;?>';">
								<?php echo $tunable['value']; ?>
								<?php 
									if($tunable['value'] == "default") 
										echo "(" . get_default_sysctl_value($tunable['tunable']) . ")"; 
								?>
							</td>
							<td class="list nowrap">
								<table border="0" cellspacing="0" cellpadding="1" summary="edit delete">
									<tr>
										<td valign="middle">
											<a href="system_advanced_sysctl.php?act=edit&amp;id=<?=$i;?>">
												<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" alt="" />
											</a>
										</td>
										<td valign="middle">
											<a href="system_advanced_sysctl.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?"); ?>')">
												<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="" />
											</a>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<?php $i++; endforeach; ?>
						<tr>
						<td class="list" colspan="3">
							</td>
							<td class="list">
								<table border="0" cellspacing="0" cellpadding="1" summary="edit">
									<tr>
										<td valign="middle">
											<a href="system_advanced_sysctl.php?act=edit">
												<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="" />
											</a>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</div>
			</td>
		</tr>
		<?php else: ?>
		<tr>
			<td>
				<div id="mainarea">
					<form action="system_advanced_sysctl.php" method="post" name="iform" id="iform">
						<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="edit system tunable">
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit system tunable"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Tunable"); ?></td>
								<td width="78%" class="vtable">
									<input size="65" name="tunable" value="<?php echo $pconfig['tunable']; ?>" />
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Description"); ?></td>
								<td width="78%" class="vtable">
									<textarea rows="7" cols="50" name="descr"><?php echo $pconfig['descr']; ?></textarea>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Value"); ?></td>
								<td width="78%" class="vtable">
									<input size="65" name="value" value="<?php echo $pconfig['value']; ?>" />
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top">&nbsp;</td>
								<td width="78%">
									<input id="submit" name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
									<input id="cancelbutton" name="cancelbutton" type="button" class="formbtn" value="<?=gettext("Cancel"); ?>" onclick="history.back()" />
									<?php if (isset($id) && $a_tunable[$id]): ?>
									<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</form>
				</div>
			</td>
		</tr>
		<?php endif; ?>
	</table>
<?php include("fend.inc"); ?>
</body>
</html>
