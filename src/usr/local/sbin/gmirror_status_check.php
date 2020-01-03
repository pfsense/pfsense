#!/usr/local/bin/php-cgi -f
<?php
/*
 * gmirror_status_check.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2013-2020 Rubicon Communications, LLC (Netgate)
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

require_once("config.inc");
require_once("notices.inc");
require_once("globals.inc");
require_once("gmirror.inc");

global $g;
$status_file = "{$g['varrun_path']}/gmirror.status";

$mirror_status = gmirror_get_status();
$mirror_list = array_keys($mirror_status);
sort($mirror_list);
$notices = array();

// Check for gmirror.status
if (file_exists($status_file)) {
	// If it exists, read status in
	$previous_mirror_status = unserialize(file_get_contents($status_file));
	$previous_mirror_list = array_keys($previous_mirror_status);
	sort($previous_mirror_list);
	if (count($previous_mirror_status) > 0) {
		// Check list of current mirrors vs old mirrors, notify if one has appeared/disappeared
		if ($mirror_list != $previous_mirror_list) {
			$notices[] = sprintf(gettext("List of mirrors changed. Old: (%s) New: (%s)"), implode(", ", $previous_mirror_list), implode(", ", $mirror_list));
		}

		// For each mirror, check the mirror status, notify if changed
		foreach ($mirror_list as $mirror) {
			if (is_array($previous_mirror_status[$mirror])) {
				// Notify if the status changed
				if ($mirror_status[$mirror]['status'] != $previous_mirror_status[$mirror]['status']) {
					$notices[] = sprintf(gettext("Mirror %s status changed from %s to %s."), $mirror, $previous_mirror_status[$mirror]['status'], $mirror_status[$mirror]['status']);
				}
				// Notify if the drive count changed
				if (count($mirror_status[$mirror]['components']) != count($previous_mirror_status[$mirror]['components'])) {
					// Notify if the consumer count changed.
					$notices[] = sprintf(gettext("Mirror %s consumer count changed from %d to %d."), $mirror, count($previous_mirror_status[$mirror]['components']), count($mirror_status[$mirror]['components']));
				}
				if (strtoupper($mirror_status[$mirror]['status']) == "DEGRADED") {
					// Check the drive status as it may be different.
					asort($mirror_status[$mirror]['components']);
					asort($previous_mirror_status[$mirror]['components']);
					if ($mirror_status[$mirror]['components'] != $previous_mirror_status[$mirror]['components']) {
						// if in SYNC state, only notify in 25% increments
						foreach ($mirror_status[$mirror]['components'] as $drive) {
							$drive = preg_replace('/[\(\)\,\%]/', '', $drive);
							$component_data = explode(" ", $drive);
							if ($component_data[1] == 'SYNCHRONIZING') {
								if (($component_data[2] % 25) == 0) {
									$notices[] = sprintf(gettext("Mirror %s drive status changed. Old: (%s) New: (%s)"),
										$mirror,
										implode(", ", $previous_mirror_status[$mirror]['components']),
										implode(", ", $mirror_status[$mirror]['components']));
								}
							}
						}
					}
				}
			}
		}
	}
}
if (count($notices)) {
	file_notice("gmirror", implode("\n ", $notices), "GEOM Mirror Status Change", 1);
}
// Write out current status if changed
if ($mirror_status != $previous_mirror_status) {
	file_put_contents($status_file, serialize($mirror_status));
}

?>
