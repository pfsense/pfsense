#!/usr/local/bin/php-cgi -q
<?php
/*
 * show_filter_reload_status.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

$last_text = "";

while (!stristr($status, "Done")) {
	$status = get_status();
	if ($status <> "") {
		echo $status . "\n";
	}
	sleep(1);
}

function get_status() {
	global $last_text;
	$status = file_get_contents("/var/run/filter_reload_status");
	$status = str_replace("...", "", $status);
	$status .= "...";
	if ($status <> $last_text) {
		$last_text = $status;
		return $status;
	}
	return "";
}

?>
