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
$bogons = false;

if ($_REQUEST['type']) {
	$tablename = $_REQUEST['type'];
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

if (($tablename == "bogons") || ($tablename == "bogonsv6")) {
	$bogons = true;

	if ($_POST['Download']) {
		mwexec_bg("/etc/rc.update_bogons.sh now");
		$maxtimetowait = 0;
		$loading = true;
		while ($loading == true) {
			$isrunning = `/bin/ps awwwux | /usr/bin/grep -v grep | /usr/bin/grep bogons`;
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
			$savemsg = gettext("The bogons database has been updated.");
		}
	}
}

exec("/sbin/pfctl -t " . escapeshellarg($tablename) . " -T show", $entries);
exec("/sbin/pfctl -sT", $tables);

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$form = new Form(false);

$section = new Form_Section('Table to display');
$group = new Form_Group("Table");

$group->add(new Form_Select(
	'type',
	null,
	$tablename,
	array_combine($tables, $tables)
));

if ($bogons || !empty($entries)) {
	if ($bogons) {
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

if ($bogons || !empty($entries)) {
?>
<div>
	<div class="infoblock_open">
<?php
	$last_updated = exec('/usr/bin/grep -i -m 1 -E "^# last updated" /etc/' . escapeshellarg($tablename) . '|cut -d"(" -f2|tr -d ")" ');
	if ($last_updated != "") {
		print_info_box(gettext("Table last updated on ") . $last_updated, 'info');
	} else {
		print_info_box(gettext("Date of last update of table is unknown"), 'info');
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
		foreach ($entries as $entry):
			$entry = trim($entry);
?>
			<tr>
				<td>
					<?=$entry?>
				</td>
				<td>
					<?php if (!$bogons): ?>
						<a class="btn btn-xs btn-default" data-entry="<?=htmlspecialchars($entry)?>">Remove</a>
					<?php endif ?>
				</td>
			</tr>
<?php endforeach ?>
		</tbody>
	</table>
</div>
<?php if (empty($entries)): ?>
	<div class="alert alert-warning" role="alert">No entries exist in this table</div>
<?php endif ?>

<?php

include("foot.inc");
