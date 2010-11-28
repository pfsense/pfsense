<?php
/*  
	external config loader
	Copyright (C) 2010 Scott Ullrich
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

require("globals.inc");
require("functions.inc");

function get_boot_disk() {
	global $g;
	$disk = `/sbin/mount | /usr/bin/grep "on / " | /usr/bin/cut -d'/' -f3 | /usr/bin/cut -d' ' -f1`;
	return $disk;
}

function get_disk_slices($disk) {
	global $g;
	$slices_array = array();
	$slices = `/bin/ls /dev/{$disk}s*`;
	if($slices == "ls: No match.") 
		return;
	$slices_array = split(" ", $slices);
	return $slices_array;
}

function get_disks() {
	global $g;
	$disks_array = array();
	$disks = `/sbin/sysctl kern.disks | cut -d':' -f2`;
	$disks_s = explode(" ", $disks);
	foreach($disks_s as $disk) 
		if(trim($disk))
			$disks_array[] = $disk;
	return $disks_array;
}

function discover_config($mountpoint) {
	global $g;
	$locations_to_check = array("/", "/config");
	foreach($locations_to_check as $ltc) {
		$tocheck = "{$mountpoint}{$ltc}/config.xml";
		if(file_exists($tocheck)) 
			return $tocheck;
	}
	return "";
}

function test_config($file_location) {
	global $g;
	// config.xml was found.  ensure it is sound.
	$root_obj = trim($g['xml_rootobj']);
	$xml_file_head = trim(`/bin/cat {$file_location} | /usr/bin/head -n1`);
	if($xml_file_head == $root_obj) {
		// Now parse config to make sure
		$config_status = config_validate($file_location);
		if($config_status) 	
			return true;
	}
	return false;
}

// Probes all disks looking for config.xml
function find_config_xml() {
	global $g;
	$disks = get_disks();
	$boot_disk = get_boot_disk();
	exec("mkdir -p /tmp/mnt/cf");
	foreach($disks as $disk) {
		$slices = get_disk_slices($disk);
		if(is_array($slices)) {
			foreach($slices as $slice) {
				echo " $slice";
				// First try msdos fs
				$result = exec("/sbin/mount -t msdos /dev/{$slice} /tmp/mnt/cf 2>/dev/null");
				// Next try regular fs (ufs)
				if(!$result) 
					$result = exec("/sbin/mount /dev/{$slice} /tmp/mnt/cf 2>/dev/null");
				if($result == "0") {
					// Item was mounted - look for config.xml file
					$config_location = discover_config($slice);
					if($config_location) {
						if(test_config($config_location)) {
							// We have a valid configuration.  Install it.
							echo " -> found ";
							backup_config();
							restore_backup($config_location);
							exec("/sbin/unount /tmp/mnt/cf");
							break;
						}
					}
				}
			}
		}
	}
}

echo "External config loader 1.0 is now starting...";
find_config_xml();
echo "\n";

?>