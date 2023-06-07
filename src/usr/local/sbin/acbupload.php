<?php
/*
 * autoconfigbackup.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
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
  * This file is called by CRON every few minutes to upload ACB backups to the server.
  * This allows the GUI to remain responsive. Backup files are deleted after upload.
  */
 
require_once("filter.inc");
require_once("notices.inc");

$lockfile = "/tmp/acb.lock";
// Check for 
if (file_exists($lockfile)) {
    if (time()-filemtime($lockfile) > (60 * 60)) {
        // The lock file is more than an hour old. Something probably went wrong
        unlink_if_exists($lockfile);
        log_error("Stale ACB lock file removed");
    } else {
       exit();
    }
}

touch($lockfile);

// Location of backup file pairs
$acbuploadpath = g_get('acbbackuppath');
// systems we do not allow
$badreasons = array("snort", "pfblocker", "minicron", "merged in config");
// List any ACB file pairs that have been created
$files = glob($acbuploadpath . '*.form');

if (count($files) > 0) {
    // Sort them, oldest first
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });

    // Upload them to the server
    foreach ($files as $file) {
        $basename = basename($file, ".form");
        upload($basename);
    }
}

unlink_if_exists($lockfile);

function upload($basename) {
    global $acbuploadpath, $badreasons;

    $upload_url = "https://acb.netgate.com/save";

    if (!is_url_hostname_resolvable($upload_url)) {
	$data = " Unable to resolve " . parse_url($upload_url, PHP_URL_HOST) . " ";
	acb_error_log($upload_url, $data);
	unlink_if_exists($acbuploadpath . $basename . ".data");
	unlink_if_exists($acbuploadpath . $basename . ".form");
	return;
    }

    // Retrieve the data to send
    // Retrieve the form data
    $formdata = file_get_contents($acbuploadpath . $basename . ".form");
    $post_fields = json_decode($formdata, true);
    // Add the backup data file
    $post_fields['file'] = curl_file_create($acbuploadpath . $basename . ".data", 'image/jpg', 'config.jpg');

    // Ensure there are no backups from systems we do not allow
    foreach ($badreasons as $term) {
        if (strpos(strtolower($post_fields['reason']), $term) !== false) {
            log_error("Skipping ACB backup for " . strtolower($post_fields['reason']) . '.');
            unlink_if_exists($acbuploadpath . $basename . ".data");
            unlink_if_exists($acbuploadpath . $basename . ".form");
            return;
        }
    }

    // Check configuration into the ESF repo (Copied from /etc/inc/acb.inc)
    $curl_session = curl_init();

    curl_setopt($curl_session, CURLOPT_URL, $upload_url);
    curl_setopt($curl_session, CURLOPT_POST, count($post_fields));
    curl_setopt($curl_session, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($curl_session, CURLOPT_CONNECTTIMEOUT, 55);
    curl_setopt($curl_session, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl_session, CURLOPT_USERAGENT, g_get('product_label') . '/' . rtrim(file_get_contents("/etc/version")));
    // Proxy
    set_curlproxy($curl_session);

    $data = curl_exec($curl_session);
    $httpcode = curl_getinfo($curl_session, CURLINFO_RESPONSE_CODE);

    if (curl_errno($curl_session)) {
        $fd = fopen("/tmp/backupdebug.txt", "w");
        $acb_curl_error = curl_error($curl_session);
        fwrite($fd, $upload_url . "" . $fields_string . "\n\n");
        fwrite($fd, $data);
        fwrite($fd, $acb_curl_error);
        fclose($fd);
    } else {
        curl_close($curl_session);
    }

    // Delete the backup files, whether it worked or not
    unlink_if_exists($acbuploadpath . $basename . ".data");
    unlink_if_exists($acbuploadpath . $basename . ".form");

    if (strpos(strval($httpcode), '20') === false) {
	if (empty($data) && $acb_curl_error) {
		$data = $acb_curl_error;
	} else {
		$data = "Unknown error";
	}
	acb_error_log($upload_url, $data);
    } else {
        // Update last pfS backup time
        $fd = fopen("/cf/conf/lastpfSbackup.txt", "w");
        fwrite($fd, $config['revision']['time']);
        fclose($fd);
        $notice_text = "End of configuration backup to " . $upload_url . " (success).";
        log_error($notice_text);
        update_filter_reload_status($notice_text);
    }
}

function acb_error_log($upload_url, $data) {
        $notice_text = sprintf(gettext(
            "An error occurred while uploading the encrypted %s configuration to "), g_get('product_label')) .
            $upload_url . " (" . htmlspecialchars($data) . ")";
        log_error($notice_text . " - " . $data);
        file_notice("AutoConfigBackup", $notice_text);
        update_filter_reload_status($notice_text);
}
