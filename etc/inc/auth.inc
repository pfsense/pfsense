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
	
      if (hasLockAbility($_SESSION['Username'])) {
        unlink_if_exists("{$g['tmp_path']}/webconfigurator.lock");
      }

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

	if(!function_exists("ldap_connect"))
		return;
	
	$allowed = array();
	$allowed_groups = array();
	
	log_error("Getting groups for {$logged_in_user}.");
	
	$local_user = false;
	
	foreach($config['system']['user'] as $username) 
		if($username['name'] == $logged_in_user) 
			$local_user = true;
	
	/* return ldap groups if we are in ldap mode */
	if($config['system']['webgui']['backend'] == "ldap" && $local_user == false) {
		$allowed_groups = ldap_get_groups($logged_in_user);
		$fdny = fopen("/tmp/groups","w");
		fwrite($fdny, print_r($allowed, true));
		fclose($fdny);
		$allowed = array();
		if(is_array($config['system']['group']) && is_array($allowed_groups)) {
			foreach($config['system']['group'] as $group) {
				if(in_array($group['name'], $allowed_groups)) {
					foreach($group['pages'] as $page) {
						$allowed[] = $page;
					}				
				}
			}
		}
		return $allowed;
	}
	if($config['system']['webgui']['backend'] == "ldapother" && $local_user == false) {
		$allowed_groups = ldap_get_groups($logged_in_user);
		$fdny = fopen("/tmp/groups","w");
		fwrite($fdny, print_r($allowed, true));
		fclose($fdny);
		$allowed = array();
		if(is_array($config['system']['group']) && is_array($allowed_groups)) {
			foreach($config['system']['group'] as $group) {
				if(in_array($group['name'], $allowed_groups)) {
					foreach($group['pages'] as $page) {
						$allowed[] = $page;
					}				
				}
			}
		}
		return $allowed;
	}	
	
	$final_allowed = array();

	foreach($config['system']['user'] as $username) {
		if($username['name'] == $logged_in_user) 
			$allowed_groups  = explode(",", $username['groupname']);
	}
		
	foreach($config['system']['group'] as $group) {
		if(in_array($group['name'], $allowed_groups)) {
			foreach($group['pages'] as $page) {
				$allowed[] = $page;
			}
		}
	}
	
	return $allowed;
}

function &getSystemAdminNames() {
  global $config, $g, $userindex;
  $adminUsers = array();

  if (is_array($config['system']['user'])) {
    foreach($config['system']['user'] as $user){
      if (isSystemAdmin($user['name'])) {
        $adminUsers[] = $user['name'];
      }
    }
  }

  return $adminUsers;
}

function &getSystemPrivs() {
  global $g;

  $privs = array();

  $privs[] = array("id" => "lockwc",
                   "name" => "Lock webConfigurator",
                   "desc" => "Indicates whether this user will lock access to " .
                             "the webConfigurator for other users.");
  $privs[] = array("id" => "lock-ipages",
                   "name" => "Lock individual pages",
                   "desc" => "Indicates whether this user will lock individual " .
                              "HTML pages after having accessed a particular page" .
                              "(the lock will be freed if the user leaves or " .
                              "saves the page form).");
  $privs[] = array("id" => "hasshell",
                   "name" => "Has shell access",
                   "desc" => "Indicates whether this user is able to login for " .
                   "example via SSH.");
  $privs[] = array("id" => "copyfiles",
                   "name" => "Is allowed to copy files",
                   "desc" => "Indicates whether this user is allowed to copy files " .
                             "onto the {$g['product_name']} appliance via SCP/SFTP. " .
                             "If you are going to use this privilege, you must install " .
                             "scponly on the appliance (Hint: pkg_add -r scponly).");
  $privs[] = array("id" => "isroot",
                   "name" => "Is root user",
                   "desc" => "This user is associated with the UNIX root user " .
                             "(you should associate this privilege only with one " .
                             "single user).");

  return $privs;
}

function assignUID($username = "") {
  global $userindex, $config, $g;

  if ($username == "") { return; }

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

  if ($groupname == "") { return; }

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

  if ($privid == "" || ! isset($userindex[$user])) { return 0; }

  $privs = &$config['system']['user'][$userindex[$user]]['priv'];

  if (is_array($privs)) {
    foreach($privs as $priv){
      if ($priv['id'] == $privid) {
        return 1;
      }
    }
  }

  return 0;
}

function isAllowedToCopyFiles($username) {
  global $userindex, $config, $g;

  if ($username == "") { return 0; }

  return hasPrivilege($username, "copyfiles");
}

function hasLockAbility($username) {
  global $userindex, $config, $g;

  if ($username == "") { return 0; }

  return hasPrivilege($username, "lockwc");
}

function hasPageLockAbility($username) {
  global $userindex, $config, $g;

  if ($username == "") { return 0; }

  return hasPrivilege($username, "lock-ipages");
}

function hasShellAccess($username) {
  global $userindex, $config, $g;

  if ($username == "") { return 0; }

  return hasPrivilege($username, "hasshell");
}

function isUNIXRoot($username = "") {
  global $userindex, $config;

  if ($username == "") { return 0; }

  if (isSystemAdmin($username)) {
    return hasPrivilege($username, "isroot");
  }

  return 0;
}

function setUserFullName($name = "", $new_name = "") {
  global $config, $g, $userindex;

  if ($name == "" || $new_name == "") { return; }

  $user = &$config['system']['user'][$userindex[$name]];
  $user['fullname'] = $new_name;
}

function setUserName($name = "", $new_name = "") {
  global $config, $g, $userindex;

  if ($name == "" || $new_name == "") { return; }

  $user = &$config['system']['user'][$userindex[$name]];
  $user['name'] = $new_name;
}

function setUserPWD($name = "", $password = "") {
  global $config, $g, $userindex;

  if ($name == "" || $password == "") { return; }

  $user = &$config['system']['user'][$userindex[$name]];
  $user['password'] = crypt($password);
}

function setUserGroupName($name = "", $new_name = "") {
  global $config, $g, $userindex;

  if ($name == "" || $new_name == "") { return; }

  $user = &$config['system']['user'][$userindex[$name]];
  $user['groupname'] = $new_name;
}

function setUserType($name = "", $new_type = "") {
  global $config, $g, $userindex;

  if ($name == "" || $new_type == "") { return; }

  $user = &$config['system']['user'][$userindex[$name]];
  $user['scope'] = $new_type;
}

function getUNIXRoot() {
  global $config, $g, $userindex;

  if (is_array($config['system']['user'])) {
    foreach($config['system']['user'] as $user){
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

  if (is_array($config['system']['user'])) {
    foreach($config['system']['user'] as $user){
      if (isUNIXRoot($user['name'])) {
        return $user['name'];
      }
    }
  }

  return NULL;
}

function getGroupHomePage($group = "") {
  global $groupindex, $config, $g;

  if ($group == "") { return ""; }

  $page = $config['system']['group'][$groupindex[$group]]['home'];
  if(empty($page)) { $page = ""; }
  return $page;
}

function isSystemAdmin($username = "") {
  global $groupindex, $userindex, $config, $g, $_SESSION;

  if($_SESSION['isSystemAdmin']) 
	return $_SESSION['isSystemAdmin'];

  if(!function_exists("ldap_connect"))
	return;

  if($config['system']['webgui']['backend'] == "ldap") {
	$groups = ldap_get_groups($username);
	if(is_array($groups)){
		if(in_array("admins", $groups)) {
			$_SESSION['isSystemAdmin'] = true;
			return true;
		}
	}
  }
    if($config['system']['webgui']['backend'] == "ldapother") {
  	$groups = ldap_get_groups($username);
  	if(is_array($groups)){
  		if(in_array("admins", $groups)) {
  			$_SESSION['isSystemAdmin'] = true;
  			return true;
  		}
  	}
  }

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

  if ($username == "") { return ""; }

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
  } else {
    return $backing($HTTP_SERVER_VARS['AUTH_USER'],$HTTP_SERVER_VARS['AUTH_PW']);
  }
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
    } else {
      /* give the user a more detailed error message */
      if (isset($userindex[$_POST['usernamefld']])) {
        $_SESSION['Login_Error'] = "Wrong password";
        if(isAjax()) {
        	echo "showajaxmessage('Wrong password');";
        	return;        	
        }
      } else {
        $_SESSION['Login_Error'] = "User does not exist";
        if(isAjax()) {
        	echo "showajaxmessage('User does not exist');";
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
  } else {
    /* If session timeout isn't set, we don't mark sessions stale */
    if (!isset($config['system']['webgui']['session_timeout']) or
       $config['system']['webgui']['session_timeout'] == 0 or
       $config['system']['webgui']['session_timeout'] == "")
       $_SESSION['last_access'] = time();
     else
       /* Check for stale session */
       if ($_SESSION['last_access'] < (time() - ($config['system']['webgui']['session_timeout'] * 60)))
         $_GET['logout'] = true;
       else
         /* only update if it wasn't ajax */
         if (!isAjax())
           $_SESSION['last_access'] = time();

    /* user hit the logout button */
    if (isset($_GET['logout'])) {
      if (hasLockAbility($_SESSION['Username'])) {
        unlink_if_exists("{$g['tmp_path']}/webconfigurator.lock");
      }

      /* wipe out $_SESSION */
      $_SESSION = array();

      if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-42000, '/');
      }

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

    /* user wants to explicitely delete the log file.
     * Requires a particular privilege.
     */
    } else if ($_GET['deletelock'] && hasLockAbility($_SESSION['Username'])) {
      unlink_if_exists("{$g['tmp_path']}/webconfigurator.lock");
      $HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
      return true;

    /* this is for debugging purpose if you do not want to use Ajax
     * to submit a HTML form. It basically diables the observation
     * of the submit event and hence does not trigger Ajax.
     */
    } else if ($_GET['disable_ajax']) {
      $_SESSION['NO_AJAX'] = "True";
      $HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
      return true;

    /* Same to re-enable Ajax.
     */
    } else if ($_GET['enable_ajax']) {
      unset($_SESSION['NO_AJAX']);
      $HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
      return true;

    /* user wants to explicitely create a lock.
     * Requires a particular privilege.
     */
    } else if ($_GET['createlock'] && hasLockAbility($_SESSION['Username'])) {
      $fd = fopen("{$g['tmp_path']}/webconfigurator.lock", "w");
      fputs($fd, "{$_SERVER['REMOTE_ADDR']} (" .
                 getRealName($_SESSION['Username']) . ")");
      fclose($fd);
      /* if the user did delete the lock manually, do not
       * re-create it while the session is valide.
       */
      $_SESSION['Lock_Created'] = "True";
      $HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
      return true;

    /* proceed with the login process */
    } else {
      /* if the user is allowed to create a lock,
       * create it once per session.
       */
      if (hasLockAbility($_SESSION['Username']) &&
          ! isset($_SESSION['Lock_Created'])) {

        $fd = fopen("{$g['tmp_path']}/webconfigurator.lock", "w");
        fputs($fd, "{$_SERVER['REMOTE_ADDR']} (" .
                   getRealName($_SESSION['Username']) . ")");
        fclose($fd);
        /* if the user did delete the lock manually, do not
         * re-create it while the session is valide.
         */
        $_SESSION['Lock_Created'] = "True";

      /* give regular users a chance to automatically invalidate
       * a lock if its older than a particular time.
       */
      } else if (! hasLockAbility($_SESSION['Username']) &&
                 file_exists("{$g['tmp_path']}/webconfigurator.lock")) {

        $offset = 12; //hours
        $mtime = filemtime("{$g['tmp_path']}/webconfigurator.lock");
        $now_minus_offset = mktime(date("H") - $offset, 0, 0, date("m"), date("d"), date("Y"));

        if (($mtime - $now_minus_offset) < $mtime) {
          require_once("authgui.inc");
          display_login_form();
          return false;
        } else {
          /* file is older than mtime + offset which may
           * indicate a stale lockfile, hence we are going
           * to remove it.
           */
           unlink_if_exists("{$g['tmp_path']}/webconfigurator.lock");
        }
      }

      $HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
      return true;
    }
  }
}

function pam_backed($username = "", $password = "") {
  /* do not allow blank passwords */
  if ($username == "" || password == "") { return false; }

  if(! extension_loaded( 'pam_auth' )) {
    if(! @dl( 'pam_auth.so' )) {
      return false;
    } else {
      /* no php file no auth, sorry */
      if (! file_exists("/etc/pam.d/php")) {
        if (! file_exists("/etc/pam.d")) { mkdir("/etc/pam.d"); }

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
      } // end if

      if (pam_auth($username, $password, &$error)) {
        return true;
      } else {
        return false;
      }
    }
  }
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

  /* Encrypt entered password with salt
   * And finally validate password
   */
  if ($pass == crypt($passwd, $salt))
    return true;
  else
    return false;
}

function ldap_test_connection() {
	global $config, $g;

	$ldapserver = $config['system']['webgui']['ldapserver'];
	$ldapbindun = $config['system']['webgui']['ldapbindun'];
	$ldapbindpw = $config['system']['webgui']['ldapbindpw'];

	if (!($ldap = ldap_connect($ldapserver))) {
		return false;
	}

	return true;
}

function ldap_test_bind() {
	global $config, $g;

    $ldapserver = $config['system']['webgui']['ldapserver'];
    $ldapbindun = $config['system']['webgui']['ldapbindun'];
    $ldapbindpw = $config['system']['webgui']['ldapbindpw'];

    if (!($ldap = ldap_connect($ldapserver))) {
		return false;
    }

    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

    if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		return false;
    }

	return true;
}

function ldap_get_user_ous($show_complete_ou=false) {
	global $config, $g;

	if(!function_exists("ldap_connect"))
		return;

    $ldapserver = $config['system']['webgui']['ldapserver'];
    $ldapbindun = $config['system']['webgui']['ldapbindun'];
    $ldapbindpw = $config['system']['webgui']['ldapbindpw'];
	$ldapsearchbase = "{$config['system']['webgui']['ldapsearchbase']}";

    $ldapfilter = "(ou=*)";

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

	if(is_array($info)) {
	    foreach($info as $inf) {
			if(!$show_complete_ou) {
	            $inf_split = split(",", $inf['dn']);
	            $ou = $inf_split[0];
	            $ou = str_replace("OU=","", $ou);
			} else {
				if($inf['dn'])
					$ou = $inf['dn'];
			}
			if($ou)
				$ous[] = $ou;
	    }
	}
	
	$ous[] = "OU=Users," . $ldapsearchbase;
	
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
	
	log_error("Getting LDAP groups for {$username}.");
	
	$ldapserver = $config['system']['webgui']['ldapserver'];
	$ldapbindun = $config['system']['webgui']['ldapbindun'];
	$ldapbindpw = $config['system']['webgui']['ldapbindpw'];
	$ldapfilter = $config['system']['webgui']['ldapfilter'];
	$ldapsearchbase = "{$config['system']['webgui']['ldapsearchbase']}";
	$ldapfilter = str_replace("\$username", $username, $ldapfilter);
	$ldapauthcontainers = $config['system']['webgui']['ldapauthcontainers'];
	$ldapgroupattribute = $config['system']['webgui']['ldapgroupattribute'];
 
        /*Convert attribute to lowercase.  php ldap arrays put everything in lowercase */
        $ldapgroupattribute = strtolower($ldapgroupattribute);

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

	/* user specified login containers */
	$ldac_split = split(";", $ldapauthcontainers);
	$search = ldap_search($ldap, $ldapsearchbase, $ldapfilter);
	
	if(!$search) {
		log_error("ERROR!  Could not locate User group container for username {$username}.");
		log_error("        Please ensure that the needed container is defined in the ldap auth containers setting feature.");
		$status = htpasswd_backed($username, $passwd);
		return $status;	
	}
	
	$info = ldap_get_entries($ldap, $search);

	/* find home container */
    foreach($info as $inf) {
            $inf_split = split(",", $inf['dn']);
            $ou = $inf_split[1];
    }

	if(!$ou) {
		log_error("Could not resolve users home container for {$username}");
		$status = htpasswd_backed($username, $passwd);
		return $status;	
	}

    log_error("LDAPGroupAttribute ===== {$ldapgroupattribute}");
	$search = ldap_search($ldap, $ldapauthcontainers, $ldapfilter, array($ldapgroupattribute));

	if($search) {
		$info = ldap_get_entries($ldap, $search);
    	$countem = $info["count"];	
	}
	$memberof = array();
	
	log_error("USER HAS {$countem} LDAP Groups it is {$info[0][$ldapgroupattribute][0]}");

	if(is_array($info[0][$ldapgroupattribute])) {
    	foreach($info[0][$ldapgroupattribute] as $member) {
    	   //$member = strtoupper($member);
    	   log_error("MEMBER===={$member}");
            	if(stristr($member, "CN=") !== false) {
                    	$membersplit = split(",", $member);
                    	$memberof[] = preg_replace("/CN=/i", "", $membersplit[0]);
            	}
    	}
	}
	
	/* Time to close LDAP connection */
	ldap_close($ldap);
	
	$groups = print_r($memberof,true);
	
	log_error("Returning groups " . $groups  . " for user $username");
	
	return $memberof;
}

function ldap_backed($username, $passwd) {
	global $config;
	
	if(!$username) 
		return;

	if(!function_exists("ldap_connect"))
		return;

	$ldapserver = $config['system']['webgui']['ldapserver'];
	$ldapbindun = $config['system']['webgui']['ldapbindun'];
	$ldapbindpw = $config['system']['webgui']['ldapbindpw'];
        $ldapauthcont = $config['system']['webgui']['ldapauthcontainers'];   
        $ldapnameattribute = $config['system']['webgui']['ldapnameattribute'];     
    /* NEED TO FIGURE OUT LDAP TYPE */
    $ldaptype = $config['system']['webgui']['backend'];
    /********************************/

	if(!$ldapserver) {
		log_error("ERROR!  ldap_backed() backed selected with no LDAP authentication server defined.  Defaulting to built-in htpasswd_backed().     Visit System -> User Manager -> Settings.");
		$status = htpasswd_backed($username, $passwd);
		return $status;
	}
	
	if (!($ldap = ldap_connect($ldapserver))) {
	    log_error("ERROR!  ldap_backed() could not connect to server {$ldapserver} - {$ldapfilter}.  Defaulting to built-in htpasswd_backed().     Visit System -> User Manager -> Settings.");
		$status = htpasswd_backed($username, $passwd);
		return $status;	
	}
        /* TAKE AD INTO ACCOUNT */
	if ($ldaptype == 'ldap'){
	   $binduser = $username;
	}
	if ($ldaptype == 'ldapother'){
	   $binduser = $ldapnameattribute.'='.$username.','.$ldapauthcont;
	}
	log_error("BINDUSER ==== {$binduser}");
    	/************************/
	
	if (!($res = @ldap_bind($ldap, $binduser, $passwd))) {
	    log_error("ERROR!  ldap_backed() could not bind to {$ldapserver} - {$username} - {$passwd}.  Defaulting to built-in htpasswd_backed().    Visit System -> User Manager -> Settings.");
		$status = htpasswd_backed($username, $passwd);
		return $status;
	}
	
	log_error("$username logged in via LDAP.");
	log_error("$binduser ldap name logged in via LDAP.");
	/* At this point we are binded to LDAP so the user was auth'd okay. */
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
  else
    return false;
}

function radius_backed($username, $passwd){
  global $config, $debug;
  $ret = false;
  $radiusservers = $config['system']['radius']['servers'];

  $rauth = new Auth_RADIUS_PAP($username, $passwd);
  foreach ($radiusservers as $radsrv) {
    // Add a new server to our instance
    $rauth->addServer($radsrv['ipaddr'], $radsrv['port'], $radsrv['sharedsecret']);
  }

  if (!$rauth->start()) {
    $retvalue['auth_val'] = 1;
    $retvalue['error'] = $rauth->getError();
    if ($debug)
      printf("Radius start: %s<br>\n", $retvalue['error']);
  }

  // XXX - billm - somewhere in here we need to handle securid challenge/response

  // Send request
  $result = $rauth->send();
  if (PEAR::isError($result)) {
    $retvalue['auth_val'] = 1;
    $retvalue['error'] = $result->getMessage();
    if ($debug)
      printf("Radius send failed: %s<br>\n", $retvalue['error']);
  } else if ($result === true) {
    $retvalue['auth_val'] = 2;
    if ($debug)
      printf (gettext("Radius Auth succeeded") . "<br>\n");
    $ret = true;
  } else {
      $retvalue['auth_val'] = 3;
      if ($debug)
        printf (gettext("Radius Auth rejected") . "<br>\n");
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
