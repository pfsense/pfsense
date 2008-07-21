<?php
/* $Id$ */
/*
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
*/

require_once("functions.inc");
$groupindex = index_groups();
$userindex = index_users();

function logout_session() {
	global $_SESSION;
	
	if (hasLockAbility($_SESSION['Username']))
		unlink_if_exists("{$g['tmp_path']}/webconfigurator.lock");

	/* wipe out $_SESSION */
	$_SESSION = array();

	/* and destroy it */
	session_destroy();

	$scriptName = split("/", $_SERVER["SCRIPT_FILENAME"]);
	$scriptElms = count($scriptName);
	$scriptName = $scriptName[$scriptElms-1];
}

function getAllowedGroups($logged_in_user) {
	global $g, $config;

	if (!function_exists("ldap_connect"))
		return;
	
	$allowed = array();
	$allowed_groups = array();
	
	$ldapon = $_SESSION['ldapon'];
	//log_error("Getting groups for {$logged_in_user}.");
	
	$local_user = false;

	//log_error("Local_user = {$local_user}");

	foreach ($config['system']['user'] as $username) 
		if ($username['name'] == $logged_in_user)
			$local_user = true;

	/* return ldap groups if we are in ldap mode */
	if ($config['system']['webgui']['backend'] == "ldap" && $local_user == false) {
		//log_error("Calling LDAP_GET_GROUPS from the first section");
		$allowed_groups = ldap_get_groups($logged_in_user);
		$fdny = fopen("/tmp/groups","w");
		fwrite($fdny, print_r($allowed, true));
		fclose($fdny);
		$allowed = array();
		if (is_array($config['system']['group']) && is_array($allowed_groups))
			foreach ($config['system']['group'] as $group)
				if (in_array($group['name'], $allowed_groups))
					foreach ($group['pages'] as $page)
						$allowed[] = $page;
		return $allowed;
	}

	if ($config['system']['webgui']['backend'] == "ldapother" && $local_user == false) {
		//log_error("Calling LDAP_GET_GROUPS from the first section");
		$allowed_groups = ldap_get_groups($logged_in_user);
		$fdny = fopen("/tmp/groups","w");
		fwrite($fdny, print_r($allowed, true));
		fclose($fdny);
		$allowed = array();
		if (is_array($config['system']['group']) && is_array($allowed_groups))
			foreach ($config['system']['group'] as $group)
				if (in_array($group['name'], $allowed_groups))
					foreach ($group['pages'] as $page)
						$allowed[] = $page;
		return $allowed;
	}	

	$final_allowed = array();

	foreach ($config['system']['user'] as $username)
		if ($username['name'] == $logged_in_user) 
			$allowed_groups  = explode(",", $username['groupname']);

	foreach ($config['system']['group'] as $group)
		if (in_array($group['name'], $allowed_groups))
			foreach ($group['pages'] as $page)
				$allowed[] = $page;

	return $allowed;
}

function &getSystemAdminNames() {
	global $config, $g, $userindex;
	$adminUsers = array();

	if (is_array($config['system']['user']))
		foreach ($config['system']['user'] as $user)
			if (isSystemAdmin($user['name']))
				$adminUsers[] = $user['name'];

	return $adminUsers;
}

function &getSystemPrivs() {
	global $g;

	$privs = array();

	$privs[] = array("id" =>	"lockwc",
					 "name" =>	"Lock webConfigurator",
					 "desc" =>	"Indicates whether this user will lock access to " .
								"the webConfigurator for other users.");

	$privs[] = array("id" =>	"lock-ipages",
					 "name" =>	"Lock individual pages",
					 "desc" =>	"Indicates whether this user will lock individual " .
								"HTML pages after having accessed a particular page" .
								"(the lock will be freed if the user leaves or " .
								"saves the page form).");

	$privs[] = array("id" =>	"hasshell",
					 "name" =>	"Has shell access",
					 "desc" =>	"Indicates whether this user is able to login for " .
								"example via SSH.");

	$privs[] = array("id" =>	"copyfiles",
					 "name" =>	"Is allowed to copy files",
					 "desc" =>	"Indicates whether this user is allowed to copy files " .
								"onto the {$g['product_name']} appliance via SCP/SFTP. " .
								"If you are going to use this privilege, you must install " .
								"scponly on the appliance (Hint: pkg_add -r scponly).");

	$privs[] = array("id" =>	"isroot",
					 "name" =>	"Is root user",
					 "desc" =>	"This user is associated with the UNIX root user " .
								"(you should associate this privilege only with one " .
								"single user).");

	return $privs;
}

function assignUID($username = "") {
	global $userindex, $config, $g;

	if ($username == "")
		return;

	$nextuid = $config['system']['nextuid'];
	$user =& $config['system']['user'][$userindex[$username]];

	if (empty($user['uid'])) {
		$user['uid'] = $nextuid;
		$nextuid++;
		$config['system']['nextuid'] = $nextuid;

		write_config();
		return $user;
	}
}

function assignGID($groupname = "") {
	global $groupindex, $config, $g;

	if ($groupname == "")
		return;

	$nextgid = $config['system']['nextgid'];
	$group =& $config['system']['group'][$groupindex[$groupname]];

	if (empty($group['gid'])) {
		$group['gid'] = $nextgid;
		$nextgid++;
		$config['system']['nextgid'] = $nextgid;

		write_config();
		return $group;
	}
}

function hasPrivilege($user, $privid = "") {
	global $userindex, $config, $g;

	if ($privid == "" || ! isset($userindex[$user]))
		return 0;

	$privs = &$config['system']['user'][$userindex[$user]]['priv'];

	if (is_array($privs))
		foreach ($privs as $priv)
			if ($priv['id'] == $privid)
				return 1;
	return 0;
}

function isAllowedToCopyFiles($username) {
	global $userindex, $config, $g;

	if ($username == "")
		return 0;

	return hasPrivilege($username, "copyfiles");
}

function hasLockAbility($username) {
	global $userindex, $config, $g;

	if ($username == "")
		return 0;

	return hasPrivilege($username, "lockwc");
}

function hasPageLockAbility($username) {
	global $userindex, $config, $g;

	if ($username == "")
		return 0;

	return hasPrivilege($username, "lock-ipages");
}

function hasShellAccess($username) {
	global $userindex, $config, $g;

	if ($username == "")
		return 0;

	return hasPrivilege($username, "hasshell");
}

function isUNIXRoot($username = "") {
	global $userindex, $config;

	if ($username == "")
		return 0;

	if (isSystemAdmin($username))
		return hasPrivilege($username, "isroot");

	return 0;
}

function setUserFullName($name = "", $new_name = "") {
	global $config, $g, $userindex;

	if ($name == "" || $new_name == "")
		return;

	$user = &$config['system']['user'][$userindex[$name]];
	$user['fullname'] = $new_name;
}

function setUserName($name = "", $new_name = "") {
	global $config, $g, $userindex;

	if ($name == "" || $new_name == "")
		return;

	$user = &$config['system']['user'][$userindex[$name]];
	$user['name'] = $new_name;
}

function setUserPWD($name = "", $password = "") {
	global $config, $g, $userindex;

	if ($name == "" || $password == "")
		return;

	$user = &$config['system']['user'][$userindex[$name]];
	$user['password'] = crypt($password);
}

function setUserGroupName($name = "", $new_name = "") {
	global $config, $g, $userindex;

	if ($name == "" || $new_name == "")
		return;

	$user = &$config['system']['user'][$userindex[$name]];
	$user['groupname'] = $new_name;
}

function setUserType($name = "", $new_type = "") {
	global $config, $g, $userindex;

	if ($name == "" || $new_type == "")
		return;

	$user = &$config['system']['user'][$userindex[$name]];
	$user['scope'] = $new_type;
}

function getUNIXRoot() {
	global $config, $g, $userindex;

	if (is_array($config['system']['user'])) {
		foreach($config['system']['user'] as $user) {
			if (isUNIXRoot($user['name'])) {
				$root = &$config['system']['user'][$userindex[$user['name']]];
				return $root;
			}
		}
	}

	return NULL;
}

function getUNIXRootName() {
	global $config, $g, $userindex;

	if (is_array($config['system']['user']))
		foreach ($config['system']['user'] as $user)
			if (isUNIXRoot($user['name']))
				return $user['name'];

	return NULL;
}

function getGroupHomePage($group = "") {
	global $groupindex, $config, $g;

	if ($group == "")
		return "";

	$page = $config['system']['group'][$groupindex[$group]]['home'];
	if (empty($page))
		$page = "";

	return $page;
}

function isSystemAdmin($username = "") {
	global $groupindex, $userindex, $config, $g, $_SESSION;

	if ($_SESSION['isSystemAdmin']) 
		return $_SESSION['isSystemAdmin'];

	if (!function_exists("ldap_connect"))
		return;

	if ($username == "") {
		$_SESSION['isSystemAdmin'] = false;
		return 0; 
	}

	$gname = $config['system']['group'][$groupindex[$config['system']['user'][$userindex[$username]]['groupname']]]['name'];

	if (isset($gname)) {
		$_SESSION['isSystemAdmin'] = $gname === $g["admin_group"];
		return ($gname === $g["admin_group"]);
	}

	$_SESSION['isSystemAdmin'] = false; 

	return 0;
}

function getRealName($username = "") {
	global $userindex, $config;

	if ($username == "")
		return "";

	return $config['system']['user'][$userindex[$username]]['fullname'];
}

function basic_auth($backing) {
	global $HTTP_SERVER_VARS;

	/* Check for AUTH_USER */
	if ($HTTP_SERVER_VARS['PHP_AUTH_USER'] <> "") {
		$HTTP_SERVER_VARS['AUTH_USER'] = $HTTP_SERVER_VARS['PHP_AUTH_USER'];
		$HTTP_SERVER_VARS['AUTH_PW'] = $HTTP_SERVER_VARS['PHP_AUTH_PW'];
	}

	if (!isset($HTTP_SERVER_VARS['AUTH_USER'])) {
		require_once("authgui.inc");
		header("WWW-Authenticate: Basic realm=\".\"");
		header("HTTP/1.0 401 Unauthorized");
		display_error_form("401", gettext("You must enter valid credentials to access this resource."));
		exit;
	}

	return $backing($HTTP_SERVER_VARS['AUTH_USER'],$HTTP_SERVER_VARS['AUTH_PW']);
}

function session_auth($backing) {
	global $g, $HTTP_SERVER_VARS, $userindex, $config;

	session_start();

	/* Validate incoming login request */
	if (isset($_POST['login'])) {
		if ($backing($_POST['usernamefld'], $_POST['passwordfld'])) {
			$_SESSION['Logged_In'] = "True";
			$_SESSION['Username'] = $_POST['usernamefld'];
			$_SESSION['last_access'] = time();
			log_error("Successful login for user '{$_POST['usernamefld']}' from: {$_SERVER['REMOTE_ADDR']}");
		} else {
			/* give the user a more detailed error message */
			if (isset($userindex[$_POST['usernamefld']])) {
				$_SESSION['Login_Error'] = "Username or Password incorrect";
				log_error("Wrong password entered for user '{$_POST['usernamefld']}' from: {$_SERVER['REMOTE_ADDR']}");
				if(isAjax()) {
					echo "showajaxmessage('{$_SESSION['Login_Error']}');";
					return;
				}
			} else {
				$_SESSION['Login_Error'] = "Username or Password incorrect";
				log_error("Attempted login for invalid user '{$_POST['usernamefld']}' from: {$_SERVER['REMOTE_ADDR']}");
				if(isAjax()) {
					echo "showajaxmessage('{$_SESSION['Login_Error']}');";
					return;
				}
			}
		}
	}

	/* Show login page if they aren't logged in */
	if (empty($_SESSION['Logged_In'])) {
		/* Don't display login forms to AJAX */
		if (isAjax())
			return false;
		require_once("authgui.inc");
		display_login_form();
		return false;
	}

	/* If session timeout isn't set, we don't mark sessions stale */
	if (!isset($config['system']['webgui']['session_timeout']) ||
		$config['system']['webgui']['session_timeout'] == 0 ||
		$config['system']['webgui']['session_timeout'] == "")
		$_SESSION['last_access'] = time();
	else {
		/* Check for stale session */
		if ($_SESSION['last_access'] < (time() - ($config['system']['webgui']['session_timeout'] * 60))) {
			$_GET['logout'] = true;
			$_SESSION['Logout'] = true;
		} else {
			/* only update if it wasn't ajax */
			if (!isAjax())
				$_SESSION['last_access'] = time();
		}
	}

	/* user hit the logout button */
	if (isset($_GET['logout'])) {

		if ($_SESSION['Logout'])
			log_error("Session timed out for user '{$_SESSION['Username']}' from: {$_SERVER['REMOTE_ADDR']}");
		else
			log_error("User logged out for user '{$_SESSION['Username']}' from: {$_SERVER['REMOTE_ADDR']}");

		if (hasLockAbility($_SESSION['Username']))
			unlink_if_exists("{$g['tmp_path']}/webconfigurator.lock");

		/* wipe out $_SESSION */
		$_SESSION = array();

		if (isset($_COOKIE[session_name()]))
			setcookie(session_name(), '', time()-42000, '/');

		/* and destroy it */
		session_destroy();

		$scriptName = split("/", $_SERVER["SCRIPT_FILENAME"]);
		$scriptElms = count($scriptName);
		$scriptName = $scriptName[$scriptElms-1];

		if (isAjax())
			return false;

		/* redirect to page the user is on, it'll prompt them to login again */
		pfSenseHeader($scriptName);

		return false;
	}

	/*
	 * user wants to explicitely delete the lock file.
	 * Requires a particular privilege.
	 */
	if ($_GET['deletelock'] && hasLockAbility($_SESSION['Username'])) {
		unlink_if_exists("{$g['tmp_path']}/webconfigurator.lock");
		$HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
		return true;
	}

	/*
	 * user wants to explicitely create a lock.
	 * Requires a particular privilege.
	 */
	if ($_GET['createlock'] && hasLockAbility($_SESSION['Username'])) {
		$fd = fopen("{$g['tmp_path']}/webconfigurator.lock", "w");
		fputs($fd, "{$_SERVER['REMOTE_ADDR']} (" .
			getRealName($_SESSION['Username']) . ")");
		fclose($fd);

		/*
		 * if the user did delete the lock manually, do not
		 * re-create it while the session is valide.
		 */
		$_SESSION['Lock_Created'] = "True";
		$HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
		return true;
	}

	/*
	 * this is for debugging purpose if you do not want to use Ajax
	 * to submit a HTML form. It basically diables the observation
	 * of the submit event and hence does not trigger Ajax.
	 */
	if ($_GET['disable_ajax']) {
		$_SESSION['NO_AJAX'] = "True";
		$HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
		return true;
	}

	/*
	 * Same to re-enable Ajax.
	 */
	if ($_GET['enable_ajax']) {
		unset($_SESSION['NO_AJAX']);
		$HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
		return true;
	}

	/*
	 * is the user is allowed to create a lock
	 */
	if (hasLockAbility($_SESSION['Username'])) {

		/*
		 * create a lock once per session
		 */
		if (!isset($_SESSION['Lock_Created'])) {

			$fd = fopen("{$g['tmp_path']}/webconfigurator.lock", "w");
			fputs($fd, "{$_SERVER['REMOTE_ADDR']} (" .
				getRealName($_SESSION['Username']) . ")");
			fclose($fd);

			/*
			 * if the user did delete the lock manually, do not
			 * re-create it while the session is valide.
			 */
			$_SESSION['Lock_Created'] = "True";
		}

	} else {

		/*
		 * give regular users a chance to automatically invalidate
		 * a lock if its older than a particular time.
		 */
		if (file_exists("{$g['tmp_path']}/webconfigurator.lock")) {

			$offset = 12; //hours
			$mtime = filemtime("{$g['tmp_path']}/webconfigurator.lock");
			$now_minus_offset = mktime(date("H") - $offset, 0, 0,
									date("m"), date("d"), date("Y"));

			if (($mtime - $now_minus_offset) < $mtime) {
				require_once("authgui.inc");
				display_login_form();
				return false;
			}

			/*
			 * file is older than mtime + offset which may
			 * indicate a stale lockfile, hence we are going
			 * to remove it.
			 */
			unlink_if_exists("{$g['tmp_path']}/webconfigurator.lock");
		}
	}

	$HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
	return true;
}

function pam_backed($username = "", $password = "") {

	/* do not allow blank passwords */
	if ($username == "" || password == "")
		return false;

	if (!extension_loaded( 'pam_auth'))
		if (!@dl('pam_auth.so'))
			return false;

	/* no php file no auth, sorry */
	if (!file_exists("/etc/pam.d/php")) {

		if (!file_exists("/etc/pam.d"))
			mkdir("/etc/pam.d");

       	$pam_php = <<<EOD

# /etc/pam.d/php
#
# note: both an auth and account entry are required

# auth
auth            required        pam_nologin.so          no_warn
auth            sufficient      pam_opie.so             no_warn no_fake_prompts
auth            requisite       pam_opieaccess.so       no_warn allow_local
auth            required        pam_unix.so             no_warn try_first_pass

# account
account         required        pam_unix.so

# session
session         required        pam_permit.so

# password
password        required        pam_unix.so             no_warn try_first_pass

EOD;

		file_put_contents("/etc/pam.d/php", $pam_php);
	}

	if (pam_auth($username, $password, &$error))
		return true;

	return false;
}

function passwd_backed($username, $passwd) {

	$authfile = file("/etc/master.passwd");
	$matches="";

	/* Check to see if user even exists */
	if(!($line = array_shift(preg_grep("/^$username:.*$/", $authfile))))
		return false;

	/* Get crypted password */
	preg_match("/^$username:((\\$1\\$[.\d\w_\/]{8}\\$)[.\d\w_\/]{22})$/", $line, $matches);
	$pass = $matches[1];
	$salt = $matches[2];

	/*
	 * Encrypt entered password with salt
	 * And finally validate password
	 */
	if ($pass == crypt($passwd, $salt))
		return true;

	return false;
}

function ldap_test_connection() {
	global $config, $g;

	$ldapserver = $config['system']['webgui']['ldapserver'];
	$ldapbindun = $config['system']['webgui']['ldapbindun'];
	$ldapbindpw = $config['system']['webgui']['ldapbindpw'];

	if (!($ldap = ldap_connect($ldapserver)))
		return false;

	return true;
}

function ldap_test_bind() {
	global $config, $g;

	$ldapserver = $config['system']['webgui']['ldapserver'];
	$ldapbindun = $config['system']['webgui']['ldapbindun'];
	$ldapbindpw = $config['system']['webgui']['ldapbindpw'];
    
	if (!($ldap = ldap_connect($ldapserver)))
		return false;

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    
	if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw)))
		return false;

	return true;
}

function ldap_get_user_ous($show_complete_ou=true) {
	global $config, $g;

	if(!function_exists("ldap_connect"))
		return;

	$ldapserver     = $config['system']['webgui']['ldapserver'];
	$ldapbindun     = $config['system']['webgui']['ldapbindun'];
	$ldapbindpw     = $config['system']['webgui']['ldapbindpw'];
	$ldapsearchbase = "{$config['system']['webgui']['ldapsearchbase']}";
	$ldaptype       = $config['system']['webgui']['backend'];

	$ldapfilter = "(ou=*)";
	putenv('LDAPTLS_REQCERT=never');
	if (!($ldap = ldap_connect($ldapserver))) {
		log_error("ERROR!  ldap_get_groups() could not connect to server {$ldapserver}.  Defaulting to built-in htpasswd_backed()");
		$status = htpasswd_backed($username, $passwd);
		return $status;
	}

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

	if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		log_error("ERROR! ldap_get_groups() could not bind to {$ldapserver} - {$ldapfilter}.  Defaulting to built-in htpasswd_backed()");
		$status = htpasswd_backed($username, $passwd);
		return $status;
	}

	$search = ldap_search($ldap, $ldapsearchbase, $ldapfilter);

	$info = ldap_get_entries($ldap, $search);

	$ous = array();

	if (is_array($info)) {
		foreach ($info as $inf) {
			if (!$show_complete_ou) {
				$inf_split = split(",", $inf['dn']);
				$ou = $inf_split[0];
				$ou = str_replace("OU=","", $ou);
			} else
				if($inf['dn'])
					$ou = $inf['dn'];
			if($ou)
				$ous[] = $ou;
		}
	}

	//Tack on the default Users container for AD since its non-standard
	if($ldaptype == 'ldap')
		$ous[] = "CN=Users,".$ldapsearchbase;

	return $ous;
}

function ldap_get_groups($username) {
	global $config;
	
	if(!function_exists("ldap_connect"))
		return;
	
	if(!$username) 
		return false;

	if(stristr($username, "@")) {
		$username_split=split("\@", $username);
		$username = $username_split[0];		
	}

	if(stristr($username, "\\")) {
		$username_split=split("\\", $username);
		$username = $username_split[0];        
	}    
	
	//log_error("Getting LDAP groups for {$username}.");
	
	$ldapserver         = $config['system']['webgui']['ldapserver'];
	$ldapbindun         = $config['system']['webgui']['ldapbindun'];
	$ldapbindpw         = $config['system']['webgui']['ldapbindpw'];
	$ldapfilter         = $config['system']['webgui']['ldapfilter'];
	$ldapfilter         = str_replace("\$username", $username, $ldapfilter);
	$ldapgroupattribute = $config['system']['webgui']['ldapgroupattribute'];
	$ldapdn             = $_SESSION['ldapdn'];
	 
	/*Convert attribute to lowercase.  php ldap arrays put everything in lowercase */
	$ldapgroupattribute = strtolower($ldapgroupattribute);

	/* connect and see if server is up */
	putenv('LDAPTLS_REQCERT=never');
	if (!($ldap = ldap_connect($ldapserver))) {
		log_error("ERROR!  ldap_get_groups() could not connect to server {$ldapserver}.  Defaulting to built-in htpasswd_backed()");
		$status = htpasswd_backed($username, $passwd);
		return $status;	
	}
    
	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

	/* bind as user that has rights to read group attributes */
	if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		log_error("ERROR! ldap_get_groups() could not bind to {$ldapserver} - {$ldapfilter}.  Defaulting to built-in htpasswd_backed()");
		$status = htpasswd_backed($username, $passwd);
		return $status;
	}

	/* get groups from DN found */
	/* use ldap_read instead of search so we don't have to do a bunch of extra work */
	/* since we know the DN is in $_SESSION['ldapdn'] */
	//$search    = ldap_read($ldap, $ldapdn, "(objectclass=*)", array($ldapgroupattribute));
	$search    = ldap_read($ldap, $ldapdn, $ldapfilter, array($ldapgroupattribute));
	$info      = ldap_get_entries($ldap, $search);

	$countem = $info["count"];	
	$memberof = array();
	
	if(is_array($info[0][$ldapgroupattribute])) {
		/* Iterate through the groups and throw them into an array */
		foreach ($info[0][$ldapgroupattribute] as $member) {
			if (stristr($member, "CN=") !== false) {
				$membersplit = split(",", $member);
				$memberof[] = preg_replace("/CN=/i", "", $membersplit[0]);
			}
		}
	}
	
	/* Time to close LDAP connection */
	ldap_close($ldap);
	
	$groups = print_r($memberof,true);
	
	//log_error("Returning groups ".$groups." for user $username");
	
	return $memberof;
}

function ldap_backed($username, $passwd) {
	global $config;
	
	if(!$username) 
		return;

	if(!function_exists("ldap_connect"))
		return;

	$adbindas = $username;
    
	if(stristr($username, "@")) {
		$username_split=split("\@", $username);
		$username = $username_split[0];        
	}
	if(stristr($username, "\\")) {
		$username_split=split("\\", $username);
		$username = $username_split[0];        
	}

	$ldapserver         = $config['system']['webgui']['ldapserver'];
	$ldapbindun         = $config['system']['webgui']['ldapbindun'];
	$ldapbindpw         = $config['system']['webgui']['ldapbindpw'];
	$ldapauthcont       = $config['system']['webgui']['ldapauthcontainers'];   
	$ldapnameattribute  = $config['system']['webgui']['ldapnameattribute'];  
	$ldapfilter         = $config['system']['webgui']['ldapfilter'];
	$ldaptype           = $config['system']['webgui']['backend'];
	$ldapfilter = str_replace("\$username", $username, $ldapfilter);

	/* first check if there is even an LDAP server populated */ 
	if(!$ldapserver) {
		log_error("ERROR!  ldap_backed() backed selected with no LDAP authentication server defined.  Defaulting to built-in htpasswd_backed().     Visit System -> User Manager -> Settings.");
		$status = htpasswd_backed($username, $passwd);
		return $status;
	}
	
	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

	/* Make sure we can connect to LDAP */
	putenv('LDAPTLS_REQCERT=never');
	if (!($ldap = ldap_connect($ldapserver))) {
		log_error("ERROR!  ldap_backed() could not connect to server {$ldapserver} - {$ldapfilter}.  Defaulting to built-in htpasswd_backed().     Visit System -> User Manager -> Settings.");
		$status = htpasswd_backed($username, $passwd);		
		return $status;	
	}
	/* ok, its up.  now, lets bind as the bind user so we can search it */
	if (!($res = ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		log_error("ERROR! ldap_backed() could not bind to {$ldapserver} - {$ldapfilter}.  Defaulting to built-in htpasswd_backed()");
		ldap_close($ldap);
		$status = htpasswd_backed($username, $passwd);
		return $status;
	}
	
	/* Get LDAP Authcontainers and split em up. */
	$ldac_split = split(";", $ldapauthcont);
	
	/* now count how many there are */
	$containers = count($ldac_split);
	log_error("Number of Authentication Containers to search for $username is {$containers}");
	
	/* setup the usercount so we think we havn't found anyone yet */
	$usercount  = 0;

	/******************************/
	/* Currently LDAP Types are   */
	/* LDAP = Active Directory    */
	/* LDAPOTHER = eDir/Openldap  */
	/******************************/      
        
	/*****************************************************************/
	/* Now Active Directory We keep this seperate for future addons. */
	/*****************************************************************/
	/* Now LDAP other.  eDirectory or Netscape or Sunone or OpenLDAP */
	/*****************************************************************/
	/*  We First find the user based on username and filter          */
	/*  Then, once we find the first occurance of that person        */
	/*  We set seesion variables to ponit to the OU and DN of the    */
	/*  Person.  To later be used by ldap_get_groups.                */
	/*  that way we don't have to search twice.                      */
	/*****************************************************************/
	if ($ldaptype == 'ldap'){
		log_error("Now Searching for {$username} in Active directory.");
		/* Iterate through the user containers for search */
		for ($i=0;$i<$containers;$i++){
			/* Make sure we just use the first user we find */
			log_error("Now Searching in {$ldac_split[$i]} for {$ldapfilter}.");
			$search	 = ldap_search($ldap,$ldac_split[$i],$ldapfilter);
			$info	 = ldap_get_entries($ldap,$search);
			$matches = $info['count'];
			log_error("Matches Found = {$matches}");
			if ($matches == 1){
				$_SESSION['ldapdn'] = $info[0]['dn'];
				$_SESSION['ldapou'] = $ldac_split[$i];
				$_SESSION['ldapon'] = "true";
				$ldapdn = $_SESSION['ldapdn'];
				$userou = $_SESSION['ldapou'];
				break;
			}
		}

		if ($matches == 1){
			$binduser = $adbindas;
			log_error("Going to login as {$username} - DN = {$_SESSION['ldapdn']}");
		}
		if ($matches != 1){
			log_error("ERROR! Either LDAP search failed, or multiple users were found");
			$status = htpasswd_backed($username, $passwd);
			$_SESSION['ldapon'] = "false";
			ldap_close($ldap);
			return $status;                         
		}
	}

	/*****************************************************************/
	/* Now LDAP other.  eDirectory or Netscape or Sunone or OpenLDAP */
	/*****************************************************************/
	/*  We First find the user based on username and filter          */
	/*  Then, once we find the first occurance of that person        */
	/*  We set seesion variables to ponit to the OU and DN of the    */
	/*  Person.  To later be used by ldap_get_groups.                */
	/*  that way we don't have to search twice.                      */
	/*****************************************************************/
	if ($ldaptype == 'ldapother'){
		log_error("Now Searching for {$username} in LDAP.");
		/* Iterate through the user containers for search */
		for ($i=0;$i<$containers;$i++){
			/* Make sure we just use the first user we find */
			log_error("Now searching in {$ldac_split[$i]} for {$ldapfilter}.");
			$search  = ldap_search($ldap,$ldac_split[$i],$ldapfilter);
            $info    = ldap_get_entries($ldap,$search);
            $matches = $info['count'];
            log_error("Matches Found = {$matches}.");
                                      
			if ($matches == 1){
				$_SESSION['ldapdn'] = $info[0]['dn'];
				$_SESSION['ldapou'] = $ldac_split[$i];
				$_SESSION['ldapon'] = "true";
				$ldapdn = $_SESSION['ldapdn'];
				$userou = $_SESSION['ldapou'];
				break;
			}
		}
		if($matches == 1){
			$binduser = $ldapnameattribute."=".$username.",".$userou;
			log_error("Going to login as {$username} - DN = {$_SESSION['ldapdn']}");
		}
		if($matches != 1){
			log_error("ERROR! Either LDAP search failed, or multiple users were found");
			$status = htpasswd_backed($username, $passwd);
			ldap_close($ldap);
			$_SESSION['ldapon'] = "false";
			return $status;                         
		}
	}
	
	/* Now lets bind as the user we found */
	if (!($res = @ldap_bind($ldap, $binduser, $passwd))) {
		log_error("ERROR!  ldap_backed() could not bind to {$ldapserver} - {$username} - {$passwd}.  Defaulting to built-in htpasswd_backed().    Visit System -> User Manager -> Settings.");
		$status = htpasswd_backed($username, $passwd);
		return $status;
	}

	log_error("$binduser succesfully logged in via LDAP.");

	/* At this point we are bound to LDAP so the user was auth'd okay. */
	return true;
}

function htpasswd_backed($username, $passwd) {
	$authfile = file("/var/run/htpasswd");

	/* sanity check to ensure that /usr/local/www/.htpasswd doesn't exist */
	unlink_if_exists("/usr/local/www/.htpasswd");

	$matches="";
	if(!($line = array_shift(preg_grep("/^$username:.*$/", $authfile))))
		return false;

	/* Get crypted password */
	preg_match("/^$username:((\\$1\\$[.\d\w_\/]{8}\\$)[.\d\w_\/]{22})$/", $line, $matches);
	$pass = $matches[1];
	$salt = $matches[2];

	/* Encrypt entered password with salt
	 * And finally validate password
	 */
	if ($pass == crypt($passwd, $salt))
		return true;

	return false;
}

function radius_backed($username, $passwd){
	global $config, $debug;
	$ret = false;
	$radiusservers = $config['system']['radius']['servers'];

	$rauth = new Auth_RADIUS_PAP($username, $passwd);
	/* Add a new servers to our instance */
	foreach ($radiusservers as $radsrv)
		$rauth->addServer($radsrv['ipaddr'], $radsrv['port'], $radsrv['sharedsecret']);

	if (!$rauth->start()) {
		$retvalue['auth_val'] = 1;
		$retvalue['error'] = $rauth->getError();
		if ($debug)
			printf("Radius start: %s<br>\n", $retvalue['error']);
	}

	// XXX - billm - somewhere in here we need to handle securid challenge/response

	/* Send request */
	$result = $rauth->send();
	if (PEAR::isError($result)) {
		$retvalue['auth_val'] = 1;
		$retvalue['error'] = $result->getMessage();
		if ($debug)
			printf("Radius send failed: %s<br>\n", $retvalue['error']);
	} else if ($result === true) {
		$retvalue['auth_val'] = 2;
		if ($debug)
			printf(gettext("Radius Auth succeeded")."<br>\n");
		$ret = true;
	} else {
		$retvalue['auth_val'] = 3;
		if ($debug)
			printf(gettext("Radius Auth rejected")."<br>\n");
	}

	// close OO RADIUS_AUTHENTICATION
	$rauth->close();

	return $ret;
}

function index_groups() {
	global $g, $config, $groupindex;

	$groupindex = array();

	if (isset($config['system']['group'])) {
		$i = 0;
		foreach($config['system']['group'] as $groupent) {
			$groupindex[$groupent['name']] = $i;
			$i++;
		}
	}

	return ($groupindex);
}

function index_users() {
	global $g, $config;

	if (isset($config['system']['user'])) {
		$i = 0;
		foreach($config['system']['user'] as $userent) {
			$userindex[$userent['name']] = $i;
			$i++;
		}
	}

	return ($userindex);
}

?>
