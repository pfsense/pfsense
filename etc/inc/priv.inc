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
class Privilege {
	public static $priv_list;

	public static function init() {
		$priv_list = self::loadPrivileges();
		$priv_list = array_merge($priv_list, self::loadUserPrivileges());
		$priv_list = array_merge($priv_list, self::loadPackagePrivileges());

		if(is_array($priv_list)) {
			self::sort_privs($priv_list);
		}

		self::$priv_list = $priv_list;
	}

	public static function loadPrivileges() {
		$priv_list = array();
		require_once("priv.defs.inc");
		return $priv_list;
	}

	public static function loadUserPrivileges() {
		$_priv_list = array();

		// Load and sort privs
		$privilegeFiles = self::get_priv_files("/etc/inc/priv");

		foreach ($privilegeFiles as $file) {
			if (!is_dir("/etc/inc/priv/{$file}") && stristr($file, ".inc")) {
				require_once("/etc/inc/priv/{$file}");
				
				if(is_array($priv_list)) {
					$_priv_list = array_merge($_priv_list, $priv_list);
				}
			}
		}

		return $_priv_list;
	}

	public static function loadPackagePrivileges() {
		$priv_list = array();

		if(is_dir("/usr/local/pkg/priv")) {
			$privilegeFiles = self::get_priv_files("/usr/local/pkg/priv");

			foreach ($privilegeFiles as $file) {
				if (!is_dir("/usr/local/pkg/priv/{$file}") && stristr($file,".inc")) {
					require_once("/usr/local/pkg/priv/{$file}");
					$_priv_list = array_merge($_priv_list, $priv_list);
				}
			}
		}

		return $priv_list;
	}

	/* Load and process custom privs. */
	public static function get_priv_files($directory) {
		$dir_array = array();
		
		if(!is_dir($directory)) 
			return;
		
		if ($dh = opendir($directory)) {
			while (($file = readdir($dh)) !== false) {
				if(in_array($file, array('.', '..'))) continue;
				$dir_array[] = $file;
			}
			closedir($dh);
		}

		return $dir_array;
	}

	public static function cmp_privkeys($a, $b) {
		/* user privs at the top */
		$auser = strncmp("user-", $a, 5);
		$buser = strncmp("user-", $b, 5);
		if($auser != $buser)
			return $auser - $buser;

		/* name compare others */
		return strcasecmp($a, $b);
	}

	public static function sort_privs(& $privs) {
		uksort($privs, array(self, 'cmp_privkeys'));
	}

	public static function cmp_page_matches($page, & $matches, $fullwc = true) {

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

	public static function map_page_privname($page) {
		foreach (self::$priv_list as $pname => $pdata) {
			if (strncmp($pname, "page-", 5))
				continue;
			$fullwc = false;
			if (!strcasecmp($page,"any")||!strcmp($page,"*"))
				$fullwc = true;
			if (self::cmp_page_matches($page, $pdata['match'], $fullwc))
				return $pname;
		}

		return false;
	}

	public static function get_user_privdesc(& $user) {
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
				if (!self::$priv_list[$pname])
					continue;
				$priv = self::$priv_list[$pname];
				$priv['group'] = $group['name'];
				$privs[] = $priv;
			}
		}

		foreach ($user_privs as $pname)
			if(self::$priv_list[$pname])
				$privs[] = self::$priv_list[$pname];

		return $privs;
	}

	public static function isAllowed($username, $page) {
		if (!isset($username))
			return false;

		/* admin/root access check */
		$user = getUserEntry($username);
		if (isset($user))
			if (isset($user['uid']))
				if ($user['uid']==0)
					return true;

		/* user privelege access check */
		if (self::cmp_page_matches($page, $_SESSION['page-match']))
			return true;

		return false;
	}

	public static function isAllowedPage($page) {
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
		return self::cmp_page_matches($page, $_SESSION['page-match']);
	}

	public static function getPrivPages(& $entry, & $allowed_pages) {
		if (!is_array($entry['priv']))
			return;

		foreach ($entry['priv'] as $pname) {
			if (strncmp($pname, "page-", 5)) continue;

			$priv = &self::$priv_list[$pname];

			if (!is_array($priv)) continue;

			$matches = &$priv['match'];

			if (!is_array($matches)) continue;

			foreach ($matches as $match) {
				$allowed_pages[] = $match;
			}
		}
	}

	public static function getAllowedPages($username) {
		global $config, $_SESSION;

		$allowed_pages = array();
		$allowed_groups = array();
		
		$authcfg = auth_get_authserver($config['system']['webgui']['authmode']);
		// obtain ldap groups if we are in ldap mode
		if ($authcfg['type'] == "ldap") {
			$allowed_groups = @ldap_get_groups($username, $authcfg);
			if (!function_exists("ldap_connect")) return;
		}
		else {
			// search for a local user by name
			$local_user = getUserEntry($username);
			self::getPrivPages($local_user, $allowed_pages);

			// obtain local groups if we have a local user
			if ($local_user)
				$allowed_groups = local_user_get_groups($local_user);
		}

		// build a list of allowed pages
		if (is_array($config['system']['group']) && is_array($allowed_groups)) {
			foreach ($config['system']['group'] as $group) {
				if (in_array($group['name'], $allowed_groups)) {
					self::getPrivPages($group, $allowed_pages);
				}
			}
		}

		$_SESSION['page-match'] = $allowed_pages;

		return $allowed_pages;
	}

	public static function sort_user_privs($privs) {
		// Privileges to place first, to redirect properly.
		$priority_privs = array("page-dashboard-all", "page-system-login/logout");

		$fprivs = array_intersect($privs, $priority_privs);
		$sprivs  = array_diff($privs, $priority_privs);

		return array_merge($fprivs, $sprivs);
	}
}

Privilege::init();
