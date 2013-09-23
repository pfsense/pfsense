#!/usr/local/bin/php -q
<?php
/* $Id$ */
/*
        captiveportal_gather_stats.php
        Copyright (C) 2011 Warren Baker
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

if(empty($type))
	exit;

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
			if($line)
				$previous_user_timestamp = $line;
			else
				$previous_user_timestamp = 0;
		}
	} else {
		$previous_user_timestamp = 0;
	}
	@fclose($fd);

	foreach($cpdb as $user) {
		$user_ip = $user[2];
		// Record the timestamp
		$timestamp = $user[0];
		if ($timestamp > $previous_user_timestamp)
			$current_user_count = $current_user_count + 1;
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
	if($timestamp <= $previous_user_timestamp)
		$result = 0;
	else {
		$result = $current_user_count;
	}
} elseif ($type == "concurrent")
	$result = $no_users;

echo "$result";

?>
