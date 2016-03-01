<?php
/*
	diag_tables.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Portions borrowed from diag_dump_states.php
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
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

// Set default table
$tablename = "sshlockout";

if ($_REQUEST['type']) {
	$tablename = $_REQUEST['type'];
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
		$savemsg = sprintf(gettext("The %s database has been updated."), $db_name);
	}
}

exec("/sbin/pfctl -t " . escapeshellarg($tablename) . " -T show", $entries);
exec("/sbin/pfctl -sT", $tables);

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if ($tablename == "webConfiguratorlockout") {
	$displayname = gettext("webConfigurator Lockout Table");
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
));

if ($bogons || $urltable || !empty($entries)) {
	if ($bogons || $urltable) {
		$group->add(new Form_Button(
			'Download',
			'Update'
		))->removeClass('btn-primary')->addClass('btn-success btn-sm');
	} elseif (!empty($entries)) {
		$group->add(new Form_Button(
			'clearall',
			'Clear Table'
		))->removeClass('btn-primary')->addClass('btn-danger btn-sm');
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

	$datestrregex = '(Mon|Tue|Wed|Thr|Fri|Sat|Sun).* GMT';
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
					type: '<?=htmlspecialchars($tablename)?>',
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
								<a class="btn btn-xs btn-default" data-entry="<?=htmlspecialchars($entry)?>"><?=gettext("Remove")?></a>
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
