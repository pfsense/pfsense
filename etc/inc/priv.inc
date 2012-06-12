<?php
/* $Id$ */
/*
		Copyright (C) 2008 Shrew Soft Inc
		All rights reserved.

		Copyright (C) 2007, 2008 Scott Ullrich <sullrich@gmail.com>
		All rights reserved.

        Copyright (C) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
        All rights reserved.

        Copyright (C) 2006 Paul Taylor <paultaylor@winn-dixie.com>.
        All rights reserved.

        Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
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

		DISABLE_PHP_LINT_CHECKING
*/

/*
	pfSense_MODULE:	auth
*/

require_once("priv.defs.inc");

/* Load and process custom privs. */
function get_priv_files($directory) {
	$dir_array = array();
	if(!is_dir($directory)) 
		return;
	if ($dh = opendir($directory)) {
		while (($file = readdir($dh)) !== false) {
			$canadd = 0;
			if($file == ".") 
				$canadd = 1;
			if($file == "..") 
				$canadd = 1;
			if($canadd == 0)
				array_push($dir_array, $file);
		}
		closedir($dh);
	}
	if(!is_array($dir_array))
		return;
	return $dir_array;
}

// Load and sort privs
$dir_array = get_priv_files("/etc/inc/priv");
foreach ($dir_array as $file) 
	if (!is_dir("/etc/inc/priv/{$file}") && stristr($file,".inc")) 
		include("/etc/inc/priv/{$file}");
if(is_dir("/usr/local/pkg/priv")) {
	$dir_array = get_priv_files("/usr/local/pkg/priv");
	foreach ($dir_array as $file) 
		if (!is_dir("/usr/local/pkg/priv/{$file}") && stristr($file,".inc")) 
			include("/usr/local/pkg/priv/{$file}");
}

if(is_array($priv_list))
	sort_privs($priv_list);

function cmp_privkeys($a, $b) {
	/* user privs at the top */
	$auser = strncmp("user-", $a, 5);
	$buser = strncmp("user-", $b, 5);
	if($auser != $buser)
		return $auser - $buser;

	/* name compare others */
	return strcasecmp($a, $b);
}

function sort_privs(& $privs) {
	uksort($privs, "cmp_privkeys");
}

function cmp_page_matches($page, & $matches, $fullwc = true) {

//	$dbg_matches = implode(",", $matches);
//	log_error("debug: checking page {$page} match with {$dbg_matches}");

	if (!is_array($matches))
		return false;

	/* skip any leading fwdslash */
	$test = strpos($page, "/");
	if ($test !== false && $test == 0)
		$page = substr($page, 1);

	/* look for a match */
	foreach ($matches as $match) {

		/* possibly ignore full wildcard match */
		if (!$fullwc && !strcmp($match ,"*"))
			continue;

		/* compare exact or wildcard match */
		$match =  str_replace(array(".", "*","?"), array("\.", ".*","\?"), $match);
		$result = preg_match("@^/{$match}$@", "/{$page}");
		
		if ($result)
			return true;
	}

	return false;
}

function map_page_privname($page) {
	global $priv_list;

	foreach ($priv_list as $pname => $pdata) {
		if (strncmp($pname, "page-", 5))
			continue;
		$fullwc = false;
		if (!strcasecmp($page,"any")||!strcmp($page,"*"))
			$fullwc = true;
		if (cmp_page_matches($page, $pdata['match'], $fullwc))
			return $pname;
	}

	return false;
}

function get_user_privdesc(& $user) {
	global $priv_list;

	$privs = array();

	$user_privs = $user['priv'];
	if (!is_array($user_privs))
		$user_privs = array();

	$names = local_user_get_groups($user, true);

	foreach ($names as $name) {
		$group = getGroupEntry($name);
		$group_privs = $group['priv'];
		if (!is_array($group_privs))
			continue;
		foreach ($group_privs as $pname) {
			if (in_array($pname,$user_privs))
				continue;
			if (!$priv_list[$pname])
				continue;
			$priv = $priv_list[$pname];
			$priv['group'] = $group['name'];
			$privs[] = $priv;
		}
	}

	foreach ($user_privs as $pname)
		if($priv_list[$pname])
			$privs[] = $priv_list[$pname];

	return $privs;
}

function isAllowed($username, $page) {
	global $_SESSION;

	if (!isset($username))
		return false;

	/* admin/root access check */
	$user = getUserEntry($username);
	if (isset($user))
		if (isset($user['uid']))
			if ($user['uid']==0)
				return true;

	/* user privelege access check */
	if (cmp_page_matches($page, $_SESSION['page-match']))
		return true;

	return false;
}


function isAllowedPage($page) {
	global $_SESSION;


	$username = $_SESSION['Username'];

	if (!isset($username))
		return false;

	/* admin/root access check */
	$user = getUserEntry($username);
	if (isset($user))
		if (isset($user['uid']))
			if ($user['uid']==0)
				return true;

	/* user privelege access check */
	return cmp_page_matches($page, $_SESSION['page-match']);
}

function getPrivPages(& $entry, & $allowed_pages) {
	global $priv_list;

	if (!is_array($entry['priv']))
		return;

	foreach ($entry['priv'] as $pname) {
		if (strncmp($pname, "page-", 5))
			continue;
		$priv = &$priv_list[$pname];
		if (!is_array($priv))
			continue;
		$matches = &$priv['match'];
		if (!is_array($matches))
			continue;
		foreach ($matches as $match)
			$allowed_pages[] = $match;
	}
}

function getAllowedPages($username) {
	global $config, $_SESSION;

	if (!function_exists("ldap_connect"))
		return;
	
	$allowed_pages = array();
	$allowed_groups = array();
	
	$authcfg = auth_get_authserver($config['system']['webgui']['authmode']);
	// obtain ldap groups if we are in ldap mode
	if ($authcfg['type'] == "ldap")
		$allowed_groups = @ldap_get_groups($username, $authcfg);
	else {
		// search for a local user by name
		$local_user = getUserEntry($username);
		getPrivPages($local_user, $allowed_pages);

		// obtain local groups if we have a local user
		if ($local_user)
			$allowed_groups = local_user_get_groups($local_user);
	}

	// build a list of allowed pages
	if (is_array($config['system']['group']) && is_array($allowed_groups))
		foreach ($config['system']['group'] as $group)
			if (in_array($group['name'], $allowed_groups))
				getPrivPages($group, $allowed_pages);

//	$dbg_pages = implode(",", $allowed_pages);
//	$dbg_groups = implode(",", $allowed_groups);
//	log_error("debug: user {$username} groups = {$dbg_groups}");
//	log_error("debug: user {$username} pages = {$dbg_pages}");

	$_SESSION['page-match'] = $allowed_pages;

	return $allowed_pages;
}

function sort_user_privs($privs) {
	// Privileges to place first, to redirect properly.
	$priority_privs = array("page-dashboard-all", "page-system-login/logout");

	$fprivs = array_intersect($privs, $priority_privs);
	$sprivs  = array_diff($privs, $priority_privs);

	return array_merge($fprivs, $sprivs);
}
?>