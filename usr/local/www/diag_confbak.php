<?php
/* $Id$ */
/*
	diag_confbak.php
	Copyright (C) 2005 Colin Smith
	Copyright (C) 2010 Jim Pingle
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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

if($savemsg)
	print_info_box($savemsg);
?>
	<?php if ($diff):?>
		<h3><?=gettext("Configuration diff from")?><?=date(gettext("n/j/y H:i:s"), $oldtime)?><?=gettext("to")?><?=date(gettext("n/j/y H:i:s"), $newtime)?></h3>
		<pre><?php foreach ($diff as $line) {
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

			print '<span style="background-color: '.$color .'">'. htmlentities($line) .'</span><br/>';
		}
		?></pre>
<?php endif?>
<?PHP if ($_GET["newver"] || $_GET["rmver"]):?>
	<h2><?=gettext("Confirm Action")?></h2>
	<form action="diag_confbak.php" method="post">
		<div class="alert alert-danger">
			<p><?=gettext("Please confirm you wish to ")?>
			<?PHP
				if (!empty($_GET["newver"])) {
					echo gettext("restore from Configuration Backup");
					$target_config = $_GET["newver"]?>
				<input type="hidden" name="newver" value="<?PHP echo htmlspecialchars($_GET["newver"])?>" />
			<?PHP
				} elseif (!empty($_GET["rmver"])) {
					echo gettext("remove Configuration Backup");
					$target_config = $_GET["rmver"]?>
				<input type="hidden" name="rmver" value="<?PHP echo htmlspecialchars($_GET["rmver"])?>" />
			<?PHP
				} ?>
				<?PHP echo gettext("revert to configuration from ")?> <?=date(gettext("n/j/y H:i:s"), $target_config)?>
				<br />
				<input type="submit" name="confirm" value="<?PHP echo gettext("Confirm")?>" />
			</p>
		</div>
	</form>
<?PHP else:?>
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("Config History"), true, "diag_confbak.php");
	$tab_array[1] = array(gettext("Backup/Restore"), false, "diag_backup.php");
	display_top_tabs($tab_array);
?>
		<form action="diag_confbak.php" method="post">
			<div class="form-group">
				<label for="backupcount" class="col-sm-2 control-label"><?=gettext("Backup Count")?></label>
				<div class="col-sm-10">
					<input name="backupcount" type="number" class="form-control" size="5" value="<?=htmlspecialchars($config['system']['backupcount'])?>" />
					<?=gettext("Maximum number of old configurations to keep. By default this is 30 for a full install or 5 on NanoBSD.")?>
				</div>
			</div>

			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save")?>" />
					<p><?=gettext("Current space used by backups: ")?><?=exec("/usr/bin/du -sh /conf/backup | /usr/bin/awk '{print $1;}'")?></p>
				</div>
			</div>
		</form>
<?php if (!is_array($confvers)): ?>
	<?php print_info_box(gettext("No backups found."))?>
<?php else: ?>
	<form action="diag_confbak.php" method="get">
	<div class="table-responsive">
	<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th><input type="submit" name="diff" class="btn btn-default" value="<?=gettext("Diff")?>" /></th>
			<th><?=gettext("Date")?></th>
			<th><?=gettext("Version")?></th>
			<th><?=gettext("Size")?></th>
			<th><?=gettext("Configuration Change")?></th>
			<th></th>
		</tr>
		</thead>

		<tbody>
		<tr>
			<td>
				<input type="radio" name="oldtime" disabled="disabled" />
				<input type="radio" name="newtime" value="current" <?=($_GET['newtime']==$version['time'] ? ' checked="checked"' : '')?>/>
			</td>
			<td><?=date(gettext("n/j/y H:i:s"), $config['revision']['time'])?></td>
			<td><?=$config['version']?></td>
			<td><?=format_bytes(filesize("/conf/config.xml"))?></td>
			<td><?=$config['revision']['description']?></td>
			<td><i><?=gettext("Current")?></i></td>
		</tr>
		<?php
			foreach($confvers as $version):
				if($version['time'] != 0)
					$date = date(gettext("n/j/y H:i:s"), $version['time']);
				else
					$date = gettext("Unknown");
		?>
		<tr>
			<td>
				<input type="radio" name="oldtime" value="<?=$version['time']?>" <?=($_GET['oldtime']==$version['time'] ? ' checked="checked"' : '')?> />
				<input type="radio" name="newtime" value="<?=$version['time']?>" <?=($_GET['newtime']==$version['time'] ? ' checked="checked"' : '')?><?=($version == end($confvers))? 'disabled="disabled"' : ''?> />
			</td>
			<td><?=$date?></td>
			<td><?=$version['version']?></td>
			<td><?=format_bytes($version['filesize'])?></td>
			<td><?=$version['description']?></td>
			<td>
				<a href="diag_confbak.php?newver=<?=$version['time']?>" title="<?=gettext("Revert to this configuration")?>">
					<i class="icon icon-retweet"></i>
				</a>
				<a href="diag_confbak.php?rmver=<?=$version['time']?>" title="<?=gettext("Remove this backup")?>">
					<i class="icon icon-remove"></i>
				</a>
				<a href="diag_confbak.php?getcfg=<?=$version['time']?>" title="<?=gettext("Download this backup")?>">
					<i class="icon icon-download"></i>
				</a>
			</td>
		</tr>
		<?php endforeach?>
		</tbody>
		<tfoot>
		<tr>
			<td colspan="6"><input type="submit" name="diff" class="btn btn-default" value="<?=gettext("Compare selected")?>" /></td>
		</tr>
	<?php endif; ?>
<?php endif?>
	</table>
	</div>
	</form>
<?php include("foot.inc")?>