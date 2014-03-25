<?php
/*
	diag_tables.php
	Copyright (C) 2010 Jim Pingle

	Portions borrowed from diag_dump_states.php:
	Copyright (C) 2010 Scott Ullrich
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
	pfSense_BUILDER_BINARIES:	/sbin/pfctl
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-diagnostics-tables
##|*NAME=Diagnostics: PF Table IP addresses
##|*DESCR=Allow access to the 'Diagnostics: Tables' page.
##|*MATCH=diag_tables.php*
##|-PRIV

$pgtitle = array(gettext("Diagnostics"), gettext("Tables"));
$shortcut_section = "aliases";

require_once("guiconfig.inc");

// Set default table
$tablename = "sshlockout";

if($_REQUEST['type'])
	$tablename = $_REQUEST['type'];

if($_REQUEST['delete']) {
	if(is_ipaddr($_REQUEST['delete']) || is_subnet($_REQUEST['delete'])) {
		exec("/sbin/pfctl -t " . escapeshellarg($_REQUEST['type']) . " -T delete " . escapeshellarg($_REQUEST['delete']), $delete);
		echo htmlentities($_REQUEST['delete']);
	}
	exit;
}

if($_REQUEST['deleteall']) {
	exec("/sbin/pfctl -t " . escapeshellarg($tablename) . " -T show", $entries);
	if(is_array($entries)) {
		foreach($entries as $entryA) {
			$entry = trim($entryA);
			exec("/sbin/pfctl -t " . escapeshellarg($tablename) . " -T delete " . escapeshellarg($entry), $delete);
		}
	}
}

if((($tablename == "bogons") || ($tablename == "bogonsv6")) && ($_POST['Download'])) {
	mwexec_bg("/etc/rc.update_bogons.sh now");
	$maxtimetowait = 0;
	$loading = true;
	while($loading == true) {
		$isrunning = `/bin/ps awwwux | /usr/bin/grep -v grep | /usr/bin/grep bogons`;
		if($isrunning == "")
			$loading = false;
		$maxtimetowait++;
		if($maxtimetowait > 89)
			$loading = false;
		sleep(1);
	}
	if($maxtimetowait < 90)
		$savemsg = gettext("The bogons database has been updated.");
}

exec("/sbin/pfctl -t " . escapeshellarg($tablename) . " -T show", $entries);
exec("/sbin/pfctl -sT", $tables);

include("head.inc");
include("fbegin.inc");

?>

<?php if ($savemsg) print_info_box($savemsg); ?>
<form method='post'>

<script type="text/javascript">
	function method_change(entrytype) {
		window.location='diag_tables.php?type=' + entrytype;
	}
	function del_entry(entry) {
		jQuery.ajax("diag_tables.php?type=<?php echo htmlspecialchars($tablename);?>&delete=" + entry, {
		complete: function(response) {
			if (200 == response.status) {
				// Escape all dots to not confuse jQuery selectors
				name = response.responseText.replace(/\./g,'\\.');
				name = name.replace(/\//g,'\\/');
				jQuery('#' + name).fadeOut(1000);
			}
		}
		});
	}
</script>

<?=gettext("Table:");?>
<select id='type' onChange='method_change(jQuery("#type").val());' name='type'>
	<?php foreach ($tables as $table) {
		echo "<option name='{$table}' value='{$table}'";
		if ($tablename == $table)
			echo " selected ";
		echo ">{$table}</option>\n";
		}
	?>
</select>

<p/>

<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td class="listhdrr"><?=gettext("IP Address");?></td>
	</tr>
<?php $count = 0; foreach($entries as $entryA): ?>
	<?php $entry = trim($entryA); ?>
	<tr id='<?=$entry?>'>
		<td>
			<?php echo $entry; ?>
		</td>
		<td>
			<?php if ( ($tablename != "bogons") && ($tablename != "bogonsv6") ) { ?>
			<a onClick='del_entry("<?=htmlspecialchars($entry)?>");'>
				<img img src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif">
			<?php } ?>
			</a>
		</td>
	</tr>
<?php $count++; endforeach; ?>
<?php
	if($count == 0)
		if( ($tablename == "bogons") || ($tablename == "bogonsv6") )
			echo "<p/>" . gettext("No entries exist in this table.") . "&nbsp&nbsp" . "<input name='Download' type='submit' class='formbtn' value='" . gettext("Download") . "'> " . gettext(" the latest bogon data.");
		else
			echo "<p/>" . gettext("No entries exist in this table.");
?>

<?php
	if($count > 0)
		if( ($tablename == "bogons") || ($tablename == "bogonsv6") ) {
			$last_updated = exec('/usr/bin/grep -i -m 1 -E "^# last updated" /etc/' . escapeshellarg($tablename));
			echo "<p/>&nbsp<b>$count</b> " . gettext("entries in this table.") . "&nbsp&nbsp" . "<input name='Download' type='submit' class='formbtn' value='" . gettext("Download") . "'> " . gettext(" the latest bogon data.") . "<br />" . "$last_updated";
		}
		else
			echo "<p/>" . gettext("Delete") . " <a href='diag_tables.php?deleteall=true&type=" . htmlspecialchars($tablename) . "'>" . gettext("all") . "</a> " . "<b>$count</b> " . gettext("entries in this table.");
?>

</table>

<?php include("fend.inc"); ?>
