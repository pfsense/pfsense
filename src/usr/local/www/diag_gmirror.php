<?php
/*
	diag_gmirror.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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

	if (($_POST['action'] != "clear") && !is_valid_mirror($_POST['mirror'])) {
		$input_errors[] = gettext("You must supply a valid mirror name.");
	}

	if (!empty($_POST['consumer']) && !is_valid_consumer($_POST['consumer'])) {
		$input_errors[] = gettext("You must supply a valid consumer name");
	}

	/* Additional action-specific validation that hasn't already been tested */
	switch ($_POST['action']) {
		case "insert":
			if (!is_consumer_unused($_POST['consumer'])) {
				$input_errors[] = gettext("Consumer is already in use and cannot be inserted. Remove consumer from existing mirror first.");
			}
			if (gmirror_consumer_has_metadata($_POST['consumer'])) {
				$input_errors[] = gettext("Consumer has metadata from an existing mirror. Clear metadata before inserting consumer.");
			}
			$mstat = gmirror_get_status_single($_POST['mirror']);
			if (strtoupper($mstat) != "COMPLETE") {
				$input_errors[] = gettext("Mirror is not in a COMPLETE state, cannot insert consumer. Forget disconnected disks or wait for rebuild to finish.");
			}
			break;

		case "clear":
			if (!is_consumer_unused($_POST['consumer'])) {
				$input_errors[] = gettext("Consumer is in use and cannot be cleared. Deactivate disk first.");
			}
			if (!gmirror_consumer_has_metadata($_POST['consumer'])) {
				$input_errors[] = gettext("Consumer has no metadata to clear.");
			}
			break;

		case "activate":
			if (is_consumer_in_mirror($_POST['consumer'], $_POST['mirror'])) {
				$input_errors[] = gettext("Consumer is already present on specified mirror.");
			}
			if (!gmirror_consumer_has_metadata($_POST['consumer'])) {
				$input_errors[] = gettext("Consumer has no metadata and cannot be reactivated.");
			}

			break;

		case "remove":
		case "deactivate":
		case "rebuild":
			if (!is_consumer_in_mirror($_POST['consumer'], $_POST['mirror'])) {
				$input_errors[] = gettext("Consumer must be present on the specified mirror.");
			}
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
	if (is_consumer_unused($disk)) {
		$unused_consumers = array_merge($unused_consumers, gmirror_get_all_unused_consumer_sizes_on_disk($disk));
	}
}

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($_GET["error"] && ($_GET["error"] != 0)) {
	print_info_box(gettext("There was an error performing the chosen mirror operation. Check the System Log for details."));
}

?>
<form action="diag_gmirror.php" method="POST" id="gmirror_form" name="gmirror_form">

<!-- Confirmation screen -->
<?php
if ($_GET["action"]):  ?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Confirm Action')?></h2></div>
		<div class="panel-body">
			<strong><?=gettext('Please confirm the selected action: '); ?></strong>
			<span style="color:green"><?=$action_list[$_GET["action"]]; ?></span>
			<input type="hidden" name="action" value="<?=htmlspecialchars($_GET['action']); ?>" />
<?php
	if (!empty($_GET["mirror"])): ?>
			<br /><strong><?=gettext("Mirror: "); ?></strong>
			<?=htmlspecialchars($_GET['mirror']); ?>
			<input type="hidden" name="mirror" value="<?=htmlspecialchars($_GET['mirror']); ?>" />
<?php
	endif; ?>

<?php
	if (!empty($_GET["consumer"])): ?>
			<br /><strong><?=gettext("Consumer"); ?>:</strong>
			<?=htmlspecialchars($_GET["consumer"]); ?>
			<input type="hidden" name="consumer" value="<?=htmlspecialchars($_GET["consumer"]); ?>" />
<?php
	endif; ?>
			<br />
			<br /><input type="submit" name="confirm" class="btn btn-sm btn-primary" value="<?=gettext("Confirm"); ?>" />
		</div>
	</div>
<?php
else:
	// Status/display page
	print_info_box(gettext("The options on this page are intended for use by advanced users only. This page is for managing existing mirrors, not creating new mirrors."));
?>

	<!-- GEOM mirror table -->
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('GEOM Mirror Information - Mirror Status')?></h2></div>
		<div class="panel-body table-responsive">

<?php
	if (count($mirror_status) > 0): ?>

			<table class="table table-striped stable-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("Name"); ?></th>
						<th><?=gettext("Status"); ?></th>
						<th><?=gettext("Component"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php
		foreach ($mirror_status as $mirror => $name):
								$components = count($name["components"]); ?>
					<tr>
						<td rowspan="<?=$components; ?>">
							<?=htmlspecialchars($name['name']); ?><br />Size: <?=gmirror_get_mirror_size($name['name']); ?>
						</td>
						<td rowspan="<?=$components; ?>">
							<?=htmlspecialchars($name['status']); ?>
<?php
			if (strtoupper($name['status']) == "DEGRADED"): ?>
							<br /><a href="diag_gmirror.php?action=forget&amp;mirror=<?=htmlspecialchars($name['name']); ?>">[<?=gettext("Forget Disconnected Disks"); ?>]</a>
<?php
			endif; ?>
						</td>
						<td>
							<?=$name['components'][0]; ?>
							<?php list($cname, $cstatus) = explode(" ", $name['components'][0], 2); ?><br />
<?php
			if ((strtoupper($name['status']) == "COMPLETE") && (count($name["components"]) > 1)): ?>
							<a class="btn btn-xs btn-info" href="diag_gmirror.php?action=rebuild&amp;consumer=<?=htmlspecialchars($cname); ?>&amp;mirror=<?=htmlspecialchars($name['name']); ?>">[<?=gettext("Rebuild"); ?>]</a>
							<a class="btn btn-xs btn-info" href="diag_gmirror.php?action=deactivate&amp;consumer=<?=htmlspecialchars($cname); ?>&amp;mirror=<?=htmlspecialchars($name['name']); ?>">[<?=gettext("Deactivate"); ?>]</a>
							<a class="btn btn-xs btn-danger" href="diag_gmirror.php?action=remove&amp;consumer=<?=htmlspecialchars($cname); ?>&amp;mirror=<?=htmlspecialchars($name['name']); ?>">[<?=gettext("Remove"); ?>]</a>
<?php
			endif; ?>
						</td>
					</tr>
<?php
			if (count($name["components"]) > 1):
				$morecomponents = array_slice($name["components"], 1); ?>
<?php
				foreach ($morecomponents as $component): ?>
					<tr>
						<td>
							<?=$component; ?>
							<?php list($cname, $cstatus) = explode(" ", $component, 2); ?><br />
<?php
					if ((strtoupper($name['status']) == "COMPLETE") && (count($name["components"]) > 1)): ?>
							<a class="btn btn-xs btn-info" href="diag_gmirror.php?action=rebuild&amp;consumer=<?=htmlspecialchars($cname); ?>&amp;mirror=<?=htmlspecialchars($name['name']); ?>">[<?=gettext("Rebuild"); ?>]</a>
							<a class="btn btn-xs btn-info" href="diag_gmirror.php?action=deactivate&amp;consumer=<?=htmlspecialchars($cname); ?>&amp;mirror=<?=htmlspecialchars($name['name']); ?>">[<?=gettext("Deactivate"); ?>]</a>
							<a class="btn btn-xs btn-danger" href="diag_gmirror.php?action=remove&amp;consumer=<?=htmlspecialchars($cname); ?>&amp;mirror=<?=htmlspecialchars($name['name']); ?>">[<?=gettext("Remove"); ?>]</a>
<?php
					endif; ?>
						</td>
					</tr>
<?php
				endforeach; ?>
<?php
			endif; ?>
<?php
		endforeach; ?>
				</tbody>
			</table>
<?php
	else: ?>
		<?=gettext("No Mirrors Found"); ?>

<?php
	endif; ?>

		</div>
	</div>

<?php print_info_box(gettext("Some disk operations may only be performed when there are multiple consumers present in a mirror."), 'default'); ?>

	<!-- Consumer information table -->
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Consumer Information - Available Consumers')?></h2></div>
		<div class="panel-body table-responsive">
<?php
	if (count($unused_consumers) > 0): ?>
			<table class="table table-striped stable-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("Name"); ?></th>
						<th><?=gettext("Size"); ?></th>
						<th><?=gettext("Add to Mirror"); ?></th>
					</tr>
				</thead>

				<tbody>
<?php
		foreach ($unused_consumers as $consumer): ?>
					<tr>
						<td>
							<?=htmlspecialchars($consumer['name']); ?>
						</td>
						<td>
							<?=htmlspecialchars($consumer['size']); ?>
							<?=htmlspecialchars($consumer['humansize']); ?>
						</td>
						<td>
<?php
			$oldmirror = gmirror_get_consumer_metadata_mirror($consumer['name']);

			if ($oldmirror): ?>
							<a class="btn btn-xs btn-success" href="diag_gmirror.php?action=activate&amp;consumer=<?=htmlspecialchars($consumer['name']); ?>&amp;mirror=<?=htmlspecialchars($oldmirror); ?>">
								<?=gettext("Reactivate on:") . ' ' . htmlspecialchars($oldmirror); ?>
							</a>
							<br />
							<a class="btn btn-xs btn-danger" href="diag_gmirror.php?action=clear&amp;consumer=<?=htmlspecialchars($consumer['name']); ?>">
								<?=gettext("Remove metadata from disk"); ?>
							</a>
<?php
			else: ?>
<?php
				foreach ($mirror_list as $mirror):
					$mirror_size = gmirror_get_mirror_size($mirror);
					$consumer_size = gmirror_get_unused_consumer_size($consumer['name']);

					if ($consumer_size > $mirror_size): ?>
							<a class="btn btn-xs btn-info" href="diag_gmirror.php?action=insert&amp;consumer=<?=htmlspecialchars($consumer['name']); ?>&amp;mirror=<?=htmlspecialchars($mirror); ?>">
								<?=htmlspecialchars($mirror); ?>
							</a>
<?php
					endif; ?>
<?php
				endforeach; ?>

<?php
			endif; ?>
						</td>
					</tr>
<?php
		endforeach; ?>
				</tbody>
			</table>
<?php
	else: ?>
		<?=gettext("No unused consumers found"); ?>
<?php
	endif; ?>
		</div>
	</div>
<?php
	print_info_box(gettext("Consumers may only be added to a mirror if they are larger than the size of the mirror.") . '<br />' .
				   gettext("To repair a failed mirror, first perform a 'Forget' command on the mirror, followed by an 'insert' action on the new consumer."), 'default');
endif; ?>
</form>

<?php
require("foot.inc");
