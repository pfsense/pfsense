<?php
/*
 * notices.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2005 Colin Smith (ethethlay@gmail.com)
 * Copyright (c) 2005-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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

require_once("globals.inc");
require_once("functions.inc");
require_once("led.inc");

global $notice_root, $notice_path, $notice_queue, $notice_last;
$notice_root  = g_get('tmp_path') . '/notices';
$notice_path  = $notice_root . '/notices.serial';
$notice_queue = $notice_root . '/smtp.queue';
$notice_last  = $notice_root . '/smtp.last';

global $smtp_authentication_mechanisms;
$smtp_authentication_mechanisms = array(
	'PLAIN' => 'PLAIN',
	'LOGIN' => 'LOGIN');
/* Other SMTP Authentication Mechanisms that could be supported.
 * Note that MD5 is no longer considered secure.
 *	'GSSAPI' => 'GSSAPI ' . gettext("Generic Security Services Application Program Interface")
 *	'DIGEST-MD5' => 'DIGEST-MD5 ' . gettext("Digest access authentication")
 *	'MD5' => 'MD5'
 *	'CRAM-MD5' => 'CRAM-MD5'
*/
global $pushover_sounds;
$pushover_sounds = array(
	'devicedefault' => 'Device Default',
	'pushover' => 'Pushover',
	'bike' => 'Bike',
	'bugle' => 'Bugle',
	'cashregister' => 'Cash Register',
	'classical' => 'Classical',
	'cosmic' => 'Cosmic',
	'falling' => 'Falling',
	'gamelan' => 'Gamelan',
	'incoming' => 'Incoming',
	'intermission' => 'Intermission',
	'magic' => 'Magic',
	'mechanical' => 'Mechanical',
	'pianobar' => 'Piano Bar',
	'siren' => 'Siren',
	'spacealarm' => 'Space Alarm',
	'tugboat' => 'Tug Boat',
	'alien' => 'Alien (long)',
	'climb' => 'Climb (long)',
	'persistent' => 'Persistent (long)',
	'echo' => 'Echo (long)',
	'updown' => 'Up Down (long)',
	'vibrate' => 'Vibrate only',
	'none' => 'None (silent)');

/****f* notices/notices_setup
 * NAME
 *   notices_setup
 * INPUTS
 *   None
 * RESULT
 *   Sets up notice queues in such a way that any local daemon/user can
 *   submit notification messages.
 ******/
function notices_setup() {
	global $notice_root, $notice_path, $notice_queue, $notice_last;

	/* Setup notice queue directory */
	if (!is_dir($notice_root) || is_link($notice_root)) {
		@unlink_if_exists($notice_root);
		safe_mkdir($notice_root, 0755);
	}

	/* Create notice queue files */
	foreach ([$notice_path, $notice_queue, $notice_last] as $nf) {
		if (!is_file($nf) || is_link($nf)) {
			@unlink_if_exists($nf);
			touch($nf);
			chmod($nf, 0777);
		}
	}
}

/****f* notices/file_notice
 * NAME
 *   file_notice
 * INPUTS
 *	 $id, $notice, $category, $url, $priority, $local_only
 * RESULT
 *   Files a notice and kicks off the various alerts, smtp, telegram, pushover, system log, LED's, etc.
 *   If $local_only is true then the notice is not sent to external places (smtp, telegram, pushover)
 ******/
function file_notice($id, $notice, $category = "General", $url = "", $priority = 1, $local_only = false) {
	/*
	 * $category - Category that this notice should be displayed under. This can be arbitrary,
	 * 	       but a page must be set to receive this messages for it to be displayed.
	 *
	 * $priority - A notice's priority. Higher numbers indicate greater severity.
	 *	       0 = informational, 1 = warning, 2 = error, etc. This may also be arbitrary,
	 */
	global $notice_path;
	notices_setup();
	if (!$queue = get_notices()) {
		$queue = [];
	}
	$queuekey = time();
	$toqueue = array(
				'id'		=> htmlentities($id),
				'notice'	=> htmlentities($notice),
				'url'		=> htmlentities($url),
				'category'	=> htmlentities($category),
				'priority'	=> htmlentities($priority),
			);
	while (isset($queue[$queuekey])) {
		$queuekey++;
	}
	$queue[$queuekey] = $toqueue;
	$queueout = fopen($notice_path, "w");
	if (!$queueout) {
		log_error(sprintf(gettext("Could not open %s for writing"), $notice_path));
		return;
	}
	fwrite($queueout, serialize($queue));
	fclose($queueout);
	log_error(sprintf(gettext("New alert found: %s"), $notice));
	/* soekris */
	if (file_exists("/dev/led/error")) {
		exec("/bin/echo 1 > /dev/led/error");
	}
	/* wrap & alix */
	led_normalize();
	led_morse(1, 'sos');
	if (!$local_only) {
		notify_all_remote($notice);
	}
	return $queuekey;
}

/****f* notices/get_notices
 * NAME
 *   get_notices
 * INPUTS
 *	 $category
 * RESULT
 *   Returns a specific notices text
 ******/
function get_notices($category = "all") {
	global $notice_path;
	notices_setup();

	if (file_exists($notice_path)) {
		$queue = unserialize(file_get_contents($notice_path));
		if (!$queue) {
			return false;
		}
		if ($category != 'all') {
			foreach ($queue as $time => $notice) {
				if (strtolower($notice['category']) == strtolower($category)) {
					$toreturn[$time] = $notice;
				}
			}
			return $toreturn;
		} else {
			return $queue;
		}
	} else {
		return false;
	}
}

/****f* notices/close_notice
 * NAME
 *   close_notice
 * INPUTS
 *	 $id
 * RESULT
 *   Removes a notice from the list
 ******/
function close_notice($id) {
	global $notice_path;
	notices_setup();
	require_once("util.inc");
	/* soekris */
	if (file_exists("/dev/led/error")) {
		exec("/bin/echo 0 > /dev/led/error");
	}
	/* wrap & alix */
	led_normalize();
	$ids = [];
	if (!$notices = get_notices()) {
		return;
	}
	if ($id == "all") {
		@file_put_contents($notice_path, '');
		notices_setup();
		return;
	}
	foreach (array_keys($notices) as $time) {
		if ($id == $time) {
			unset($notices[$id]);
			break;
		}
	}
	foreach ($notices as $key => $notice) {
		$ids[$key] = $notice['id'];
	}
	foreach ($ids as $time => $tocheck) {
		if ($id == $tocheck) {
			unset($notices[$time]);
			break;
		}
	}
	if (count($notices) != 0) {
		$queueout = fopen($notice_path, "w");
		fwrite($queueout, serialize($notices));
		fclose($queueout);
	} else {
		@file_put_contents($notice_path, '');
		notices_setup();
	}

	return;
}

/****f* notices/are_notices_pending
 * NAME
 *   are_notices_pending
 * INPUTS
 *	 $category to check
 * RESULT
 *   returns true if notices are pending, false if they are not
 ******/
function are_notices_pending($category = "all") {
	global $notice_path;
	notices_setup();
	return file_exists($notice_path) && (filesize($notice_path) > 0);
}

function notices_sendqueue() {
	global $notice_queue;
	notices_setup();
	$nothing_done_count = 0;
	$messagequeue = [];

	while(true) {
		$nothing_done_count++;
		$smtpcount = 0;
		$messages = [];
		if (is_file($notice_queue) &&
		    is_writable($notice_queue)) {
			$notifyqueue_lck = lock("notifyqueue", LOCK_EX);
			$messages = unserialize(file_get_contents($notice_queue));
			if ($messages &&
			    is_array($messages) &&
			    !empty($messages)) {
				$messagequeue = $messages;
				/* Empty message queue */
				$messages = ['mails' => ['item' => []]];
				$ret = @file_put_contents($notice_queue, serialize($messages));
				if ($ret === false) {
					log_error("ERROR: Failed to write notify message queue!");
					return;
				}
			} else {
				/* No messages in queue, nothing to do */
				return;
			}
			unset($messages);
		} else {
			/* Queue does not exist or is not writable, so no action can be taken
			 * https://redmine.pfsense.org/issues/14031
			 */
			log_error("SMTP queue does not exist or is not writable.");
			return;
		}
		// clear lock before trying to send messages, so new one's can be added
		unlock($notifyqueue_lck);

		$smtpmessage = "";
		foreach(array_get_path($messagequeue, 'mails/item', []) as $mail) {
			if (!is_array($mail) || empty($mail)) {
				continue;
			}
			switch ($mail['type']) {
				case 'mail':
					$smtpcount++;
					$smtpmessage .= "\r\n" . date("H:i:s", $mail['time']) . " " . $mail['msg'];
					break;
				default:
					break;
			}
		}
		if (!empty($smtpmessage)) {
			$smtpmessageheader = sprintf(gettext("Notifications in this message: %s"), $smtpcount);
			$smtpmessageheader .= "\r\n" . str_repeat('=', strlen($smtpmessageheader)) . "\r\n";
			$nothing_done_count = 0;
			notify_via_smtp($smtpmessage, true, $smtpmessageheader);
		}

		/* First batch may already be sent, sleep a bit before checking
		 * again to send additional larger batches. */
		if ($nothing_done_count > 3) {
			break;
		} else {
			sleep(30);
		}
	}
}

function notify_via_queue_add($message, $type='mail') {
	global $notice_queue;
	notices_setup();
	$mail = [];
	$mail['time'] = time();
	$mail['type'] = $type;
	$mail['msg'] = $message;
	$notifyqueue_lck = lock("notifyqueue", LOCK_EX);
	$messages = [];
	if (is_file($notice_queue)) {
		if (!is_writable($notice_queue)) {
			/* Cannot write to notify queue, so exit early
			 * https://redmine.pfsense.org/issues/14031
			 */
			log_error("Cannot write to the notify queue.");
			return;
		}
		$queue = unserialize(file_get_contents($notice_queue));
		if ($queue) {
			$messages = $queue;
		}
	}
	if (is_array($messages)) {
		$msg = array_get_path($messages, 'mails/item', []);
		$msg[] = $mail;
		array_set_path($messages, 'mails/item', $msg);
		$ret = @file_put_contents($notice_queue, serialize($messages));
		if ($ret === false) {
			log_error("ERROR: Failed to write notify message queue!");
			return;
		}
	}
	unset($messages);

	mwexec_bg('/usr/local/bin/notify_monitor.php');
	unlock($notifyqueue_lck);
}

/****f* notices/notify_via_smtp
 * NAME
 *   notify_via_smtp
 * INPUTS
 *	 notification string to send as an email
 * RESULT
 *   returns true if message was sent
 ******/
function notify_via_smtp($message, $force = false, $header = "") {
	global $notice_last;
	if (is_platform_booting()) {
		return;
	}

	if ((config_path_enabled('notifications/smtp', 'disable') && !$force) ||
	    empty(config_get_path('notifications/smtp/ipaddress')) ||
	    empty(config_get_path('notifications/smtp/notifyemailaddress'))) {
		return;
	}

	notices_setup();

	/* Try not to send the same message twice, except if $force is true */
	$message = trim($message);

	$repeat = false;
	if (!$force &&
	    file_exists($notice_last) &&
	    (filesize($notice_last) > 0)) {
		$lastmsg = trim(file_get_contents($notice_last));

		/* Trim leading time from previous stored message */
		$msgmatches = [];
		if (preg_match('/^(([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]) (.*)/', $lastmsg, $msgmatches)) {
			$lastmsg = $msgmatches[3];
		}

		/* Trim leading time from current message (if present) */
		$msgmatches = [];
		if (preg_match('/^(([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]) (.*)/', $message, $msgmatches)) {
			/* Compare text without leading time, but don't change original copy */
			if ($lastmsg == $msgmatches[3]) {
				$repeat = true;
			}
		} elseif ($lastmsg == $message) {
			$repeat = true;
		}
	}

	/* Log that we have suppressed a repeat message
	 * TODO: Maybe track repeats and send every X times.
	 */
	if ($repeat) {
		log_error(gettext("Suppressing repeat e-mail notification message."));
		return;
	}

	/* Store last message sent to avoid spamming */
	@file_put_contents($notice_last, $message);
	if (!$force) {
		notify_via_queue_add($header . $message, 'mail');
		$ret = true;
	} else {
		$ret = send_smtp_message($header . "\r\n" . $message . "\r\n",
					config_get_path('system/hostname') . '.' .
					config_get_path('system/domain') .
					" - Notification",
					$force);
	}

	return $ret;
}

function send_smtp_message($message, $subject = "(no subject)", $force = false) {
	require_once("Mail.php");

	/* Bail if disabled (and not forced) or if config is incomplete */
	if ((config_path_enabled('notifications/smtp', 'disable') && !$force) ||
	    empty(config_get_path('notifications/smtp/ipaddress')) ||
	    empty(config_get_path('notifications/smtp/notifyemailaddress'))) {
		return;
	}

	if (empty(config_get_path('notifications/smtp/username')) ||
	    empty(config_get_path('notifications/smtp/password'))) {
		$auth = false;
		$username = '';
		$password = '';
	} else {
		$auth = config_get_path('notifications/smtp/authentication_mechanism', 'PLAIN');
		$username = config_get_path('notifications/smtp/username');
		$password = config_get_path('notifications/smtp/password');
	}

	$params = array(
		'host' => (config_path_enabled('notifications/smtp', 'ssl')
		    ? 'ssl://'
		    : '')
		    . config_get_path('notifications/smtp/ipaddress'),
		'port' => config_get_path('notifications/smtp/port', 25),
		'auth' => $auth,
		'username' => $username,
		'password' => $password,
		'localhost' => config_get_path('system/hostname') . '.' . config_get_path('system/domain'),
		'timeout' => config_get_path('notifications/smtp/timeout', 20),
		'debug' => false,
		'persist' => false
	);

	if (config_get_path('notifications/smtp/sslvalidate') == "disabled") {
		$params['socket_options'] = array(
			'ssl' => array(
				'verify_peer_name' => false,
				'verify_peer' => false
		));
	}

	if (!empty(config_get_path('notifications/smtp/fromaddress'))) {
		$from = config_get_path('notifications/smtp/fromaddress');
	} else {
		$from = "pfsense@" . config_get_path('system/hostname') . '.' . config_get_path('system/domain');
	}

	$to = config_get_path('notifications/smtp/notifyemailaddress');

	$headers = array(
		"From"    => $from,
		"To"      => $to,
		"Subject" => $subject,
		"Date"    => date("r")
	);

	$error_text = 'Could not send the message to %1$s -- Error: %2$s';
	try {
		$smtp =& Mail::factory('smtp', $params);
		$mail = @$smtp->send($to, $headers, $message);

		if (PEAR::isError($mail)) {
			$err_msg = sprintf(gettext($error_text),
			    $to, $mail->getMessage());
		}
	} catch (Exception $e) {
		$err_msg = sprintf(gettext($error_text), $to, $e->getMessage());
	}

	if (!empty($err_msg)) {
		log_error($err_msg);
		return($err_msg);
	}

	log_error(sprintf(gettext("Message sent to %s OK"), $to));
	return;
}
/****f* notices/notify_via_telegram
 * NAME
 *   notify_via_telegram
 * INPUTS
 *	 notification string to send to Telegram via API
 * RESULT
 *   returns NULL if message was sent
 ******/

function notify_via_telegram($message, $force = false) {
	if ((!config_path_enabled('notifications/telegram', 'enabled') && (!$force)) ||
	    empty(config_get_path('notifications/telegram/api')) ||
	    empty(config_get_path('notifications/telegram/chatid'))) {
		if ($force) {
			return gettext("Unable to test Telegram notification without both API Key & Chat ID set");
		}
		return;
	}

	$url = "https://api.telegram.org/bot" . config_get_path('notifications/telegram/api') . "/sendMessage?";
	$data = array(
		"chat_id" => config_get_path('notifications/telegram/chatid'),
		"text" => config_get_path('system/hostname') . '.' . config_get_path('system/domain') . "\n{$message}"
	);
	$result = json_decode(curl_post_notification($url . http_build_query($data)), true);
	if (is_array($result)) {
		if ($result['ok']) {
			unset($err_msg);
		} else {
			$err_msg = sprintf(gettext("Failed to send Telegram notification. Error received was :{$result['error_code']}: {$result['description']}"));
			log_error($err_msg);
		}
	} else {
		$err_msg = gettext("API to Telegram did not return data in expected format!");
		log_error($err_msg);
	}
	return $err_msg;
}

/****f* notices/notify_via_pushover
 * NAME
 *   notify_via_pushover
 * INPUTS
 *	 notification string to send to Pushover via API
 * RESULT
 *   returns NULL if message was sent
 ******/

function notify_via_pushover($message, $force = false) {
	if ((!config_path_enabled('notifications/pushover', 'enabled') && (!$force)) ||
	    empty(config_get_path('notifications/pushover/apikey')) ||
	    empty(config_get_path('notifications/pushover/userkey'))) {
		if ($force) {
			return gettext("Unable to test Pushover notification without both API Key & User Key set");
		}
		return;
	}

	if (strcasecmp(config_get_path('notifications/pushover/sound'), 'devicedefault') == 0) {
		config_del_path('notifications/pushover/sound');
	}

	$url = "https://api.pushover.net/1/messages.json";
	$data = array(
		"token"    => config_get_path('notifications/pushover/apikey'),
		"user"     => config_get_path('notifications/pushover/userkey'),
		"sound"    => config_get_path('notifications/pushover/sound'),
		"priority" => config_get_path('notifications/pushover/priority'),
		"retry"    => config_get_path('notifications/pushover/retry'),
		"expire"   => config_get_path('notifications/pushover/expire'),
		"message"  => config_get_path('system/hostname') . '.' . config_get_path('system/domain') . "\n{$message}"
	);
	$result = json_decode(curl_post_notification($url, $data), true);
	if (is_array($result)) {
		if ($result['status']) {
			unset($err_msg);
		} else {
			$err_msg = sprintf(gettext("Failed to send Pushover notification. Error received was: %s"), $result['errors']['0']);
			log_error($err_msg);
		}
	} else {
		$err_msg = gettext("Pushover API server did not return data in expected format!");
		log_error($err_msg);
	}
	return $err_msg;
}

/****f* notices/notify_via_slack
 * NAME
 *   notify_via_slack
 * INPUTS
 *	 notification string to send to Slack via API
 * RESULT
 *   returns NULL if message was sent
 ******/

function notify_via_slack($message, $force = false) {
	if ((!config_path_enabled('notifications/slack', 'enabled') && (!$force)) ||
	    empty(config_get_path('notifications/slack/api')) ||
	    empty(config_get_path('notifications/slack/channel'))) {
		if ($force) {
			return gettext("Unable to test Slack notification without both API Key & Channel set");
		}
		return;
	}

	$url = "https://slack.com/api/chat.postMessage";
	$data = array(
		"token"    => config_get_path('notifications/slack/api'),
		"channel"  => "#" . config_get_path('notifications/slack/channel'),
		"text"     => $message,
		"username" => config_get_path('system/hostname') . '.' . config_get_path('system/domain')
	);
	$result = json_decode(curl_post_notification($url, $data), true);
	if (is_array($result)) {
		if ($result['ok']) {
			unset($err_msg);
		} else {
			$err_msg = sprintf(gettext("Failed to send Slack notification. Error received was: %s"), $result['error']);
			log_error($err_msg);
		}
	} else {
		$err_msg = gettext("Slack API server did not return data in expected format!");
		log_error($err_msg);
	}
	return $err_msg;
}

function curl_post_notification($url, $data = []) {
	$conn = curl_init($url);
	if (!empty($data)) {
		curl_setopt($conn, CURLOPT_POSTFIELDS, $data);
	}
	curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($conn, CURLOPT_FRESH_CONNECT,  true);
	curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
	set_curlproxy($conn);
	$curl_post_result = curl_exec($conn);
	curl_close($conn);
	return $curl_post_result; //json encoded
}

/* Notify via remote methods only - not via GUI. */
function notify_all_remote($msg) {
	notify_via_smtp($msg);
	notify_via_telegram($msg);
	notify_via_pushover($msg);
	notify_via_slack($msg);
}
