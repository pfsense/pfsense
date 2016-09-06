#!/usr/local/bin/php-cgi -f
<?php
/*
 * gmirror_status_check.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
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
if (count($notices)) {
	file_notice("gmirror", implode("\n ", $notices), "GEOM Mirror Status Change", 1);
}
// Write out current status if changed
if ($mirror_status != $previous_mirror_status) {
	file_put_contents($status_file, serialize($mirror_status));
}

?>
