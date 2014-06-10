<?php
/*
	diag_gmirror.php
	Copyright (C) 2014 Jim Pingle

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	notice, this list of conditions and the following disclaimer in the
	documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
	pfSense_BUILDER_BINARIES:	/sbin/gmirror	/sbin/geom	/usr/bin/grep	/usr/bin/egrep	/usr/bin/cut	/usr/bin/head
	pfSense_BUILDER_BINARIES:	/sbin/mount	/usr/bin/awk	/usr/bin/sed
	pfSense_MODULE:	gmirror
*/

##|+PRIV
##|*IDENT=page-diagnostics-gmirror
##|*NAME=Diagnostics: GEOM Mirrors
##|*DESCR=Allow access to the 'Diagnostics: GEOM Mirrors' page.
##|*MATCH=diag_gmirror.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("config.inc");
require_once("gmirror.inc");

$pgtitle = array(gettext("Diagnostics"), gettext("GEOM Mirrors"));

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?=$jsevents["body"]["onload"];?>">

<?php include("fbegin.inc"); ?>

<?PHP
$action_list = array(
	"forget" => gettext("Forget all formerly connected consumers"),
	"clear" => gettext("Remove metadata from disk"),
	"insert" => gettext("Insert consumer into mirror"),
	"remove" => gettext("Remove consumer from mirror"),
	"activate" => gettext("Reactivate consumer on mirror"),
	"deactivate" => gettext("Deactivate consumer from mirror"),
	"rebuild" => gettext("Force rebuild of mirror consumer"),
);

/* User tried to pass a bogus action */
if (!empty($_REQUEST['action']) && !array_key_exists($_REQUEST['action'], $action_list)) {
	header("Location: diag_gmirror.php");
	return;
}

if ($_POST) {
	if (!isset($_POST['confirm']) || ($_POST['confirm'] != gettext("Confirm"))) {
		header("Location: diag_gmirror.php");
		return;
	}
	$input_errors = "";

	if (($_POST['action'] != "clear") && !is_valid_mirror($_POST['mirror']))
		$input_errors[] = gettext("You must supply a valid mirror name.");

	if (!empty($_POST['consumer']) && !is_valid_consumer($_POST['consumer']))
		$input_errors[] = gettext("You must supply a valid consumer name");

	/* Additional action-specific validation that hasn't already been tested */
	switch ($_POST['action']) {
		case "insert":
			if (!is_consumer_unused($_POST['consumer']))
				$input_errors[] = gettext("Consumer is already in use and cannot be inserted. Remove consumer from existing mirror first.");
			if (gmirror_consumer_has_metadata($_POST['consumer']))
				$input_errors[] = gettext("Consumer has metadata from an existing mirror. Clear metadata before inserting consumer.");
			$mstat = gmirror_get_status_single($_POST['mirror']);
			if (strtoupper($mstat) != "COMPLETE")
				$input_errors[] = gettext("Mirror is not in a COMPLETE state, cannot insert consumer. Forget disconnected disks or wait for rebuild to finish.");
			break;
		case "clear":
			if (!is_consumer_unused($_POST['consumer']))
				$input_errors[] = gettext("Consumer is in use and cannot be cleared. Deactivate disk first.");
			if (!gmirror_consumer_has_metadata($_POST['consumer']))
				$input_errors[] = gettext("Consumer has no metadata to clear.");
			break;
		case "activate":
			if (is_consumer_in_mirror($_POST['consumer'], $_POST['mirror']))
				$input_errors[] = gettext("Consumer is already present on specified mirror.");
			if (!gmirror_consumer_has_metadata($_POST['consumer']))
				$input_errors[] = gettext("Consumer has no metadata and cannot be reactivated.");
			
			break;
		case "remove":
		case "deactivate":
		case "rebuild":
			if (!is_consumer_in_mirror($_POST['consumer'], $_POST['mirror']))
				$input_errors[] = gettext("Consumer must be present on the specified mirror.");
			break;
	}

$result = 0;
	if (empty($input_errors)) {
		switch ($_POST['action']) {
			case "forget":
				$result = gmirror_forget_disconnected($_POST['mirror']);
				break;
			case "clear":
				$result = gmirror_clear_consumer($_POST['consumer']);
				break;
			case "insert":
				$result = gmirror_insert_consumer($_POST['mirror'], $_POST['consumer']);
				break;
			case "remove":
				$result = gmirror_remove_consumer($_POST['mirror'], $_POST['consumer']);
				break;
			case "activate":
				$result = gmirror_activate_consumer($_POST['mirror'], $_POST['consumer']);
				break;
			case "deactivate":
				$result = gmirror_deactivate_consumer($_POST['mirror'], $_POST['consumer']);
				break;
			case "rebuild":
				$result = gmirror_force_rebuild($_POST['mirror'], $_POST['consumer']);
				break;
		}
		$redir = "Location: diag_gmirror.php";
		if ($result != 0) {
			$redir .= "?error=" . urlencode($result);
		}
		/* If we reload the page too fast, the gmirror information may be missing or not up-to-date. */
		sleep(3);
		header($redir);
		return;
	}
}

$mirror_status = gmirror_get_status();
$mirror_list = gmirror_get_mirrors();
$unused_disks = gmirror_get_disks();
$unused_consumers = array();
foreach ($unused_disks as $disk) {
	if (is_consumer_unused($disk))
		$unused_consumers = array_merge($unused_consumers, gmirror_get_all_unused_consumer_sizes_on_disk($disk));
}

if ($input_errors)
	print_input_errors($input_errors);
if ($_GET["error"] && ($_GET["error"] != 0))
	print_info_box(gettext("There was an error performing the chosen mirror operation. Check the System Log for details."));

?>
<form action="diag_gmirror.php" method="POST" id="gmirror_form" name="gmirror_form">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td id="mainarea">
			<div class="tabcont">
				<span class="vexpl">
					<span class="red">
						<strong><?=gettext("NOTE:")?>&nbsp;</strong>
					</span>
					<?=gettext("The options on this page are intended for use by advanced users only. This page is for managing existing mirrors, not creating new mirrors.")?>
					<br />&nbsp;
				</span>
				<p/>
				<table width="100%" border="0" cellpadding="6" cellspacing="0">

<?PHP if ($_GET["action"]): ?>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?PHP echo gettext("Confirm Action"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">&nbsp;</td>
						<td width="78%" class="vtable">
							<strong><?PHP echo gettext("Please confirm the selected action"); ?></strong>:
							<br />
							<br /><strong><?PHP echo gettext("Action"); ?>:</strong> <?PHP echo $action_list[$_GET["action"]]; ?>
							<input type="hidden" name="action" value="<?PHP echo htmlspecialchars($_GET["action"]); ?>" />
						<?PHP if (!empty($_GET["mirror"])): ?>
							<br /><strong><?PHP echo gettext("Mirror"); ?>:</strong> <?PHP echo htmlspecialchars($_GET["mirror"]); ?>
							<input type="hidden" name="mirror" value="<?PHP echo htmlspecialchars($_GET["mirror"]); ?>" />
						<?PHP endif; ?>
						<?PHP if (!empty($_GET["consumer"])): ?>
							<br /><strong><?PHP echo gettext("Consumer"); ?>:</strong> <?PHP echo htmlspecialchars($_GET["consumer"]); ?>
							<input type="hidden" name="consumer" value="<?PHP echo htmlspecialchars($_GET["consumer"]); ?>" />
						<?PHP endif; ?>
							<br />
							<br /><input type="submit" name="confirm" value="<?PHP echo gettext("Confirm"); ?>" />
						</td>
					</tr>
<?PHP else: ?>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?PHP echo gettext("GEOM Mirror information"); ?></td>
					</tr>

					<tr>
						<td width="22%" valign="top" class="vncell"><?PHP echo gettext("Mirror Status"); ?></td>
						<td width="78%" class="vtable">

						<table width="100%" border="0" cellspacing="0" cellpadding="0" summary="gmirror status">
							<tbody id="gmirror_status_table">
					<?PHP	if (count($mirror_status) > 0): ?>
							<tr>
							<td width="30%" class="vncellt"><?PHP echo gettext("Name"); ?></td>
							<td width="30%" class="vncellt"><?PHP echo gettext("Status"); ?></td>
							<td width="40%" class="vncellt"><?PHP echo gettext("Component"); ?></td>
							</tr>
						<?PHP	foreach ($mirror_status as $mirror => $name): 
								$components = count($name["components"]); ?>
								<tr>
								<td width="30%" rowspan="<?PHP echo $components; ?>" class="listr">
									<?PHP echo htmlspecialchars($name['name']); ?>
									<br />Size: <?PHP echo gmirror_get_mirror_size($name['name']); ?>
								</td>
								<td width="30%" rowspan="<?PHP echo $components; ?>" class="listr">
									<?PHP echo htmlspecialchars($name['status']); ?>
								<?PHP	if (strtoupper($name['status']) == "DEGRADED"): ?>
									<br /><a href="diag_gmirror.php?action=forget&amp;mirror=<?PHP echo htmlspecialchars($name['name']); ?>">[<?PHP echo gettext("Forget Disconnected Disks"); ?>]</a>
								<?PHP	endif; ?>
								</td>
								<td width="40%" class="listr">
									<?PHP echo $name['components'][0]; ?>
									<?PHP list($cname, $cstatus) = explode(" ", $name['components'][0], 2); ?>
									<br />
								<?PHP	if ((strtoupper($name['status']) == "COMPLETE") && (count($name["components"]) > 1)): ?>
									<a href="diag_gmirror.php?action=rebuild&amp;consumer=<?PHP echo htmlspecialchars($cname); ?>&amp;mirror=<?PHP echo htmlspecialchars($name['name']); ?>">[<?PHP echo gettext("Rebuild"); ?>]</a>
									<a href="diag_gmirror.php?action=deactivate&amp;consumer=<?PHP echo htmlspecialchars($cname); ?>&amp;mirror=<?PHP echo htmlspecialchars($name['name']); ?>">[<?PHP echo gettext("Deactivate"); ?>]</a>
									<a href="diag_gmirror.php?action=remove&amp;consumer=<?PHP echo htmlspecialchars($cname); ?>&amp;mirror=<?PHP echo htmlspecialchars($name['name']); ?>">[<?PHP echo gettext("Remove"); ?>]</a>
								<?PHP	endif; ?>
								</td>
								</tr>
							<?PHP	if (count($name["components"]) > 1):
									$morecomponents = array_slice($name["components"], 1); ?>
								<?PHP	foreach ($morecomponents as $component): ?>
										<tr>
										<td width="40%" class="listr">
											<?PHP echo $component; ?>
											<?PHP list($cname, $cstatus) = explode(" ", $component, 2); ?>
											<br />
										<?PHP	if ((strtoupper($name['status']) == "COMPLETE") && (count($name["components"]) > 1)): ?>
											<a href="diag_gmirror.php?action=rebuild&amp;consumer=<?PHP echo htmlspecialchars($cname); ?>&amp;mirror=<?PHP echo htmlspecialchars($name['name']); ?>">[<?PHP echo gettext("Rebuild"); ?>]</a>
											<a href="diag_gmirror.php?action=deactivate&amp;consumer=<?PHP echo htmlspecialchars($cname); ?>&amp;mirror=<?PHP echo htmlspecialchars($name['name']); ?>">[<?PHP echo gettext("Deactivate"); ?>]</a>
											<a href="diag_gmirror.php?action=remove&amp;consumer=<?PHP echo htmlspecialchars($cname); ?>&amp;mirror=<?PHP echo htmlspecialchars($name['name']); ?>">[<?PHP echo gettext("Remove"); ?>]</a>
										<?PHP	endif; ?>
										</td>
										</tr>
								<?PHP	endforeach; ?>
							<?PHP	endif; ?>
						<?PHP	endforeach; ?>
					<?PHP	else: ?>
							<tr><td colspan="3" class="listr"><?PHP echo gettext("No Mirrors Found"); ?></td></tr>
					<?PHP	endif; ?>
							</tbody>
						</table>
						<br /><?PHP echo gettext("Some disk operations may only be performed when there are multiple consumers present in a mirror."); ?>
						</td>
					</tr>

					<tr>
						<td colspan="2" valign="top" class="listtopic"><?PHP echo gettext("Consumer information"); ?></td>
					</tr>

					<tr>
						<td width="22%" valign="top" class="vncell"><?PHP echo gettext("Available Consumers"); ?></td>
						<td width="78%" class="vtable">

						<table width="100%" border="0" cellspacing="0" cellpadding="0" summary="consumer list">
							<tbody id="consumer_list">
					<?PHP	if (count($unused_consumers) > 0): ?>
							<tr>
							<td width="30%" class="vncellt"><?PHP echo gettext("Name"); ?></td>
							<td width="30%" class="vncellt"><?PHP echo gettext("Size"); ?></td>
							<td width="40%" class="vncellt"><?PHP echo gettext("Add to Mirror"); ?></td>
							</tr>
						<?PHP	foreach ($unused_consumers as $consumer): ?>
								<tr>
								<td width="30%" class="listr">
									<?PHP echo htmlspecialchars($consumer['name']); ?>
								</td>
								<td width="30%" class="listr"><?PHP echo htmlspecialchars($consumer['size']); ?> <?PHP echo htmlspecialchars($consumer['humansize']); ?></td>
								<td width="40%" class="listr">
							<?PHP	$oldmirror = gmirror_get_consumer_metadata_mirror($consumer['name']);
								if ($oldmirror): ?>
									<a href="diag_gmirror.php?action=activate&amp;consumer=<?PHP echo htmlspecialchars($consumer['name']); ?>&amp;mirror=<?PHP echo htmlspecialchars($oldmirror); ?>">[<?PHP echo gettext("Reactivate on:"); ?> <?PHP echo htmlspecialchars($oldmirror); ?>]</a>
									<br /><a href="diag_gmirror.php?action=clear&amp;consumer=<?PHP echo htmlspecialchars($consumer['name']); ?>">[<?PHP echo gettext("Remove metadata from disk"); ?>]</a>
							<?PHP	else: ?>
							<?PHP	foreach ($mirror_list as $mirror):
									$mirror_size = gmirror_get_mirror_size($mirror);
									$consumer_size = gmirror_get_unused_consumer_size($consumer['name']);
								?>
								<?PHP	if ($consumer_size > $mirror_size): ?>
									<a href="diag_gmirror.php?action=insert&amp;consumer=<?PHP echo htmlspecialchars($consumer['name']); ?>&amp;mirror=<?PHP echo htmlspecialchars($mirror); ?>"><?PHP echo htmlspecialchars($mirror); ?></a>
								<?PHP	endif; ?>
							<?PHP	endforeach; ?>
							<?PHP	endif; ?>
								</td>
								</tr>
						<?PHP	endforeach; ?>
					<?PHP	else: ?>
							<tr><td colspan="3" class="listr"><?PHP echo gettext("No unused consumers found"); ?></td></tr>
					<?PHP	endif; ?>
							</tbody>
						</table>
						<br /><?PHP echo gettext("Consumers may only be added to a mirror if they are larger than the size of the mirror."); ?>
						</td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="">&nbsp;</td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class=""><?PHP echo gettext("To repair a failed mirror, first perform a 'Forget' command on the mirror, followed by an 'insert' action on the new consumer."); ?></td>
					</tr>
<?PHP endif;?>
				</table>
			</div>
		</td>
	</tr>
</table>
</form>
<?php require("fend.inc"); ?>
</body>
</html>

<?php

// Clear the loading indicator
echo "<script type=\"text/javascript\">";
echo "jQuery('#loading').html('');";
echo "</script>";

?>
