<?php
/*
 * acbupload.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
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

/*
 * This file is called by cron every minute to upload staged ACB entries.
 * This allows the GUI to remain responsive.
 * ACB deletes backup files after upload no matter the result.
 */

require_once("filter.inc");
require_once("notices.inc");
require_once("acb.inc");

/*
 * Do not attempt to upload while the system is still booting since the
 * WAN is likely still down. The next scheduled job should pick up the
 * file(s) to be uploaded.
 */
if (is_platform_booting()) {
	return;
}

$lockfile = "/tmp/acb.lock";
/* Check for stale lock */
if (file_exists($lockfile)) {
	if (time() - filemtime($lockfile) > (60 * 60)) {
		/* The lock file is more than an hour old.
		 * Something probably went wrong. */
		unlink_if_exists($lockfile);
		log_error(gettext('Stale AutoConfigBackup lock file removed'));
	} else {
		exit();
	}
}

touch($lockfile);

/* List file pairs ACB has staged for upload */
$files = glob(g_get('acbbackuppath') . '*.form');

if (count($files) > 0) {
	/* Sort staged backups, oldest first */
	usort($files, function($a, $b) {
		return filemtime($a) - filemtime($b);
	});

	/* Upload staged backups to the ACB service */
	$notice_text = sprintf(gettext('Starting upload of staged AutoConfigBackup encrypted configuration backups to %s'), $acb_base_url);
	log_error($notice_text);
	update_filter_reload_status($notice_text);
	foreach ($files as $file) {
		$basename = basename($file, ".form");
		acb_backup_upload($basename);
		/* Sleep for one second between uploads to help ensure the
		 * ACB service gives each backup entry a unique timestamp. */
		sleep(1);
	}
}

unlink_if_exists($lockfile);
