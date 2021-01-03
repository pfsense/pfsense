<?php
/*
 * diag_confbak.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Colin Smith
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-diagnostics-configurationhistory
##|*NAME=Diagnostics: Configuration History
##|*DESCR=Allow access to the 'Diagnostics: Configuration History' page.
##|*WARN=standard-warning-root
##|*MATCH=diag_confbak.php*
##|-PRIV

require_once("guiconfig.inc");

if (isset($_POST['backupcount'])) {
	if (!empty($_POST['backupcount']) && (!is_numericint($_POST['backupcount']) || ($_POST['backupcount'] < 0))) {
		$input_errors[] = gettext("Invalid Backup Count specified");
	}

	if (!$input_errors) {
		if (is_numericint($_POST['backupcount'])) {
			$config['system']['backupcount'] = $_POST['backupcount'];
			$changedescr = $config['system']['backupcount'];
		} elseif (empty($_POST['backupcount'])) {
			unset($config['system']['backupcount']);
			$changedescr = gettext("(platform default)");
		}
		write_config(sprintf(gettext("Changed backup revision count to %s"), $changedescr));
	}
}

$confvers = unserialize(file_get_contents($g['cf_conf_path'] . '/backup/backup.cache'));

if ($_POST['newver'] != "") {
	if (config_restore($g['conf_path'] . '/backup/config-' . $_POST['newver'] . '.xml') == 0) {
		$savemsg = sprintf(gettext('Successfully reverted to timestamp %1$s with description "%2$s".'), date(gettext("n/j/y H:i:s"), $_POST['newver']), htmlspecialchars($confvers[$_POST['newver']]['description']));
	} else {
		$savemsg = gettext("Unable to revert to the selected configuration.");
	}
}

if ($_POST['rmver'] != "") {
	unlink_if_exists($g['conf_path'] . '/backup/config-' . $_POST['rmver'] . '.xml');
	$savemsg = sprintf(gettext('Deleted backup with timestamp %1$s and description "%2$s".'), date(gettext("n/j/y H:i:s"), $_POST['rmver']), htmlspecialchars($confvers[$_POST['rmver']]['description']));
}

if ($_REQUEST['getcfg'] != "") {
	$_REQUEST['getcfg'] = basename($_REQUEST['getcfg']);
	send_user_download('file',
				$g['conf_path'] . '/backup/config-' . $_REQUEST['getcfg'] . '.xml',
				"config-{$config['system']['hostname']}.{$config['system']['domain']}-{$_REQUEST['getcfg']}.xml");
}

if (($_REQUEST['diff'] == 'Diff') && isset($_REQUEST['oldtime']) && isset($_REQUEST['newtime']) &&
    (is_numeric($_REQUEST['oldtime'])) &&
    (is_numeric($_REQUEST['newtime']) || ($_REQUEST['newtime'] == 'current'))) {
	$diff = "";
	$oldfile = $g['conf_path'] . '/backup/config-' . $_REQUEST['oldtime'] . '.xml';
	$oldtime = $_REQUEST['oldtime'];
	if ($_REQUEST['newtime'] == 'current') {
		$newfile = $g['conf_path'] . '/config.xml';
		$newtime = $config['revision']['time'];
	} else {
		$newfile = $g['conf_path'] . '/backup/config-' . $_REQUEST['newtime'] . '.xml';
		$newtime = $_REQUEST['newtime'];
	}
	if (file_exists($oldfile) && file_exists($newfile)) {
		exec("/usr/bin/diff -u " . escapeshellarg($oldfile) . " " . escapeshellarg($newfile), $diff);
	}
}

cleanup_backupcache(false);
$confvers = get_backups();
unset($confvers['versions']);

$pgtitle = array(gettext("Diagnostics"), htmlspecialchars(gettext("Backup & Restore")), gettext("Config History"));
$pglinks = array("", "diag_backup.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(htmlspecialchars(gettext("Backup & Restore")), false, "diag_backup.php");
$tab_array[] = array(gettext("Config History"), true, "diag_confbak.php");
display_top_tabs($tab_array);

if ($diff) {
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?=sprintf(gettext('Configuration Diff from %1$s to %2$s'), date(gettext("n/j/y H:i:s"), $oldtime), date(gettext("n/j/y H:i:s"), $newtime))?>
		</h2>
	</div>
	<div class="panel-body table-responsive">
	<!-- This table is left un-bootstrapped to maintain the original diff format output -->
		<table style="padding-top: 4px; padding-bottom: 4px; vertical-align:middle;">

<?php
	foreach ($diff as $line) {
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
				<td class="diff-text" style="vertical-align:middle; background-color:<?=$color;?>; white-space:pre-wrap;"><?=htmlentities($line)?></td>
			</tr>
<?php
	}
?>
		</table>
	</div>
</div>
<?php
}

$form = new Form(false);

$section = new Form_Section('Configuration Backup Cache Settings', 'configsettings', COLLAPSIBLE|SEC_CLOSED);

$section->addInput(new Form_Input(
	'backupcount',
	'Backup Count',
	'number',
	$config['system']['backupcount'],
	['min' => '0']
))->setHelp('Maximum number of old configurations to keep in the cache, 0 for no backups, or leave blank for the default value (%s for the current platform).', $g['default_config_backup_count']);

$space = exec("/usr/bin/du -sh /conf/backup | /usr/bin/awk '{print $1;}'");

$section->addInput(new Form_StaticText(
	'Current space used by backups',
	$space
));

$section->addInput(new Form_Button(
	'Submit',
	gettext("Save"),
	null,
	'fa-save'
))->addClass('btn-primary');

$form->add($section);

print($form);

if (is_array($confvers)) {
?>
<div>
	<div class="infoblock blockopen">
		<?php print_info_box(
			gettext(
				'To view the differences between an older configuration and a newer configuration, ' .
				'select the older configuration using the left column of radio options and select the newer configuration in the right column, ' .
				'then press the "Diff" button.'),
			'info', false); ?>
	</div>
</div>
<?php
}
?>

<form action="diag_confbak.php" method="get">
	<div class="table-responsive">
		<table class="table table-striped table-hover table-condensed">
<?php
if (is_array($confvers)):
?>
			<thead>
				<tr>
					<th colspan="2">
						<button type="submit" name="diff" class="btn btn-info btn-xs" value="Diff">
							<i class="fa fa-exchange icon-embed-btn"></i>
							<?=gettext("Diff"); ?>
						</button>
					</th>
					<th><?=gettext("Date")?></th>
					<th><?=gettext("Version")?></th>
					<th><?=gettext("Size")?></th>
					<th><?=gettext("Configuration Change")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
				<!-- First row is the current configuration -->
				<tr style="vertical-align:top;">
					<td></td>
					<td>
						<input type="radio" name="newtime" value="current" />
					</td>
					<td><?= date(gettext("n/j/y H:i:s"), $config['revision']['time']) ?></td>
					<td><?= $config['version'] ?></td>
					<td><?= format_bytes(filesize("/conf/config.xml")) ?></td>
					<td><?= htmlspecialchars($config['revision']['description']) ?></td>
					<td><?=gettext("Current configuration")?></td>
				</tr>
<?php
	// And now for the table of prior backups
	$c = 0;
	foreach ($confvers as $version):
		if ($version['time'] != 0) {
			$date = date(gettext("n/j/y H:i:s"), $version['time']);
		} else {
			$date = gettext("Unknown");
		}
?>
				<tr>
					<td>
						<input type="radio" name="oldtime" value="<?=$version['time']?>" />
					</td>
					<td>
<?php
		if ($c < (count($confvers) - 1)) {
?>
								<input type="radio" name="newtime" value="<?=$version['time']?>" />
<?php
		}
		$c++;
?>
					</td>
					<td><?= $date ?></td>
					<td><?= $version['version'] ?></td>
					<td><?= format_bytes($version['filesize']) ?></td>
					<td><?= htmlspecialchars($version['description']) ?></td>
					<td>
						<a class="fa fa-undo"		title="<?=gettext('Revert config')?>"	href="diag_confbak.php?newver=<?=$version['time']?>" onclick="return confirm('<?=gettext("Confirmation Required to replace the current configuration with this backup.")?>')" usepost></a>
						<a class="fa fa-download"	title="<?=gettext('Download config')?>"	href="diag_confbak.php?getcfg=<?=$version['time']?>"></a>
						<a class="fa fa-trash"		title="<?=gettext('Delete config')?>"	href="diag_confbak.php?rmver=<?=$version['time']?>" usepost></a>
					</td>
				</tr>
<?php
	endforeach;
?>
				<tr>
					<td colspan="2">
						<button type="submit" name="diff" class="btn btn-info btn-xs" value="Diff">
							<i class="fa fa-exchange icon-embed-btn"></i>
							<?=gettext("Diff"); ?>
						</button>
					</td>
					<td colspan="5"></td>
				</tr>
<?php
else:
	print_info_box(gettext("No backups found."), 'danger');
endif;
?>
			</tbody>
		</table>
	</div>
</form>

<?php include("foot.inc");
