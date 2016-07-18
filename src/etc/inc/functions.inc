<?php
/*
 * functions.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

/* BEGIN compatibility goo with HEAD */
if (!function_exists("gettext")) {
	function gettext($text) {
		return $text;
	}
}

if (!function_exists("pfSenseHeader")) {
	/****f* pfsense-utils/pfSenseHeader
	 * NAME
	 *   pfSenseHeader
	 * INPUTS
	 *   none
	 * RESULT
	 *   Javascript header change or browser Location:
	 ******/
	function pfSenseHeader($text) {
		global $_SERVER;
		if (isAjax()) {
			if ($_SERVER['HTTPS'] == "on") {
				$protocol = "https";
			} else {
				$protocol = "http";
			}

			$port = ":{$_SERVER['SERVER_PORT']}";
			if ($_SERVER['SERVER_PORT'] == "80" && $protocol == "http") {
				$port = "";
			}
			if ($_SERVER['SERVER_PORT'] == "443" && $protocol == "https") {
				$port = "";
			}
			$complete_url = "{$protocol}://{$_SERVER['HTTP_HOST']}{$port}/{$text}";
			echo "\ndocument.location.href = '{$complete_url}';\n";
		} else {
			header("Location: $text");
		}
	}
}
/* END compatibility goo with HEAD */

/*fetch menu notices function*/
if (!function_exists("get_menu_messages")) {
	function get_menu_messages() {
		global $g, $config;
		if (are_notices_pending()) {
			$notices = get_notices();
			$requests = array();

			## Get Query Arguments from URL ###
			foreach ($_REQUEST as $key => $value) {
				if ($key != "PHPSESSID") {
					$requests[] = $key . '=' . $value;
				}
			}
			if (is_array($requests)) {
				$request_string = implode("&", $requests);
			}

			if (is_array($notices)) {
				$notice_msgs = "<table colspan=\'6\' id=\'notice_table\'>";
				$alert_style = "style=\'color:#ffffff; filter:Glow(color=#ff0000, strength=12);\' ";
				$notice = "<a href=\'#\' onclick=notice_action(\'acknowledge\',\'all\');domTT_close(this); {$alert_style}>" . gettext("Acknowledge All Notices") . "</a>";
				$alert_link = "title=\'" . gettext("Click to Acknowledge") . "\' {$alert_style}";
				$domtt_width = 500;
				foreach ($notices as $key => $value) {
					$date = date("m-d-y H:i:s", $key);
					$noticemsg = ($value['notice'] != "" ? $value['notice'] : $value['id']);
					$noticemsg = strip_tags(preg_replace("/(\"|\'|\n|<.?\w+>)/i", "", $noticemsg));
					if ((strlen($noticemsg)* 8) > $domtt_width) {
						$domtt_width = (strlen($noticemsg) *8);
					}
					if ((strlen($noticemsg)* 8) > 900) {
						$domtt_width = 900;
					}
					$alert_action = "onclick=notice_action(\'acknowledge\',\'{$key}\');domTT_close(this);jQuery(this).parent().parent().remove();";
					$notice_msgs .= "<tr><td style=\'vertical-align: top\' width=\'120\'><a href=\'#\' {$alert_link} {$alert_action}>{$date}</a></td><td style=\'vertical-align: top\'><a href=\'#\' {$alert_link} {$alert_action}>[ " . htmlspecialchars($noticemsg) . "]</a></td></tr>";
				}
				$notice_msgs .= "</table>";

				$domtt = "onclick=\"domTT_activate(this, event, 'caption', '{$notice}','content', '<br />{$notice_msgs}', 'trail', false, 'delay', 0, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle','width','{$domtt_width}','y',5,'type', 'sticky');\"";
				$menu_messages = "<div id='alerts'>\n";
				if (count($notices) == 1) {
					$msg = sprintf("%1$02d", count($notices)) . " " . gettext("unread notice");
				} else {
					$msg = sprintf("%1$02d", count($notices)) . " " . gettext("unread notices");
				}
				$menu_messages .= "<div id='marquee-text' style='z-index:1001;'><a href='#' {$domtt}><b> .:. {$msg} .:. </b></a></div>\n";
				$menu_messages .= "</div>\n";
			}
		} else {
			$menu_messages = '<div id="hostname">';
			$menu_messages .= $config['system']['hostname'] . "." . $config['system']['domain'];
			$menu_messages .= '</div>';
		}
		return ($menu_messages);
	}
}

if (!function_exists("dom_title")) {
	function dom_title($title_msg, $width=NULL) {
		$width = preg_replace("/\D+/", "", $width);
		if (!empty($width)) {
			$width = ",'width',$width";
		}
		if (!empty($title_msg)) {
			$title_msg = preg_replace("/\s+/", " ", $title_msg);
			$title_msg = preg_replace("/'/", "\'", $title_msg);
			return "onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\" onmouseover=\"domTT_activate(this, event, 'content', '{$title_msg}', 'trail', true, 'delay', 250, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle' $width);\"";
		}
	}
}
/* include all configuration functions */
require_once("interfaces.inc");
require_once("gwlb.inc");
require_once("services.inc");
require_once("pfsense-utils.inc");
require_once("certs.inc");
require_once("system.inc");
require_once("vslb.inc");

?>
