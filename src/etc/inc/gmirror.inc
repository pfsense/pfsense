<?php
/*
 * gmirror.inc
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

global $balance_methods;
$balance_methods = array("load", "prefer", "round-robin", "split");

/* Create a status array for each mirror and its disk components. */
function gmirror_get_status() {
	$status = "";
	exec("/sbin/gmirror status -s", $status);
	$mirrors = array();

	/* Empty output = no mirrors found */
	if (count($status) > 0) {
		/* Loop through gmirror status output. */
		foreach ($status as $line) {
			/* Split the line by whitespace */
			$all = preg_split("/[\s\t]+/", trim($line), 3);
			if (count($all) == 3) {
				/* If there are three items on a line, it is mirror name, status, and component */
				$currentmirror = basename($all[0]);
				$mirrors[$currentmirror]['name'] = basename($all[0]);
				$mirrors[$currentmirror]['status'] = $all[1];
				if (!is_array($mirrors[$currentmirror]['components'])) {
					$mirrors[$currentmirror]['components'] = array();
				}
				$mirrors[$currentmirror]['components'][] = $all[2];
			}
		}
	}
	/* Return an hash of mirrors and components */
	return $mirrors;
}

/* Get only status word for a single mirror. */
function gmirror_get_status_single($mirror) {
	$status = "";
	$mirror_status = gmirror_get_status();
	return $mirror_status[$mirror]['status'];
}

/* Generate an HTML formatted status for mirrors and disks in a small format for the widget */
function gmirror_html_status() {
	$mirrors = gmirror_get_status();
	$output = "";
	if (count($mirrors) < 1) {
		print_info_box(gettext("No mirrors found."), 'warning', false);
		return;
	}

?>
<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th><?=gettext("Name")?></td>
			<th><?=gettext("Status")?></td>
			<th><?=gettext("Component")?></td>
		</tr>
	</thead>
	<tbody>
<?php foreach ($mirrors as $mirror => $name): ?>
		<tr>
			<td rowspan="<?=count($name["components"])?>"><?=$name['name']?></td>
			<td rowspan="<?=count($name["components"])?>"><?=$name['status']?></td>
			<td><?=$name['components'][0]?></td>
		</tr>
<?php if (count($name["components"]) > 1): ?>
		<?php foreach (array_slice($name["components"], 1) as $component): ?>
			<tr>
				<td><?=$component?></td>
			</tr>
		<?php endforeach; ?>
	<?php endif; ?>
<?php endforeach; ?>
	</tbody>
</table>
<?php
}

/* List all disks in the system (potential gmirror targets) */
function gmirror_get_disks() {
	$disklist = "";
	/* Get a list of disks in a scriptable way, exclude optical drives */
	exec("/sbin/geom disk status -s | /usr/bin/grep -v '[[:blank:]]*cd[[:digit:]]*' | /usr/bin/awk '{print $1;}'", $disklist);
	return $disklist;
}

/* List all potential gmirror consumers */
function gmirror_get_unused_consumers() {
	$consumerlist = "";
	$disklist = gmirror_get_disks();
	/* Get a list of consumers, exclude existing mirrors and diskid entries */
	exec("/sbin/geom part status -s | /usr/bin/egrep -v '(mirror|diskid)' | /usr/bin/awk '{print $1, $3;}'", $consumerlist);
	$all_consumers = array();
	foreach ($consumerlist as $cl) {
		$parts = explode(" ", $cl);
		foreach ($parts as $part) {
			$all_consumers[] = $part;
		}
	}
	foreach ($disklist as $d) {
		if (!is_consumer_used($d) && !in_array($d, $all_consumers)) {
			$all_consumers[] = $d;
		}
	}
	return $all_consumers;
}

/* List all existing geom mirrors */
function gmirror_get_mirrors() {
	$mirrorlist = "";
	exec("/sbin/gmirror list | /usr/bin/grep '^Geom name:' | /usr/bin/awk '{print $3;}'", $mirrorlist);
	return $mirrorlist;
}


/* List all consumers for a given mirror */
function gmirror_get_consumers_in_mirror($mirror) {
	if (!is_valid_mirror($mirror)) {
		return array();
	}

	$consumers = array();
	exec("/sbin/gmirror status -s " . escapeshellarg($mirror) . " | /usr/bin/awk '{print $3;}'", $consumers);
	return $consumers;
}

/* Test if a given consumer is a member of an existing mirror */
function is_consumer_in_mirror($consumer, $mirror) {
	if (!is_valid_consumer($consumer) || !is_valid_mirror($mirror)) {
		return false;
	}

	$mirrorconsumers = gmirror_get_consumers_in_mirror($mirror);
	return in_array(basename($consumer), $mirrorconsumers);
}

/* Test if a mirror exists */
function is_valid_mirror($mirror) {
	$mirrors = gmirror_get_mirrors();
	return in_array($mirror, $mirrors);
}

/* Test if a disk is valid/exists */
function is_valid_disk($disk) {
	$adisks = gmirror_get_disks();
	return in_array(basename($disk), $adisks);
}

/* Test if a consumer is valid and in use in a mirror */
function is_consumer_used($consumer) {
	$found = false;
	$mirrors = gmirror_get_mirrors();
	foreach ($mirrors as $mirror) {
		$consumers = gmirror_get_consumers_in_mirror($mirror);
		if (in_array($consumer, $consumers)) {
			return true;
		}
	}
	return false;
}

/* Test if a consumer is valid and not in use */
function is_consumer_unused($consumer) {
	$consumers = gmirror_get_unused_consumers();
	return in_array($consumer, $consumers);
}

/* Test if a consumer is valid (either a disk or partition) */
function is_valid_consumer($consumer) {
	return (is_consumer_unused($consumer) || is_consumer_used($consumer));
}

/* Remove all disconnected drives from a mirror */
function gmirror_forget_disconnected($mirror) {
	if (!is_valid_mirror($mirror)) {
		return false;
	}
	return mwexec("/sbin/gmirror forget " . escapeshellarg($mirror));
}

/* Insert another consumer into a mirror */
function gmirror_insert_consumer($mirror, $consumer) {
	if (!is_valid_mirror($mirror) || !is_valid_consumer($consumer)) {
		return false;
	}
	return mwexec("/sbin/gmirror insert " . escapeshellarg($mirror) . " " . escapeshellarg($consumer));
}

/* Remove consumer from a mirror and clear its metadata */
function gmirror_remove_consumer($mirror, $consumer) {
	if (!is_valid_mirror($mirror) || !is_valid_consumer($consumer)) {
		return false;
	}
	return mwexec("/sbin/gmirror remove " . escapeshellarg($mirror) . " " . escapeshellarg($consumer));
}

/* Wipe geom info from drive (if mirror is not running) */
function gmirror_clear_consumer($consumer) {
	if (!is_valid_consumer($consumer)) {
		return false;
	}
	return mwexec("/sbin/gmirror clear " . escapeshellarg($consumer));
}

/* Find the balance method used by a given mirror */
function gmirror_get_mirror_balance($mirror) {
	if (!is_valid_mirror($mirror)) {
		return false;
	}
	$balancemethod = "";
	exec("/sbin/gmirror list " . escapeshellarg($mirror) . " | /usr/bin/grep '^Balance:' | /usr/bin/awk '{print $2;}'", $balancemethod);
	return $balancemethod[0];
}

/* Change balance algorithm of the mirror */
function gmirror_configure_balance($mirror, $balancemethod) {
	global $balance_methods;
	if (!is_valid_mirror($mirror) || !in_array($balancemethod, $balance_methods)) {
		return false;
	}
	return mwexec("/sbin/gmirror configure -b " . escapeshellarg($balancemethod) . " " . escapeshellarg($mirror));
}

/* Force a mirror member to rebuild */
function gmirror_force_rebuild($mirror, $consumer) {
	if (!is_valid_mirror($mirror) || !is_valid_consumer($consumer)) {
		return false;
	}
	return mwexec("/sbin/gmirror rebuild " . escapeshellarg($mirror) . " " . escapeshellarg($consumer));
}

/* Show all metadata on the physical consumer */
function gmirror_get_consumer_metadata($consumer) {
	if (!is_valid_consumer($consumer)) {
		return array();
	}
	$output = array();
	exec("/sbin/gmirror dump " . escapeshellarg($consumer), $output);
	return array_map('trim', $output);
}

/* Test if a consumer has metadata, indicating it is a member of a mirror (active or inactive) */
function gmirror_consumer_has_metadata($consumer) {
	return (count(gmirror_get_consumer_metadata($consumer)) > 0);
}

/* Find the mirror to which this consumer belongs */
function gmirror_get_consumer_metadata_mirror($consumer) {
	if (!is_valid_consumer($consumer)) {
		return array();
	}
	$metadata = gmirror_get_consumer_metadata($consumer);
	foreach ($metadata as $line) {
		if (substr($line, 0, 5) == "name:") {
			list ($key, $value) = explode(":", $line, 2);
			return trim($value);
		}
	}
}

/* Deactivate consumer, removing it from service in the mirror, but leave metadata intact */
function gmirror_deactivate_consumer($mirror, $consumer) {
	if (!is_valid_mirror($mirror) || !is_valid_consumer($consumer)) {
		return false;
	}
	return mwexec("/sbin/gmirror deactivate " . escapeshellarg($mirror) . " " . escapeshellarg($consumer));
}

/* Reactivate a deactivated consumer */
function gmirror_activate_consumer($mirror, $consumer) {
	if (!is_valid_mirror($mirror) || !is_valid_consumer($consumer)) {
		return false;
	}
	return mwexec("/sbin/gmirror activate " . escapeshellarg($mirror) . " " . escapeshellarg($consumer));
}

/* Find the size of the given mirror */
function gmirror_get_mirror_size($mirror) {
	if (!is_valid_mirror($mirror)) {
		return false;
	}
	$mirrorsize = "";
	exec("/sbin/gmirror list " . escapeshellarg($mirror) . " | /usr/bin/grep 'Mediasize:' | /usr/bin/head -n 1 | /usr/bin/awk '{print $2;}'", $mirrorsize);
	return $mirrorsize[0];
}

/* Return a list of all potential consumers on a disk with sizes. The geom part
	list output is a little odd, we can't get the output for just the disk, if the disk contains
	slices those get output also. */
function gmirror_get_all_unused_consumer_sizes_on_disk($disk) {
	if (!is_valid_disk($disk) || !is_consumer_unused($disk)) {
		return array();
	}
	$output = array();
	exec("/sbin/geom part list " . escapeshellarg($disk) . " | /usr/bin/egrep '(Name:|Mediasize:)' | /usr/bin/cut -c4- | /usr/bin/sed -l -e 'N;s/\\nMediasize://;P;D;' | /usr/bin/cut -c7-", $output);
	if (empty($output)) {
		exec("/sbin/geom disk list " . escapeshellarg($disk) . " | /usr/bin/egrep '(Name:|Mediasize:)' | /usr/bin/cut -c4- | /usr/bin/sed -l -e 'N;s/\\nMediasize://;P;D;' | /usr/bin/cut -c7-", $output);
	}
	$disk_contents = array();
	foreach ($output as $line) {
		list($name, $size, $humansize) = explode(" ", $line, 3);
		$consumer = array();
		$consumer['name'] = $name;
		$consumer['size'] = $size;
		$consumer['humansize'] = $humansize;
		$disk_contents[] = $consumer;
	}
	return $disk_contents;
}

/* Get only the size for one specific potential consumer. */
function gmirror_get_unused_consumer_size($consumer) {
	$consumersizes = gmirror_get_all_unused_consumer_sizes_on_disk($consumer);
	foreach ($consumersizes as $csize) {
		if ($csize['name'] == $consumer) {
			return $csize['size'];
		}
	}
	return -1;
}
?>
