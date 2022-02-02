#!/usr/local/bin/php-cgi -q
<?php
/*
 * captiveportal_gather_stats.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011 Warren Baker
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2013-2022 Rubicon Communications, LLC (Netgate)
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

require_once("functions.inc");
require_once("captiveportal.inc");
require_once("util.inc");

global $cpzone;

$cpzone = $argv[1];
$type = $argv[2];

/* read in captive portal db */
$cpdb = captiveportal_read_db();

/* determine number of logged in users */
$no_users = count($cpdb);
$concurrent_users = $no_users;

/* set initial user count to zero */
$current_user_count = 0;

/* tmp file to use to store old data (per interface)*/
$tmpfile = "{$g['vardb_path']}/captiveportal_online_users";

if (empty($type)) {
	exit;
}

/* echo the rrd required syntax */
echo "N:";
$result = "NaN";

if ($type == "loggedin") {

	/* Find out the previous user timestamp
	* so we can determine the difference between the current
	* and previous user count. If the file is empty return a 0.
	*/
	$fd = @fopen($tmpfile, "r");
	if ($fd) {
		while (!feof($fd)) {
			$line = trim(fgets($fd));
			if ($line) {
				$previous_user_timestamp = $line;
			} else {
				$previous_user_timestamp = 0;
			}
		}
	} else {
		$previous_user_timestamp = 0;
	}
	@fclose($fd);

	foreach ($cpdb as $user) {
		$user_ip = $user[2];
		// Record the timestamp
		$timestamp = $user[0];
		if ($timestamp > $previous_user_timestamp) {
			$current_user_count = $current_user_count + 1;
		}
	}

	// Write out the latest timestamp but not if it is empty
	if (!empty($timestamp)) {
		$fd = @fopen($tmpfile, "w");
		if ($fd) {
			fwrite($fd, $timestamp);
		}
		@fclose($fd);
	}

	/* If $timestamp is less than or equal to previous_user_timestamp return 0,
	 * as we only want the 'X' number of users logged in since last RRD poll.
	 */
	if ($timestamp <= $previous_user_timestamp) {
		$result = 0;
	} else {
		$result = $current_user_count;
	}
} elseif ($type == "concurrent") {
	$result = $no_users;
}

echo "$result";

?>
