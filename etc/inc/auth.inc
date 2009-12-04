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

		DISABLE_PHP_LINT_CHECKING
		pfSense_BUILDER_BINARIES:	/usr/sbin/pw	/bin/cp
		pfSense_MODULE:	auth
*/

/*
 * NOTE : Portions of the mschapv2 support was based on the BSD licensed CHAP.php
 * file courtesy of Michael Retterklieber.
 */

require_once("config.gui.inc");

$groupindex = index_groups();
$userindex = index_users();

function index_groups() {
	global $g, $debug, $config, $groupindex;

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
	global $g, $debug, $config;

	if (isset($config['system']['user'])) {
		$i = 0;
		foreach($config['system']['user'] as $userent) {
			$userindex[$userent['name']] = $i;
			$i++;
		}
	}

	return ($userindex);
}

function & getUserEntry($name) {
	global $debug, $config, $userindex;
	if (isset($userindex[$name]))
		return $config['system']['user'][$userindex[$name]];
}

function & getUserEntryByUID($uid) {
	global $debug, $config;
	foreach ($config['system']['user'] as & $user)
		if ($user['uid'] == $uid)
			return $user;

	return false;
}

function & getGroupEntry($name) {
	global $debug, $config, $groupindex;
	if (isset($groupindex[$name]))
		return $config['system']['group'][$groupindex[$name]];
}

function & getGroupEntryByGID($gid) {
	global $debug, $config;
	foreach ($config['system']['group'] as & $group)
		if ($group['gid'] == $gid)
			return $group;

	return false;
}

function get_user_privileges(& $user) {

        $privs = $user['priv'];
        if (!is_array($privs))
                $privs = array();

        $names = local_user_get_groups($user, true);

        foreach ($names as $name) {
                $group = getGroupEntry($name);
                if (is_array($group['priv']))
                        $privs = array_merge( $privs, $group['priv']);
        }

        return $privs;
}

function userHasPrivilege($userent, $privid = false) {

        if (!$privid || !is_array($userent))
                return false;

        $privs = get_user_privileges($userent);

        if (!is_array($privs))
                return false;

        if (!in_array($privid, $privs))
                return false;

        return true;
}

function local_backed($username, $passwd) {

	$user = getUserEntry($username);
	if (!$user)
		return false;

	if ($user['password'])
	{
		$passwd = crypt($passwd, $user['password']);
		if ($passwd == $user['password'])
			return true;
	}

	if ($user['md5-hash'])
	{
		$passwd = md5($passwd);
		if ($passwd == $user['md5-hash'])
			return true;
	}

	return false;
}

function local_sync_accounts() {
	global $debug, $config;
	conf_mount_rw();

	/* remove local users to avoid uid conflicts */
	$fd = popen("/usr/sbin/pw usershow -a", "r");
	if ($fd) {
		while (!feof($fd)) {
			$line = explode(":",fgets($fd));
			if (!strncmp($line[0], "_", 1))
				continue;
			if ($line[2] < 2000)
				continue;
			if ($line[2] > 65000)
				continue;
			$cmd = "/usr/sbin/pw userdel {$line[2]}";
			if($debug)
				log_error("Running: {$cmd}");
			mwexec($cmd);
		}
		pclose($fd);
	}

	/* remove local groups to avoid gid conflicts */
	$gids = array();
	$fd = popen("/usr/sbin/pw groupshow -a", "r");
	if ($fd) {
		while (!feof($fd)) {
			$line = explode(":",fgets($fd));
			if (!strncmp($line[0], "_", 1))
				continue;
			if ($line[2] < 2000)
				continue;
			if ($line[2] > 65000)
				continue;
			$cmd = "/usr/sbin/pw groupdel {$line[2]}";
			if($debug)
				log_error("Running: {$cmd}");
			mwexec($cmd);
		}
		pclose($fd);
	}

	/* make sure the all group exists */
	$allgrp = getGroupEntryByGID(1998);
	local_group_set($allgrp, true);

	/* sync all local users */
	if (is_array($config['system']['user']))
		foreach ($config['system']['user'] as $user)
			local_user_set($user);

	/* sync all local groups */
	if (is_array($config['system']['group']))
		foreach ($config['system']['group'] as $group)
			local_group_set($group);

	conf_mount_ro();

}

function local_user_set(& $user) {
	global $g, $debug;

	conf_mount_rw();

	$home_base = "/home/";	
	$user_uid = $user['uid'];
	$user_name = $user['name'];
	$user_home = "{$home_base}/$user_name";
	$user_shell = "/etc/rc.initial";
	$user_group = "nobody";

	// Ensure $home_base exists and is writable
	if (!is_dir($home_base)) 
		mkdir($home_base, 0755);

	// Ensure $user_home exists and is writable
	if(!is_dir($user_home)) 
		mkdir($user_home, 0755);

	/* configure shell type */
	if (!userHasPrivilege($user, "user-shell-access")) {
		if (!userHasPrivilege($user, "user-copy-files"))
			$user_shell = "/sbin/nologin";
		else
			$user_shell = "/usr/local/bin/scponly";
	}

	/* root user special handling */
	if ($user_uid == 0) {
		$cmd = "/usr/sbin/pw usermod -q -n root -s /bin/sh -H 0";
		if($debug)
			log_error("Running: {$cmd}");
		$fd = popen($cmd, "w");
		fwrite($fd, $user['password']);
		pclose($fd);
		$user_group = "wheel";
	}

	/* admin user special handling */
	if ($user_uid == 0) {
		$cmd = "/usr/sbin/pw usermod -q -n admin -s /bin/sh -H 0";
		if($debug)
			log_error("Running: {$cmd}");
		$fd = popen($cmd, "w");
		fwrite($fd, $user['password']);
		pclose($fd);
		$user_group = "wheel";
	}

	/* read from pw db */
	$fd = popen("/usr/sbin/pw usershow {$user_name} 2>&1", "r");
	$pwread = fgets($fd);
	pclose($fd);

	/* determine add or mod */
	if (!strncmp($pwread, "pw:", 3))
		$user_op = "useradd";
	else
		$user_op = "usermod";

	/* add or mod pw db */
	$cmd = "/usr/sbin/pw {$user_op} -q -u {$user_uid} -n {$user_name}".
			" -g {$user_group} -G all -s {$user_shell} -d {$user_home}".
			" -c ".escapeshellarg($user['fullname'])." -H 0 2>&1";

	if($debug)
		log_error("Running: {$cmd}");
	$fd = popen($cmd, "w");
	fwrite($fd, $user['password']);
	pclose($fd);

	/* create user directory if required */
	if (!is_dir($user_home)) {
		mkdir($user_home, 0700);
		mwexec("cp /root/.* {$home_base}/");
	}
	chown($user_home, $user_name);
	chgrp($user_home, $user_group);

	/* write out ssh authorized key file */
	if($user['authorizedkeys']) {
		if (!is_dir("{$user_home}/.ssh")) {
			mkdir("{$user_home}/.ssh", 0700);
			chown("{$user_home}/.ssh", $user_name);
		}
		$keys = base64_decode($user['authorizedkeys']);
		file_put_contents("{$user_home}/.ssh/authorized_keys", $keys);
		chown("{$user_home}/.ssh/authorized_keys", $user_name);
	}
	
	conf_mount_ro();
}

function local_user_del($user) {
	global $debug;

	/* remove all memberships */
	local_user_get_groups($user);

	/* delete from pw db */
	$cmd = "/usr/sbin/pw userdel {$user['name']}";

	if($debug)
		log_error("Running: {$cmd}");
	$fd = popen($cmd, "w");
	fwrite($fd, $user['password']);
	pclose($fd);

}

function local_user_set_password(& $user, $password) {

	$user['password'] = crypt($password);
	$user['md5-hash'] = md5($password);

	// Converts ascii to unicode.
	$astr = (string) $password;
	$ustr = '';
	for ($i = 0; $i < strlen($astr); $i++) {
		$a = ord($astr{$i}) << 8;
		$ustr.= sprintf("%X", $a);
	}

	// Generate the NT-HASH from the unicode string
	$user['nt-hash'] = bin2hex(mhash(MHASH_MD4, $ustr));
}

function local_user_get_groups($user, $all = false) {
	global $debug, $config;

	$groups = array();
	if (!is_array($config['system']['group']))
		return $groups;

	foreach ($config['system']['group'] as $group)
		if ( $all || ( !$all && ($group['name'] != "all")))
			if (is_array($group['member']))
				if (in_array($user['uid'], $group['member']))
					$groups[] = $group['name'];

	sort($groups);

	return $groups;
	
}

function local_user_set_groups($user, $new_groups = NULL ) {
	global $debug, $config, $groupindex;

	if (!is_array($config['system']['group']))
		return;

	$cur_groups = local_user_get_groups($user);
	$mod_groups = array();

	if (!is_array($new_groups))
		$new_groups = array();

	if (!is_array($cur_groups))
		$cur_groups = array();

	/* determine which memberships to add */
	foreach ($new_groups as $groupname) {
		if (in_array($groupname,$cur_groups))
			continue;
		$group = & $config['system']['group'][$groupindex[$groupname]];
		$group['member'][] = $user['uid'];
		$mod_groups[] = $group;
	}

	/* determine which memberships to remove */
	foreach ($cur_groups as $groupname) {
		if (in_array($groupname,$new_groups))
		continue;
		$group = & $config['system']['group'][$groupindex[$groupname]];
		$index = array_search($user['uid'], $group['member']);
		array_splice($group['member'], $index, 1);
		$mod_groups[] = $group;
	}

	/* sync all modified groups */
	foreach ($mod_groups as $group)
		local_group_set($group);
}

function local_group_set($group, $reset = false) {
	global $debug;

	$group_name = $group['name'];
	$group_gid = $group['gid'];
	$group_members = "''";
	if (!$reset && count($group['member']))
		$group_members = implode(",",$group['member']);

	/* read from group db */
	$fd = popen("/usr/sbin/pw groupshow {$group_name} 2>&1", "r");
	$pwread = fgets($fd);
	pclose($fd);

	/* determine add or mod */
	if (!strncmp($pwread, "pw:", 3))
		$group_op = "groupadd";
	else
		$group_op = "groupmod";

	/* add or mod group db */
	$cmd = "/usr/sbin/pw {$group_op} {$group_name} -g {$group_gid} -M {$group_members} 2>&1";

	if($debug)
		log_error("Running: {$cmd}");
	$fd = popen($cmd, "w");
	fwrite($fd, $user['password']);
	pclose($fd);

}

function local_group_del($group) {
	global $debug;

	/* delete from group db */
	$cmd = "/usr/sbin/pw groupdel {$group['name']}";

	if($debug)
		log_error("Running: {$cmd}");
	$fd = popen($cmd, "w");
	fwrite($fd, $user['password']);
	pclose($fd);

}

function ldap_test_connection() {
	global $debug, $config, $g;

	$ldapserver = $config['system']['webgui']['ldapserver'];
	$ldapbindun = $config['system']['webgui']['ldapbindun'];
	$ldapbindpw = $config['system']['webgui']['ldapbindpw'];

	if (!($ldap = ldap_connect($ldapserver)))
		return false;

	return true;
}

function ldap_test_bind() {
	global $debug, $config, $g;

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
	global $debug, $config, $g;

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
		log_error("ERROR!  ldap_get_groups() could not connect to server {$ldapserver}.  Defaulting to built-in local_backed()");
		$status = local_backed($username, $passwd);
		return $status;
	}

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

	if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		log_error("ERROR! ldap_get_groups() could not bind to {$ldapserver} - {$ldapfilter}.  Defaulting to built-in local_backed()");
		$status = local_backed($username, $passwd);
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
	global $debug, $config;
	
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
		log_error("ERROR!  ldap_get_groups() could not connect to server {$ldapserver}.  Defaulting to built-in local_backed()");
		$status = local_backed($username, $passwd);
		return $status;	
	}
    
	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

	/* bind as user that has rights to read group attributes */
	if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		log_error("ERROR! ldap_get_groups() could not bind to {$ldapserver} - {$ldapfilter}.  Defaulting to built-in local_backed()");
		$status = local_backed($username, $passwd);
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
	global $debug, $config;
	
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
		log_error("ERROR!  ldap_backed() backed selected with no LDAP authentication server defined.  Defaulting to built-in local_backed().     Visit System -> User Manager -> Settings.");
		$status = local_backed($username, $passwd);
		return $status;
	}
	
	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

	/* Make sure we can connect to LDAP */
	putenv('LDAPTLS_REQCERT=never');
	if (!($ldap = ldap_connect($ldapserver))) {
		log_error("ERROR!  ldap_backed() could not connect to server {$ldapserver} - {$ldapfilter}.  Defaulting to built-in local_backed().     Visit System -> User Manager -> Settings.");
		$status = local_backed($username, $passwd);		
		return $status;	
	}
	/* ok, its up.  now, lets bind as the bind user so we can search it */
	if (!($res = ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		log_error("ERROR! ldap_backed() could not bind to {$ldapserver} - {$ldapfilter}.  Defaulting to built-in local_backed()");
		ldap_close($ldap);
		$status = local_backed($username, $passwd);
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
			$status = local_backed($username, $passwd);
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
			$status = local_backed($username, $passwd);
			ldap_close($ldap);
			$_SESSION['ldapon'] = "false";
			return $status;                         
		}
	}
	
	/* Now lets bind as the user we found */
	if (!($res = @ldap_bind($ldap, $binduser, $passwd))) {
		log_error("ERROR!  ldap_backed() could not bind to {$ldapserver} - {$username} - {$passwd}.  Defaulting to built-in local_backed().    Visit System -> User Manager -> Settings.");
		$status = local_backed($username, $passwd);
		return $status;
	}

	log_error("$binduser succesfully logged in via LDAP.");

	/* At this point we are bound to LDAP so the user was auth'd okay. */
	return true;
}

function radius_backed($username, $passwd){
	global $debug, $config, $debug;
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

function get_user_expiration_date($username) {
	global $config;
	foreach($config['system']['user']  as $user) {
		if($user['name'] == $username) {
			if($user['expires']) 
				return $user['expires'];
		}
	}
}

function is_account_disabled($username) {
	global $config;
	foreach($config['system']['user'] as $user)
		if($user['name'] == $username) 
			if(isset($user['disabled'])) 
				return true;
	return false;
}

function session_auth($backing) {
	global $g, $debug, $HTTP_SERVER_VARS, $userindex, $config, $_SESSION;

	session_start();

	/* Validate incoming login request */
	if (isset($_POST['login'])) {
		if ($backing($_POST['usernamefld'], $_POST['passwordfld'])) {
			$acct_expires = get_user_expiration_date($_POST['usernamefld']);
			if($acct_expires) {
				if (strtotime("-1 day") > strtotime(date("m/d/Y",strtotime($acct_expires)))) {
					log_error("Attempted login for invalid user '{$_POST['usernamefld']}' from: {$_SERVER['REMOTE_ADDR']}");
					if(isAjax()) {
						echo "showajaxmessage('{$_SESSION['Login_Error']}');";
						return;
					}
				}
			} else {
				if(is_account_disabled($_POST['usernamefld'])) {
					log_error("Attempted login for invalid user '{$_POST['usernamefld']}' from: {$_SERVER['REMOTE_ADDR']}");
					if(isAjax()) {
						echo "showajaxmessage('{$_SESSION['Login_Error']}');";
						return;
					}
				} else {
					$_SESSION['Logged_In'] = "True";
					$_SESSION['Username'] = $_POST['usernamefld'];
					$_SESSION['last_access'] = time();
					log_error("Successful login for user '{$_POST['usernamefld']}' from: {$_SERVER['REMOTE_ADDR']}");
				}
			}
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

	/* obtain user object */
	$user = getUserEntry($_SESSION['Username']);

	/* user hit the logout button */
	if (isset($_GET['logout'])) {

		if ($_SESSION['Logout'])
			log_error("Session timed out for user '{$_SESSION['Username']}' from: {$_SERVER['REMOTE_ADDR']}");
		else
			log_error("User logged out for user '{$_SESSION['Username']}' from: {$_SERVER['REMOTE_ADDR']}");

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
		Header("Location: {$scriptName}");

		return false;
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

	$HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
	return true;
}

?>
