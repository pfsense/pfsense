<?php
/*
 * diag_tables.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-diagnostics-tables
##|*NAME=Diagnostics: pf Table IP addresses
##|*DESCR=Allow access to the 'Diagnostics: Tables' page.
##|*MATCH=diag_tables.php*
##|-PRIV

$pgtitle = array(gettext("Diagnostics"), gettext("Tables"));
$shortcut_section = "aliases";

require_once("guiconfig.inc");

exec("/sbin/pfctl -sT", $tables);

// Set default table
$tablename = "sshguard";

if ($_REQUEST['type'] && in_array($_REQUEST['type'], $tables)) {
	$tablename = $_REQUEST['type'];
} else {
	/* Invalid 'type' passed, do not take any actions that use the 'type' field. */
	unset($_REQUEST['type']);
	$_REQUEST['delete'];
}

// Gather selected alias metadata.
if (isset($config['aliases']['alias'])) {
	foreach ($config['aliases']['alias'] as $alias) {
		if ( $alias['name'] == $tablename ) {
			$tmp = array();
			$tmp['type'] = $alias['type'];
			$tmp['name'] = $alias['name'];
			$tmp['url']  = $alias['url'];
			$tmp['freq'] = $alias['updatefreq'];
			break;
		}
	}
}

# Determine if selected alias is either a bogons or URL table.
if (($tablename == "bogons") || ($tablename == "bogonsv6")) {
	$bogons = true;
} else if (preg_match('/urltable/i', $tmp['type'])) {
	$urltable = true;
} else {
	$bogons = $urltable = false;
}

if ($_REQUEST['delete']) {
	if (is_ipaddr($_REQUEST['delete']) || is_subnet($_REQUEST['delete'])) {
		exec("/sbin/pfctl -t " . escapeshellarg($_REQUEST['type']) . " -T delete " . escapeshellarg($_REQUEST['delete']), $delete);
		echo htmlentities($_REQUEST['delete']);
	}
	exit;
}

if ($_POST['clearall']) {
	$entries = array();
	exec("/sbin/pfctl -t " . escapeshellarg($tablename) . " -T show", $entries);
	if (is_array($entries)) {
		foreach ($entries as $entryA) {
			$entry = trim($entryA);
			exec("/sbin/pfctl -t " . escapeshellarg($tablename) . " -T delete " . escapeshellarg($entry), $delete);
		}
	}
	unset($entries);
}

if ($_POST['Download'] && ($bogons || $urltable)) {

	if ($bogons) {				// If selected table is either bogons or bogonsv6.
		$mwexec_bg_cmd = '/etc/rc.update_bogons.sh now';
		$table_type = 'bogons';
		$db_name = 'bogons';
	} else if ($urltable) {		//  If selected table is a URL table alias.
		$mwexec_bg_cmd = '/etc/rc.update_urltables now forceupdate ' . $tablename;
		$table_type = 'urltables';
		$db_name = $tablename;
	}

	mwexec_bg($mwexec_bg_cmd);
	$maxtimetowait = 0;
	$loading = true;
	while ($loading == true) {
		$isrunning = `/bin/ps awwwux | /usr/bin/grep -v grep | /usr/bin/grep $table_type`;
		if ($isrunning == "") {
			$loading = false;
		}
		$maxtimetowait++;
		if ($maxtimetowait > 89) {
			$loading = false;
		}
		sleep(1);
	}
	if ($maxtimetowait < 90) {
		$savemsg = sprintf(gettext("The %s file contents have been updated."), $db_name);
	}
}

$entries = array();
exec("/sbin/pfctl -t " . escapeshellarg($tablename) . " -T show", $entries);

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if ($tablename == "sshguard") {
	$displayname = gettext("SSH and GUI Lockout Table");
} else {
	$displayname = sprintf(gettext("%s Table"), ucfirst($tablename));
}

$form = new Form(false);

$section = new Form_Section('Table to Display');
$group = new Form_Group("Table");

$group->add(new Form_Select(
	'type',
	null,
	$tablename,
	array_combine($tables, $tables)
))->setHelp('Select a user-defined alias name or system table name to view its contents. %s' .
	'Aliases become Tables when loaded into the active firewall ruleset. ' .
	'The contents displayed on this page reflect the current addresses inside tables used by the firewall.', '<br/><br/>');

if ($bogons || $urltable || !empty($entries)) {
	if ($bogons || $urltable) {
		$group->add(new Form_Button(
			'Download',
			'Update',
			null,
			'fa-refresh'
		))->addClass('btn-success btn-sm');
	} elseif (!empty($entries)) {
		$group->add(new Form_Button(
			'clearall',
			'Empty Table',
			null,
			'fa-trash'
		))->addClass('btn-danger btn-sm');
	}
}

$section->add($group);
$form->add($section);
print $form;

if ($bogons || $urltable || !empty($entries)) {
?>
<div>
	<div class="infoblock blockopen">
<?php
	if ($bogons) {
		$table_file = '/etc/' . escapeshellarg($tablename);
	} else if ($urltable) {
		$table_file = '/var/db/aliastables/' . escapeshellarg($tablename) . '.txt';
	} else {
		$table_file = '';
	}

	$datestrregex = '(Mon|Tue|Wed|Thu|Fri|Sat|Sun).* GMT';
	$datelineregex = 'last.*' . $datestrregex;

	$last_updated = exec('/usr/bin/grep -i -m 1 -E "^# ' . $datelineregex . '" ' . $table_file . '|/usr/bin/grep -i -m 1 -E -o "' . $datestrregex . '"');

	if ($last_updated != "") {
		$last_update_msg = sprintf(gettext("Table last updated on %s."), $last_updated);
	} else {
		$last_update_msg = gettext("Date of last update of table is unknown.");
	}

	$records_count_msg = sprintf(gettext("%s records."), number_format(count($entries), 0, gettext("."), gettext(",")));

	# Display up to 10 comment lines (lines that begin with '#').
	unset($comment_lines);
	$res = exec('/usr/bin/grep -i -m 10 -E "^#" ' . $table_file, $comment_lines);

	foreach ($comment_lines as $comment_line) {
		$table_comments .= "$comment_line" . "<br />";
	}

	if ($table_comments) {
		print_info_box($last_update_msg . " &nbsp; &nbsp; " . $records_count_msg . "<br />" .
		'<span style="display:none" class="infoblock">' . ' ' . gettext("Hide table comments.") . '<br />' . $table_comments . '</span>' .
		'<span style="display:none"   id="showtblcom">' . ' ' . gettext("Show table comments.") . '</span>' .
		'' , 'info', false);
	} else {
		print_info_box($last_update_msg . "&nbsp; &nbsp; " . $records_count_msg, 'info', false);
	}
?>
	</div>
</div>
<?php
}
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	$('#showtblcom').show();

	$('[id^="showinfo1"]').click(function() {
			$('#showtblcom').toggle();
	});

	$('a[data-entry]').on('click', function() {
		var el = $(this);

		$.ajax(
			'/diag_tables.php',
			{
				type: 'post',
				data: {
					type: '<?=htmlspecialchars(addslashes($tablename))?>',
					delete: $(this).data('entry')
				},
				success: function() {
					el.parents('tr').remove();
				},
		});
	});

	// Auto-submit the form on table selector change
	$('#type').on('change', function() {
        $('form').submit();
    });
});
//]]>
</script>

<?php
if (empty($entries)) {
	print_info_box(gettext("No entries exist in this table."), 'warning', false);
} else {
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=$displayname?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("IP Address")?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
<?php
		// This is a band-aid for a yet to be root caused performance issue with large tables.  Suspected is css and/or sorting.
 		if (count($entries) > 3000) {
			print "<tr><td colspan='2'><pre>";
			foreach ($entries as $entry) {
				$entry = trim($entry);
					print $entry . "\n";
			}
			print "</pre></td></tr>";
		} else {
?>
<?php
		foreach ($entries as $entry):
			$entry = trim($entry);
?>
					<tr>
						<td>
							<?=$entry?>
						</td>
						<td>
							<?php if (!$bogons && !$urltable): ?>
								<a style="cursor: pointer;" data-entry="<?=htmlspecialchars($entry)?>">
									<i class="fa fa-trash" title="<?= gettext("Remove this entry") ?>"></i>
								</a>
							<?php endif ?>
						</td>
					</tr>
<?php endforeach ?>
<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php
}

include("foot.inc");
