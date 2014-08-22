<?php
/* $Id$ */
/*
    diag_confbak.php
    Copyright (C) 2005 Colin Smith
    Copyright (C) 2010 Jim Pingle
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
	pfSense_MODULE:	config
*/

##|+PRIV
##|*IDENT=page-diagnostics-configurationhistory
##|*NAME=Diagnostics: Configuration History page
##|*DESCR=Allow access to the 'Diagnostics: Configuration History' page.
##|*MATCH=diag_confbak.php*
##|-PRIV

require("guiconfig.inc");

if (isset($_POST['backupcount'])) {
	if (is_numeric($_POST['backupcount']) && ($_POST['backupcount'] >= 0)) {
		$config['system']['backupcount'] = $_POST['backupcount'];
		$changedescr = $config['system']['backupcount'];
	} else {
		unset($config['system']['backupcount']);
		$changedescr = "(platform default)";
	}
	write_config("Changed backup revision count to {$changedescr}");
} elseif ($_POST) {
	if (!isset($_POST['confirm']) || ($_POST['confirm'] != gettext("Confirm")) || (!isset($_POST['newver']) && !isset($_POST['rmver']))) {
		header("Location: diag_confbak.php");
		return;
	}

	conf_mount_rw();
	$confvers = unserialize(file_get_contents($g['cf_conf_path'] . '/backup/backup.cache'));
	if($_POST['newver'] != "") {
		if(config_restore($g['conf_path'] . '/backup/config-' . $_POST['newver'] . '.xml') == 0)
		$savemsg = sprintf(gettext('Successfully reverted to timestamp %1$s with description "%2$s".'), date(gettext("n/j/y H:i:s"), $_POST['newver']), $confvers[$_POST['newver']]['description']);
		else
			$savemsg = gettext("Unable to revert to the selected configuration.");
	}
	if($_POST['rmver'] != "") {
		unlink_if_exists($g['conf_path'] . '/backup/config-' . $_POST['rmver'] . '.xml');
		$savemsg = sprintf(gettext('Deleted backup with timestamp %1$s and description "%2$s".'), date(gettext("n/j/y H:i:s"), $_POST['rmver']),$confvers[$_POST['rmver']]['description']);
	}
	conf_mount_ro();
}

if($_GET['getcfg'] != "") {
	$file = $g['conf_path'] . '/backup/config-' . $_GET['getcfg'] . '.xml';

	$exp_name = urlencode("config-{$config['system']['hostname']}.{$config['system']['domain']}-{$_GET['getcfg']}.xml");
	$exp_data = file_get_contents($file);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
}

if (($_GET['diff'] == 'Diff') && isset($_GET['oldtime']) && isset($_GET['newtime'])
      && is_numeric($_GET['oldtime']) && (is_numeric($_GET['newtime']) || ($_GET['newtime'] == 'current'))) {
	$diff = "";
	$oldfile = $g['conf_path'] . '/backup/config-' . $_GET['oldtime'] . '.xml';
	$oldtime = $_GET['oldtime'];
	if ($_GET['newtime'] == 'current') {
		$newfile = $g['conf_path'] . '/config.xml';
		$newtime = $config['revision']['time'];
	} else {
		$newfile = $g['conf_path'] . '/backup/config-' . $_GET['newtime'] . '.xml';
		$newtime = $_GET['newtime'];
	}
	if (file_exists($oldfile) && file_exists($newfile)) {
		exec("/usr/bin/diff -u " . escapeshellarg($oldfile) . " " . escapeshellarg($newfile), $diff);
	}
}

cleanup_backupcache(false);
$confvers = get_backups();
unset($confvers['versions']);

$pgtitle = array(gettext("Diagnostics"),gettext("Configuration History"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<?php
		include("fbegin.inc");
		if($savemsg)
			print_info_box($savemsg);
	?>
	<?php if ($diff) { ?>
	<table align="center" width="100%" border="0" cellspacing="0" style="padding-top: 4px; padding-bottom: 4px; vertical-align:middle;" summary="diag confbak">
		<tr><td><?=gettext("Configuration diff from");?> <?php echo date(gettext("n/j/y H:i:s"), $oldtime); ?> <?=gettext("to");?> <?php echo date(gettext("n/j/y H:i:s"), $newtime); ?></td></tr>
		<?php foreach ($diff as $line) {
			switch (substr($line, 0, 1)) {
				case "+":
					$color = "#caffd3";
					break;
				case "-":
					$color = "#ffe8e8";
					break;
				case "@":
					$color = "#a0a0a0";
					break;
				default:
					$color = "#ffffff";
			}
			?>
		<tr>
			<td valign="middle" bgcolor="<?php echo $color; ?>" style="white-space: pre-wrap;"><?php echo htmlentities($line);?></td>
		</tr>
		<?php } ?>
	</table>
	<br />
	<?php } ?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="stats">
		<tr>
			<td>
			<?php
				$tab_array = array();
				$tab_array[0] = array(gettext("Config History"), true, "diag_confbak.php");
				$tab_array[1] = array(gettext("Backup/Restore"), false, "diag_backup.php");
				display_top_tabs($tab_array);
			?>
			</td>
		</tr>
		<tr>
			<td>
				<div id="mainarea">
					<form action="diag_confbak.php" method="post">
					<table class="tabcont" align="center" width="100%" border="0" cellpadding="6" cellspacing="0" summary="tabcont">

<?PHP if ($_GET["newver"] || $_GET["rmver"]): ?>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?PHP echo gettext("Confirm Action"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">&nbsp;</td>
						<td width="78%" class="vtable">

							<strong><?PHP echo gettext("Please confirm the selected action"); ?></strong>:
							<br />
							<br /><strong><?PHP echo gettext("Action"); ?>:</strong>
						<?PHP	if (!empty($_GET["newver"])) {
							echo gettext("Restore from Configuration Backup");
							$target_config = $_GET["newver"]; ?>
							<input type="hidden" name="newver" value="<?PHP echo htmlspecialchars($_GET["newver"]); ?>" />
						<?PHP	} elseif (!empty($_GET["rmver"])) {
							echo gettext("Remove Configuration Backup");
							$target_config = $_GET["rmver"]; ?>
							<input type="hidden" name="rmver" value="<?PHP echo htmlspecialchars($_GET["rmver"]); ?>" />
						<?PHP	} ?>
							<br /><strong><?PHP echo gettext("Target Configuration"); ?>:</strong>
							<?PHP echo sprintf(gettext('Timestamp %1$s'), date(gettext("n/j/y H:i:s"), $target_config)); ?>
							<br /><input type="submit" name="confirm" value="<?PHP echo gettext("Confirm"); ?>" />
						</td>
					</tr>
<?PHP else: ?>

						<tr>
							<td width="10%">&nbsp;</td>
							<td width="15%" valign="top"><?=gettext("Backup Count");?></td>
							<td width="10%">
							<input name="backupcount" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($config['system']['backupcount']);?>"/>
							</td>
							<td width="60%">
							<?= gettext("Enter the number of older configurations to keep in the local backup cache. By default this is 30 for a full install or 5 on NanoBSD."); ?>
							</td>
							<td width= "5%"><input name="save" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" /></td>
						</tr>
						<tr>
							<td class="vncell">&nbsp;</td>
							<td colspan="4" class="vncell">
							<?= gettext("NOTE: Be aware of how much space is consumed by backups before adjusting this value. Current space used by backups: "); ?> <?= exec("/usr/bin/du -sh /conf/backup | /usr/bin/awk '{print $1;}'") ?>
							</td>
						</tr>
					</table>
					</form>
					<form action="diag_confbak.php" method="get">
					<table class="tabcont" align="center" width="100%" border="0" cellpadding="6" cellspacing="0" summary="difference">
						<?php if (is_array($confvers)): ?>
						<tr>
							<td colspan="7" class="list">
							<?= gettext("To view the differences between an older configuration and a newer configuration, select the older configuration using the left column of radio options and select the newer configuration in the right column, then press the Diff button."); ?>
							<br /><br />
							</td>
						</tr>
						<tr>
							<td width="5%" colspan="2" valign="middle" align="center" class="list nowrap"><input type="submit" name="diff" value="<?=gettext("Diff"); ?>" /></td>
							<td width="20%" class="listhdrr"><?=gettext("Date");?></td>
							<td width="5%" class="listhdrr"><?=gettext("Version");?></td>
							<td width="5%" class="listhdrr"><?=gettext("Size");?></td>
							<td width="60%" class="listhdrr"><?=gettext("Configuration Change");?></td>
							<td width="5%" class="list">&nbsp;</td>
						</tr>
						<tr valign="top">
							<td valign="middle" class="list nowrap"></td>
							<td class="list">
								<input type="radio" name="newtime" value="current" />
							</td>
							<td class="listlr"> <?= date(gettext("n/j/y H:i:s"), $config['revision']['time']) ?></td>
							<td class="listr"> <?= $config['version'] ?></td>
							<td class="listr"> <?= format_bytes(filesize("/conf/config.xml")) ?></td>
							<td class="listr"> <?= $config['revision']['description'] ?></td>
							<td valign="middle" class="list nowrap"><b><?=gettext("Current");?></b></td>
						</tr>
						<?php
							$c = 0;
							foreach($confvers as $version):
								if($version['time'] != 0)
									$date = date(gettext("n/j/y H:i:s"), $version['time']);
								else
									$date = gettext("Unknown");
						?>
						<tr valign="top">
							<td class="list">
								<input type="radio" name="oldtime" value="<?php echo $version['time'];?>" />
							</td>
							<td class="list">
								<?php if ($c < (count($confvers) - 1)) { ?>
								<input type="radio" name="newtime" value="<?php echo $version['time'];?>" />
								<?php } else { ?>
								&nbsp;
								<?php }
								$c++; ?>
							</td>
							<td class="listlr"> <?= $date ?></td>
							<td class="listr"> <?= $version['version'] ?></td>
							<td class="listr"> <?= format_bytes($version['filesize']) ?></td>
							<td class="listr"> <?= $version['description'] ?></td>
							<td valign="middle" class="list nowrap">
							<a href="diag_confbak.php?newver=<?=$version['time'];?>">
							<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="<?=gettext("Revert to this configuration");?>" title="<?=gettext("Revert to this configuration");?>" />
								</a>
							<a href="diag_confbak.php?rmver=<?=$version['time'];?>">
							<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="<?=gettext("Remove this backup");?>" title="<?=gettext("Remove this backup");?>" />
								</a>
								<a href="diag_confbak.php?getcfg=<?=$version['time'];?>">
								<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_down.gif" width="17" height="17" border="0" alt="<?=gettext("Download this backup");?>" title="<?=gettext("Download this backup");?>" />
								</a>
							</td>
						</tr>
						<?php endforeach; ?>
						<tr>
							<td colspan="2"><input type="submit" name="diff" value="<?=gettext("Diff"); ?>" /></td>
							<td colspan="5"></td>
						</tr>
						<?php else: ?>
						<tr>
							<td>
								<?php print_info_box(gettext("No backups found.")); ?>
							</td>
						</tr>
						<?php endif; ?>
<?php endif; ?>
					</table>
					</form>
				</div>
			</td>
		</tr>
	</table>

<?php include("fend.inc"); ?>
</body>
</html>
