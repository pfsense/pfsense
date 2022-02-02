<?php
/*
 * priv.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (c) 2006 Paul Taylor <paultaylor@winn-dixie.com>.
 * Copyright (c) 2008 Shrew Soft Inc
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>.
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

require_once("priv.defs.inc");
require_once("auth_func.inc");

/* Load and process custom privs. */
function get_priv_files($directory) {
	$dir_array = array();
	if (!is_dir($directory)) {
		return;
	}
	if ($dh = opendir($directory)) {
		while (($file = readdir($dh)) !== false) {
			$canadd = 0;
			if ($file == ".") {
				$canadd = 1;
			}
			if ($file == "..") {
				$canadd = 1;
			}
			if ($canadd == 0) {
				array_push($dir_array, $file);
			}
		}
		closedir($dh);
	}
	if (!is_array($dir_array)) {
		return;
	}
	return $dir_array;
}

// Load and sort privs
$dir_array = get_priv_files("/etc/inc/priv");
foreach ($dir_array as $file) {
	if (!is_dir("/etc/inc/priv/{$file}") && substr($file, -4) == ".inc") {
		include_once("/etc/inc/priv/{$file}");
	}
}
if (is_dir("/usr/local/pkg/priv")) {
	$dir_array = get_priv_files("/usr/local/pkg/priv");
	foreach ($dir_array as $file) {
		if (!is_dir("/usr/local/pkg/priv/{$file}") &&
		    substr($file, -4) == ".inc") {
			include_once("/usr/local/pkg/priv/{$file}");
		}
	}
}

if (is_array($priv_list)) {
	sort_privs($priv_list);
}

function cmp_privkeys($a, $b) {
	/* user privs at the top */
	$auser = strncmp("user-", $a, 5);
	$buser = strncmp("user-", $b, 5);
	if ($auser != $buser) {
		return $auser - $buser;
	}

	/* name compare others */
	return strcasecmp($a, $b);
}

function sort_privs(& $privs) {
	uksort($privs, "cmp_privkeys");
}

function map_page_privname($page) {
	global $priv_list;

	foreach ($priv_list as $pname => $pdata) {
		if (strncmp($pname, "page-", 5)) {
			continue;
		}
		$fullwc = false;
		if (!strcasecmp($page, "any")||!strcmp($page, "*")) {
			$fullwc = true;
		}
		if (cmp_page_matches($page, $pdata['match'], $fullwc)) {
			return $pname;
		}
	}

	return false;
}

function get_user_privdesc(& $user) {
	global $priv_list;

	$privs = array();

	$user_privs = $user['priv'];
	if (!is_array($user_privs)) {
		$user_privs = array();
	}

	$names = local_user_get_groups($user, true);

	foreach ($names as $name) {
		$group = getGroupEntry($name);
		$group_privs = $group['priv'];
		if (!is_array($group_privs)) {
			continue;
		}
		foreach ($group_privs as $pname) {
			if (in_array($pname, $user_privs)) {
				continue;
			}
			if (!$priv_list[$pname]) {
				continue;
			}
			$priv = $priv_list[$pname];
			$priv['group'] = $group['name'];
			$privs[] = $priv;
		}
	}

	foreach ($user_privs as $pname) {
		if ($priv_list[$pname]) {
			$privs[] = $priv_list[$pname];
		}
	}

	return $privs;
}

function isAdminUID($username) {
	global $_SESSION;

	if (!isset($username)) {
		return false;
	}

	/* admin/root access check */
	$user = getUserEntry($username);
	if (isset($user)) {
		if (isset($user['uid'])) {
			if ($user['uid'] == 0) {
				return true;
			}
		}
	}

	return false;
}

function isAllowed($username, $page) {
	global $_SESSION;

	if (!isset($username)) {
		return false;
	}

	if (isAdminUID($username)) {
		return true;
	}

	/* user privilege access check */
	if (cmp_page_matches($page, $_SESSION['page-match'])) {
		return true;
	}

	return false;
}

function isAllowedPage($page) {
	global $_SESSION;


	$username = $_SESSION['Username'];

	if (!isset($username)) {
		return false;
	}

	if (isAdminUID($username)) {
		return true;
	}

	/* user privilege access check */
	return cmp_page_matches($page, $_SESSION['page-match']);
}

function getPrivPages(& $entry, & $allowed_pages) {
	global $priv_list;

	if (!is_array($entry['priv'])) {
		return;
	}

	foreach ($entry['priv'] as $pname) {
		if (strncmp($pname, "page-", 5) ||
		    !is_array($priv_list[$pname]) ||
		    !is_array($priv_list[$pname]['match'])) {
			continue;
		}
		foreach ($priv_list[$pname]['match'] as $match) {
			$allowed_pages[] = $match;
		}
	}
}

function getAllowedPages($username, &$attributes = array()) {
	global $config, $_SESSION;

	if (!function_exists("ldap_connect")) {
		return;
	}

	$allowed_pages = array();
	$allowed_groups = array();

 	phpsession_begin();
	if ($_SESSION['remoteauth']) {
		$authcfg = auth_get_authserver($config['system']['webgui']['authmode']);
		// cache auth results for a short time to ease load on auth services & logs
		if (isset($config['system']['webgui']['auth_refresh_time'])) {
			$recheck_time = $config['system']['webgui']['auth_refresh_time'];
		} else {
			$recheck_time = 30;
		}
		// obtain ldap groups if we are in ldap mode
		if ($authcfg['type'] == "ldap") {
			if (isset($_SESSION["ldap_allowed_groups"]) &&
			    (time() <= $_SESSION["auth_check_time"] + $recheck_time)) {
				$allowed_groups = $_SESSION["ldap_allowed_groups"];
			} else {
				$allowed_groups = @ldap_get_groups($username, $authcfg);
				$_SESSION["ldap_allowed_groups"] = $allowed_groups;
				$_SESSION["auth_check_time"] = time();
			}
		} elseif ($authcfg['type'] == "radius") {
			if (isset($_SESSION["radius_allowed_groups"]) &&
			    (time() <= $_SESSION["auth_check_time"] + $recheck_time)) {
				$allowed_groups = $_SESSION["radius_allowed_groups"];
			} else {
				$allowed_groups = @radius_get_groups($attributes);
				$_SESSION["radius_allowed_groups"] = $allowed_groups;
				$_SESSION["auth_check_time"] = time();
			}
		}

	}
	if (!$allowed_groups) {
		// search for a local user by name
		$local_user = getUserEntry($username);

		// obtain local user pages and groups if we have a local user
		if ($local_user) {
			getPrivPages($local_user, $allowed_pages);
			$allowed_groups = local_user_get_groups($local_user);
		}
	}

	if (!is_array($allowed_groups)) {
		$allowed_groups = array('all');
	} else {
		$allowed_groups[] = 'all';
	}

	// build a list of allowed pages
	if (is_array($config['system']['group']) && is_array($allowed_groups)) {
		foreach ($config['system']['group'] as $group) {
			if (in_array($group['name'], $allowed_groups)) {
				getPrivPages($group, $allowed_pages);
			}
		}
	}

//	$dbg_pages = implode(",", $allowed_pages);
//	$dbg_groups = implode(",", $allowed_groups);
//	log_error("debug: user {$username} groups = {$dbg_groups}");
//	log_error("debug: user {$username} pages = {$dbg_pages}");

	$_SESSION['page-match'] = $allowed_pages;
	phpsession_end(true);
	return $allowed_pages;
}

function sort_user_privs($privs) {
	// Privileges to place first, to redirect properly.
	$priority_privs = array("page-dashboard-all", "page-system-login-logout");

	$fprivs = array_intersect($privs, $priority_privs);
	$sprivs = array_diff($privs, $priority_privs);

	return array_merge($fprivs, $sprivs);
}
?>
