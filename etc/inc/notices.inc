<?php
/****h* pfSense/notices
 * NAME
 *   notices.inc - pfSense notice utilities
 * DESCRIPTION
 *   This include contains the pfSense notice facilities.
 * HISTORY
 *   $Id$
 ******
 *
 * Copyright (C) 2005 Colin Smith (ethethlay@gmail.com)
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * RISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 */

require_once("globals.inc");

$notice_path = $g['tmp_path'] . '/notices';

/*
 * $category - Category that this notice should be displayed under. This can be arbitrary,
 * 	       but a page must be set to receive this messages for it to be displayed.
 *
 * $priority - A notice's priority. Higher numbers indicate greater severity.
 *	       0 = informational, 1 = warning, 2 = error, etc. This may also be arbitrary,
 */
function file_notice($id, $notice, $category = "General", $url = "", $priority = 1) {
	global $notice_path;
	if(!$queue = get_notices()) $queue = array();
	$queuekey = time();
	$toqueue = array(
				'id'		=> $id,
				'notice'	=> $notice,
				'url'		=> $url,
				'category'	=> $category,
				'priority'	=> $priority,
			);
	$queue[$queuekey] = $toqueue;
	$queueout = fopen($notice_path, "w");
	fwrite($queueout, serialize($queue));
	fclose($queueout);
	return $queuekey;
}

function get_notices($category = "all") {
	if(file_exists('/tmp/notices')) {
		$queue = unserialize(file_get_contents('/tmp/notices'));
		if(!$queue) return false;
		if($category != 'all') {
			foreach($queue as $time => $notice) {
				if(strtolower($notice['category']) == strtolower($category))
					$toreturn[$time] = $notice;
			}
			return $toreturn;
		} else {
			return $queue;
		}
	} else {
		return false;
	}
}

function close_notice($id) {
	global $notice_path;
	require_once("util.inc");
	if(!$notices = get_notices()) return;
	if($id == "all") {
		unlink_if_exists($notice_path);
		return;
	}
	foreach(array_keys($notices) as $time) {
		if($id == $time) {
			unset($notices[$id]);
			break;
		}
	}
	foreach($notices as $key => $notice) {
		$ids[$key] = $notice['id'];
	}
	foreach($ids as $time => $tocheck) {
		if($id == $tocheck) {
			unset($notices[$time]);
			break;
		}
	}
	$queueout = fopen($notice_path, "w");
	fwrite($queueout, serialize($queue));
	fclose($queueout);
	return;
}

function dump_xml_notices() {
	require_once("xmlparse.inc");
	global $notice_path, $listtags;
	$listtags[] = 'notice';
	if(!$notices = get_notices()) return;
	foreach($notices as $time => $notice) {
		$notice['time'] = $time;
		$toput['notice'][] = $notice;
	}
	$xml = dump_xml_config($toput, 'notices');
	return $xml;
}

function print_notices($notices, $category = "all") {
	foreach($notices as $notice) {
		if($category != "all") {
			if(in_array($notice['category'], $category)) $categories[] = $notice['category'];
		} else {
			$categories[] = $notice['category'];
		}
	}
	$categories = array_unique($categories);
	sort($categories);
	foreach($categories as $category) {
		$toreturn .= "<ul><li>{$category}<ul>";
		foreach($notices as $notice) {
			if(strtolower($notice['category']) == strtolower($category)) {
				if($notice['id'] != "") {
					if($notice['url'] != "") {
						$toreturn .= "<li><a href={$notice['url']}>{$notice['id']}</a> - {$notice['notice']}</li>";
					} else {
						$toreturn .= "<li>{$notice['id']} - {$notice['notice']}</li>";
					}
				}
			}
		}
		$toreturn .= "</ul></li></ul>";
	}
	return $toreturn;
}

function print_notice_box($category = "all") {
	$notices = get_notices();
	if(!$notices) return;
	print_info_box_np(print_notices($notices, $category));
	return;
}
?>
