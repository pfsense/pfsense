<?php
/*
	diag_confbak.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2005 Colin Smith
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-diagnostics-configurationhistory
##|*NAME=Diagnostics: Configuration History
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
} elseif ($_GET) {
	if (!isset($_GET['newver']) && !isset($_GET['rmver']) && !isset($_GET['getcfg']) && !isset($_GET['diff'])) {
		header("Location: diag_confbak.php");
		return;
	}

	conf_mount_rw();
	$confvers = unserialize(file_get_contents($g['cf_conf_path'] . '/backup/backup.cache'));

	if ($_GET['newver'] != "") {
		if (config_restore($g['conf_path'] . '/backup/config-' . $_GET['newver'] . '.xml') == 0) {
			$savemsg = sprintf(gettext('Successfully reverted to timestamp %1$s with description "%2$s".'), date(gettext("n/j/y H:i:s"), $_GET['newver']), htmlspecialchars($confvers[$_GET['newver']]['description']));
		} else {
			$savemsg = gettext("Unable to revert to the selected configuration.");
		}
	}
	if ($_GET['rmver'] != "") {
		unlink_if_exists($g['conf_path'] . '/backup/config-' . $_GET['rmver'] . '.xml');
		$savemsg = sprintf(gettext('Deleted backup with timestamp %1$s and description "%2$s".'), date(gettext("n/j/y H:i:s"), $_GET['rmver']), htmlspecialchars($confvers[$_GET['rmver']]['description']));
	}
	conf_mount_ro();
}

if ($_GET['getcfg'] != "") {
	$_GET['getcfg'] = basename($_GET['getcfg']);
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

if (($_GET['diff'] == 'Diff') && isset($_GET['oldtime']) && isset($_GET['newtime']) &&
    (is_numeric($_GET['oldtime'])) &&
    (is_numeric($_GET['newtime']) || ($_GET['newtime'] == 'current'))) {
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

$pgtitle = array(gettext("Diagnostics"), gettext("Configuration History"));
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if ($diff) {
?>
<div class="panel panel-default">
	<div class="panel-heading"><?=gettext("Configuration diff from ")?><?=date(gettext("n/j/y H:i:s"), $oldtime); ?><?=gettext(" to ")?><?=date(gettext("n/j/y H:i:s"), $newtime); ?></div>
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
				<td valign="middle" bgcolor="<?=$color; ?>" style="white-space: pre-wrap;"><?=htmlentities($line)?></td>
			</tr>
<?php
	}
?>
		</table>
	</div>
</div>
<?php
}

$tab_array = array();
$tab_array[] = array(gettext("Config History"), true, "diag_confbak.php");
$tab_array[] = array(gettext("Backup/Restore"), false, "diag_backup.php");
display_top_tabs($tab_array);

$form = new Form(new Form_Button(
	'Submit',
	gettext("Save")
));

$section = new Form_Section('Saved Configurations');

$section->addInput(new Form_Input(
	'backupcount',
	'Backup Count',
	'number',
	$config['system']['backupcount']
))->setHelp('Maximum number of old configurations to keep. By default this is 30 for a full install or 5 on NanoBSD. ');

$space = exec("/usr/bin/du -sh /conf/backup | /usr/bin/awk '{print $1;}'");

$section->addInput(new Form_StaticText(
	'Current space used by backups',
	$space
));

$form->add($section);

print($form);

if (is_array($confvers)) {
?>
<div>
	<div class="infoblock_open">
		<?=print_info_box(
			gettext(
				'To view the differences between an older configuration and a newer configuration, ' .
				'select the older configuration using the left column of radio options and select the newer configuration in the right column, ' .
				'then press the "Diff" button.'),
			'info')?>
	</div>
</div>
<?php
}
?>

<form action="diag_confbak.php" method="get">
	<div class="table-resposive">
		<table class="table table-striped table-hover table-condensed">
<?php
if (is_array($confvers)):
?>
			<thead>
				<tr>
					<th colspan="2">
						<input type="submit" name="diff" class="btn btn-info btn-xs" value="<?=gettext("Diff"); ?>" />
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
				<tr valign="top">
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
						<a class="fa fa-undo"		title="<?=gettext('Revert config')?>"	href="diag_confbak.php?newver=<?=$version['time']?>"	onclick="return confirm('<?=gettext("Are you sure you want to replace the current configuration with this backup?")?>')"></a>
						<a class="fa fa-download"	title="<?=gettext('Download config')?>"	href="diag_confbak.php?getcfg=<?=$version['time']?>"></a>
						<a class="fa fa-trash"		title="<?=gettext('Delete config')?>"	href="diag_confbak.php?rmver=<?=$version['time']?>"></a>
					</td>
				</tr>
<?php
	endforeach;
?>
				<tr>
					<td colspan="2"><input type="submit" name="diff" class="btn btn-info btn-xs" value="<?=gettext("Diff"); ?>" /></td>
					<td colspan="5"></td>
				</tr>
<?php
else:
	print_info_box(gettext("No backups found."), 'danger');
endif;
?>
			</tbody>
		</table>
	</form>
</div>

<?php include("foot.inc");
