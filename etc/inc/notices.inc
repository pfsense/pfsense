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
require_once("led.inc");

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
	if(!$queueout) {
		log_error("Could not open {$notice_path} for writing");
		return;
	}
	fwrite($queueout, serialize($queue));
	fclose($queueout);
	log_error("New alert found: {$notice}");
	/* soekris */
	if(file_exists("/dev/led/error"))
		exec("/bin/echo 1 > /dev/led/error");
	/* wrap & alix */
	led_normalize();
	led_morse(1, 'sos');
	notify_via_growl($notice);
	notify_via_smtp($notice);
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
	/* soekris */
	if(file_exists("/dev/led/error"))
		exec("/bin/echo 0 > /dev/led/error");
	/* wrap & alix */
	led_normalize();
	$ids = array();
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
	if(count($notices) != 0) {
		$queueout = fopen($notice_path, "w");
        	fwrite($queueout, serialize($notices));
        	fclose($queueout);
	} else {
		unlink_if_exists($notice_path);
	}

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


function are_notices_pending($category = "all") {
	global $notice_path;
	if(file_exists($notice_path)) {
		return true;
	}
	return false;
}

/****f* pfsense-utils/notify_via_smtp
 * NAME
 *   notify_via_smtp
 * INPUTS
 *	 notification string to send as an email
 * RESULT
 *   returns true if message was sent
 ******/
function notify_via_smtp($message) {
	global $config;

	if(!$config['notifications']['smtp']['ipaddress'])
		return;

	if(!$config['notifications']['smtp']['notifyemailaddress']) 
		return;

	require_once("smtp.inc");

	$smtp = new smtp_class;

	$from = "pfsense@{$config['system']['hostname']}.{$config['system']['domain']}";
	$to = $config['notifications']['smtp']['notifyemailaddress'];

	$smtp->host_name = $config['notifications']['smtp']['ipaddress'];
	$smtp->host_port = 25;

	$smtp->direct_delivery = 1;
	$smtp->ssl = 0;
	$smtp->debug = 1;
	$smtp->html_debug = 0;

	$headers = array(
		"From: {$from}",
		"To: {$to}",
		"Subject: {$config['system']['hostname']}.{$config['system']['domain']} - Notification",
		"Date: ".strftime("%a, %d %b %Y %H:%M:%S %Z")
	);

	if($smtp->SendMessage($from, array($to), $headers, $message))
		echo "Message sent to {$to} OK.\n";
	else
		echo "Cound not send the message to {$to}.\nError: {$smtp->error}\n";

}


/****f* pfsense-utils/notify_via_growl
 * NAME
 *   notify_via_growl
 * INPUTS
 *	 notification string to send
 * RESULT
 *   returns true if message was sent
 ******/
function notify_via_growl($message) {
	require_once("growl.class");
	global $config;
	$growl_ip = $config['notifications']['growl']['ipaddress'];
	$growl_password = $config['notifications']['growl']['password'];
	if($growl_ip) {
		$growl = new Growl($growl_ip, $growl_password);
		$growl->notify("pfSense growl alert", "pfSense", "{$message}");
	}
}

/****f* pfsense-utils/register_via_growl
 * NAME
 *   register_via_growl
 * INPUTS
 *	 none
 * RESULT
 *   none
 ******/
function register_via_growl() {
	require_once("growl.class");
	global $config;
	$growl_ip = $config['notifications']['growl']['ipaddress'];
	$growl_password = $config['notifications']['growl']['password'];
	if($growl_ip) {
		$growl = new Growl($growl_ip, $growl_password);
		$growl->register();
	}
}

?>