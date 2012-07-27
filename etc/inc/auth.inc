<?php
/* $Id$ */
/*
	Copyright (C) 2010 Ermal Luçi
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
		pfSense_BUILDER_BINARIES:	/usr/sbin/pw	/bin/cp
		pfSense_MODULE:	auth
*/

/*
 * NOTE : Portions of the mschapv2 support was based on the BSD licensed CHAP.php
 * file courtesy of Michael Retterklieber.
 */
if(!$do_not_include_config_gui_inc)
	require_once("config.gui.inc");

// Will be changed to false if security checks fail
$security_passed = true;

/* If this function doesn't exist, we're being called from Captive Portal or 
   another internal subsystem which does not include authgui.inc */
if (function_exists("display_error_form") && !isset($config['system']['webgui']['nodnsrebindcheck'])) {
	/* DNS ReBinding attack prevention.  http://redmine.pfsense.org/issues/708 */
	$found_host = false;
	if(strstr($_SERVER['HTTP_HOST'], ":")) {
		$http_host_port = explode(":", $_SERVER['HTTP_HOST']);
		$http_host = $http_host_port[0];
	} else {
		$http_host = $_SERVER['HTTP_HOST'];
	}
	if(is_ipaddr($http_host) or $_SERVER['SERVER_ADDR'] == "127.0.0.1" or
			strcasecmp($http_host, "localhost") == 0)
		$found_host = true;
	if(strcasecmp($http_host, $config['system']['hostname'] . "." . $config['system']['domain']) == 0 or
			strcasecmp($http_host, $config['system']['hostname']) == 0)
		$found_host = true;

	if(is_array($config['dyndnses']['dyndns']) && !$found_host)
		foreach($config['dyndnses']['dyndns'] as $dyndns)
			if(strcasecmp($dyndns['host'], $http_host) == 0) {
				$found_host = true;
				break;
			}

	if(!empty($config['system']['webgui']['althostnames']) && !$found_host) {
		$althosts = explode(" ", $config['system']['webgui']['althostnames']);
		foreach ($althosts as $ah)
			if(strcasecmp($ah, $http_host) == 0 or strcasecmp($ah, $_SERVER['SERVER_ADDR']) == 0) {
				$found_host = true;
				break;
			}
	}

	if($found_host == false) {
		if(!security_checks_disabled()) {
			display_error_form("501", "Potential DNS Rebind attack detected, see http://en.wikipedia.org/wiki/DNS_rebinding<br/>Try accessing the router by IP address instead of by hostname.");
			exit;
		}
		$security_passed = false;
	}
}

// If the HTTP_REFERER is something other than ourselves then disallow.
if(function_exists("display_error_form") && !isset($config['system']['webgui']['nohttpreferercheck'])) {
	if($_SERVER['HTTP_REFERER']) {
		if(file_exists("{$g['tmp_path']}/setupwizard_lastreferrer")) {
			if($_SERVER['HTTP_REFERER'] == file_get_contents("{$g['tmp_path']}/setupwizard_lastreferrer")) {
				unlink("{$g['tmp_path']}/setupwizard_lastreferrer");
				header("Refresh: 1; url=index.php");
				echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\"\n        \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">";
				echo "<html><head><title>" . gettext("Redirecting...") . "</title></head><body>" . gettext("Redirecting to the dashboard...") . "</body></html>";
				exit;
			}
		}
		$found_host = false;
		$referrer_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
		if($referrer_host) {
			if(strcasecmp($referrer_host, $config['system']['hostname'] . "." . $config['system']['domain']) == 0
					|| strcasecmp($referrer_host, $config['system']['hostname']) == 0)
				$found_host = true;
			if(!empty($config['system']['webgui']['althostnames']) && !$found_host) {
				$althosts = explode(" ", $config['system']['webgui']['althostnames']);
				foreach ($althosts as $ah) {
					if(strcasecmp($referrer_host, $ah) == 0) {
						$found_host = true;
						break;
					}
				}
			}
			if(!$found_host) {
				$interface_list_ips = get_configured_ip_addresses();
				foreach($interface_list_ips as $ilips) {
					if(strcasecmp($referrer_host, $ilips) == 0) {
						$found_host = true;
						break;
					}
				}
				if($referrer_host == "127.0.0.1" || $referrer_host == "localhost") {
					// allow SSH port forwarded connections and links from localhost
					$found_host = true;
				}
			}
		}
		if($found_host == false) {
			if(!security_checks_disabled()) {
				display_error_form("501", "An HTTP_REFERER was detected other than what is defined in System -> Advanced (" . htmlspecialchars($_SERVER['HTTP_REFERER']) . ").  You can disable this check if needed in System -> Advanced -> Admin.");
				exit;
			}
			$security_passed = false;
		}
	} else
		$security_passed = false;
}

if (function_exists("display_error_form") && $security_passed)
	/* Security checks passed, so it should be OK to turn them back on */
	restore_security_checks();
unset($security_passed);

$groupindex = index_groups();
$userindex = index_users();

function index_groups() {
	global $g, $debug, $config, $groupindex;

	$groupindex = array();

	if (is_array($config['system']['group'])) {
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

	if (is_array($config['system']['user'])) {
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

	if (is_array($config['system']['user']))
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

	if (is_array($config['system']['group']))
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

	if (is_account_disabled($username) || is_account_expired($username))
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
			if (((!strncmp($line[0], "_", 1)) || ($line[2] < 2000) || ($line[2] > 65000)) && ($line[0] != "admin"))
				continue;
			$cmd = "/usr/sbin/pw userdel -n '{$line[0]}'";
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

	if (empty($user['password'])) {
		log_error("There is something wrong in your config because user {$user['name']} password is missing!");
		return;
	}

	conf_mount_rw();

	$home_base = "/home/";	
	$user_uid = $user['uid'];
	$user_name = $user['name'];
	$user_home = "{$home_base}{$user_name}";
	$user_shell = "/etc/rc.initial";
	$user_group = "nobody";

	// Ensure $home_base exists and is writable
	if (!is_dir($home_base)) 
		mkdir($home_base, 0755);

	$lock_account = false;
	/* configure shell type */
	/* Cases here should be ordered by most privileged to least privileged. */
	if (userHasPrivilege($user, "user-shell-access") || userHasPrivilege($user, "page-all")) {
		$user_shell = "/bin/tcsh";
	} elseif (userHasPrivilege($user, "user-copy-files")) {
		$user_shell = "/usr/local/bin/scponly";
	} elseif (userHasPrivilege($user, "user-ssh-tunnel")) {
		$user_shell = "/usr/local/sbin/ssh_tunnel_shell";
	} elseif (userHasPrivilege($user, "user-ipsec-xauth-dialin")) {
		$user_shell = "/sbin/nologin";
	} else {
		$user_shell = "/sbin/nologin";
		$lock_account = true;
	}

	/* Lock out disabled or expired users, unless it's root/admin. */
	if ((is_account_disabled($user_name) || is_account_expired($user_name)) && ($user_uid != 0)) {
		$user_shell = "/sbin/nologin";
		$lock_account = true;
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
		$user_home = "/root";
		$user_shell = "/etc/rc.initial";
	}

	/* read from pw db */
	$fd = popen("/usr/sbin/pw usershow -n {$user_name} 2>&1", "r");
	$pwread = fgets($fd);
	pclose($fd);
	$userattrs = explode(":", trim($pwread));

	/* determine add or mod */
	if (($userattrs[0] != $user['name']) || (!strncmp($pwread, "pw:", 3))) {
		$user_op = "useradd -m -k /etc/skel -o";
	} else {
		$user_op = "usermod";
	}

	$comment = str_replace(array(":", "!", "@"), " ", $user['descr']); 
	/* add or mod pw db */
	$cmd = "/usr/sbin/pw {$user_op} -q -u {$user_uid} -n {$user_name}".
			" -g {$user_group} -s {$user_shell} -d {$user_home}".
			" -c ".escapeshellarg($comment)." -H 0 2>&1";

	if($debug)
		log_error("Running: {$cmd}");
	$fd = popen($cmd, "w");
	fwrite($fd, $user['password']);
	pclose($fd);

	/* create user directory if required */
	if (!is_dir($user_home)) {
		mkdir($user_home, 0700);
		mwexec("/bin/cp /root/.* {$home_base}/", true);
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
	} else
		unlink_if_exists("{$user_home}/.ssh/authorized_keys");

	$un = $lock_account ? "" : "un";
	exec("/usr/sbin/pw {$un}lock {$user_name} -q");
	
	conf_mount_ro();
}

function local_user_del($user) {
	global $debug;

	/* remove all memberships */
	local_user_set_groups($user);

	/* Don't remove /root */
	if ($user['uid'] != 0)
		$rmhome = "-r";

	/* read from pw db */
	$fd = popen("/usr/sbin/pw usershow -n {$user['name']} 2>&1", "r");
	$pwread = fgets($fd);
	pclose($fd);
	$userattrs = explode(":", trim($pwread));

	if ($userattrs[0] != $user['name']) {
		log_error("Tried to remove user {$user['name']} but got user {$userattrs[0]} instead. Bailing.");
		return;
	}

	/* delete from pw db */
	$cmd = "/usr/sbin/pw userdel -n {$user['name']} {$rmhome}";

	if($debug)
		log_error("Running: {$cmd}");
	mwexec($cmd);

	/* Delete user from groups needs a call to write_config() */
	local_group_del_user($user);
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

	if ( $all )
		$groups[] = "all";

	sort($groups);

	return $groups;
	
}

function local_user_set_groups($user, $new_groups = NULL ) {
	global $debug, $config, $groupindex;

	if (!is_array($config['system']['group']))
		return;

	$cur_groups = local_user_get_groups($user, true);
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
	unset($group);

	/* determine which memberships to remove */
	foreach ($cur_groups as $groupname) {
		if (in_array($groupname,$new_groups))
			continue;
		if (!isset($config['system']['group'][$groupindex[$groupname]]))
			continue;
		$group = & $config['system']['group'][$groupindex[$groupname]];
		if (is_array($group['member'])) {
			$index = array_search($user['uid'], $group['member']);
			array_splice($group['member'], $index, 1);
			$mod_groups[] = $group;
		}
	}
	unset($group);

	/* sync all modified groups */
	foreach ($mod_groups as $group)
		local_group_set($group);
}

function local_group_del_user($user) {
	global $config;

	if (!is_array($config['system']['group']))
                return;

        foreach ($config['system']['group'] as $group) {
		if (is_array($group['member'])) {
			foreach ($group['member'] as $idx => $uid) {
				if ($user['uid'] == $uid)
					unset($config['system']['group']['member'][$idx]);
			}
		}
	}
}

function local_group_set($group, $reset = false) {
	global $debug;

	$group_name = $group['name'];
	$group_gid = $group['gid'];
	$group_members = "''";
	if (!$reset && !empty($group['member']) && count($group['member']) > 0)
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
	mwexec($cmd);

}

function local_group_del($group) {
	global $debug;

	/* delete from group db */
	$cmd = "/usr/sbin/pw groupdel {$group['name']}";

	if($debug)
		log_error("Running: {$cmd}");
	mwexec($cmd);
}

function ldap_test_connection($authcfg) {
	global $debug, $config, $g;

	if ($authcfg) {
                if (strstr($authcfg['ldap_urltype'], "Standard"))
                        $ldapproto = "ldap";
                else
                        $ldapproto = "ldaps";
                $ldapserver         = "{$ldapproto}://{$authcfg['host']}";
                $ldapport           = $authcfg['ldap_port'];
		if (!empty($ldapport))
			$ldapserver .= ":{$ldapport}";
                $ldapbasedn         = $authcfg['ldap_basedn'];
                $ldapbindun         = $authcfg['ldap_binddn'];
                $ldapbindpw         = $authcfg['ldap_bindpw'];
        } else
		return false;

        /* first check if there is even an LDAP server populated */
        if(!$ldapserver)
                return false;

        /* Setup CA environment if needed. */
        ldap_setup_caenv($authcfg);

        /* connect and see if server is up */
        $error = false;
	if (!($ldap = ldap_connect($ldapserver)))
		$error = true;

        if ($error == true) {
                log_error("ERROR!  Could not connect to server {$ldapname}.");
                return false;
        }

	return true;
}

function ldap_setup_caenv($authcfg) {
	global $g;
	require_once("certs.inc");

	unset($caref);
	if (empty($authcfg['ldap_caref']) || !strstr($authcfg['ldap_urltype'], "SSL")) {
		putenv('LDAPTLS_REQCERT=never');
		return;
	} else {
		$caref = lookup_ca($authcfg['ldap_caref']);
		if (!$caref) {
			log_error(sprintf(gettext("LDAP: Could not lookup CA by reference for host %s."), $authcfg['ldap_caref']));
			/* XXX: Prevent for credential leaking since we cannot setup the CA env. Better way? */
			putenv('LDAPTLS_REQCERT=hard');
			return;
		}
		if (!is_dir("{$g['varrun_path']}/certs"))
			@mkdir("{$g['varrun_path']}/certs");
		if (file_exists("{$g['varrun_path']}/certs/{$authcfg['name']}.ca"))
			@unlink("{$g['varrun_path']}/certs/{$authcfg['name']}.ca");
		file_put_contents("{$g['varrun_path']}/certs/{$authcfg['name']}.ca", base64_decode($caref['crt']));
		@chmod("{$g['varrun_path']}/certs/{$authcfg['name']}.ca", 0600);
		putenv('LDAPTLS_REQCERT=hard');
		/* XXX: Probably even the hashed link should be created for this? */
		putenv("LDAPTLS_CACERTDIR={$g['varrun_path']}/certs");
		putenv("LDAPTLS_CACERT={$g['varrun_path']}/certs/{$authcfg['name']}.ca");
	}
}

function ldap_test_bind($authcfg) {
	global $debug, $config, $g;

	if ($authcfg) {
                if (strstr($authcfg['ldap_urltype'], "Standard"))
                        $ldapproto = "ldap";
                else
                        $ldapproto = "ldaps";
                $ldapserver         = "{$ldapproto}://{$authcfg['host']}";
                $ldapport           = $authcfg['ldap_port'];
		if (!empty($ldapport))
			$ldapserver .= ":{$ldapport}";
                $ldapbasedn         = $authcfg['ldap_basedn'];
                $ldapbindun         = $authcfg['ldap_binddn'];
                $ldapbindpw         = $authcfg['ldap_bindpw'];
                $ldapver            = $authcfg['ldap_protver'];
		if (empty($ldapbndun) || empty($ldapbindpw))
                        $ldapanon = true;
                else
                        $ldapanon = false;
	} else
		return false;

	/* first check if there is even an LDAP server populated */
        if(!$ldapserver)
                return false;

	/* Setup CA environment if needed. */
	ldap_setup_caenv($authcfg);

        /* connect and see if server is up */
        $error = false;
	if (!($ldap = ldap_connect($ldapserver)))
		$error = true;

        if ($error == true) {
                log_error("ERROR!  Could not connect to server {$ldapname}.");
                return false;
        }

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);
 
	if ($ldapanon == true) {
		if (!($res = @ldap_bind($ldap))) {
			@ldap_close($ldap);
			return false;
		}
	} else if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		@ldap_close($ldap);
		return false;
	}

	@ldap_unbind($ldap);

	return true;
}

function ldap_get_user_ous($show_complete_ou=true, $authcfg) {
	global $debug, $config, $g;

	if(!function_exists("ldap_connect"))
		return;

	$ous = array();

	if ($authcfg) {
                if (strstr($authcfg['ldap_urltype'], "Standard"))
                        $ldapproto = "ldap";
                else
                        $ldapproto = "ldaps";
                $ldapserver         = "{$ldapproto}://{$authcfg['host']}";
                $ldapport           = $authcfg['ldap_port'];
		if (!empty($ldapport))
			$ldapserver .= ":{$ldapport}";
                $ldapbasedn         = $authcfg['ldap_basedn'];
                $ldapbindun         = $authcfg['ldap_binddn'];
                $ldapbindpw         = $authcfg['ldap_bindpw'];
                $ldapver            = $authcfg['ldap_protver'];
		if (empty($ldapbindun) || empty($ldapbindpw))
                        $ldapanon = true;
                else
                        $ldapanon = false;
                $ldapname           = $authcfg['name'];
                $ldapfallback       = false;
		$ldapscope          = $authcfg['ldap_scope'];
        } else
		return false;

        /* first check if there is even an LDAP server populated */
        if(!$ldapserver) {
                log_error("ERROR!  ldap_get_user_ous() backed selected with no LDAP authentication server defined.");
                return $ous;
        }

	/* Setup CA environment if needed. */
	ldap_setup_caenv($authcfg);

	/* connect and see if server is up */
        $error = false;
	if (!($ldap = ldap_connect($ldapserver)))
		$error = true;

        if ($error == true) {
		log_error("ERROR!  Could not connect to server {$ldapname}.");
                return $ous;
        }

	$ldapfilter = "(|(ou=*)(cn=Users))";

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);

	if ($ldapanon == true) {
                if (!($res = @ldap_bind($ldap))) {
			log_error("ERROR! ldap_get_user_ous() could not bind anonymously to server {$ldapname}.");
			@ldap_close($ldap);
                        return $ous;
		}
	} else if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		log_error("ERROR! ldap_get_user_ous() could not bind to server {$ldapname}.");
		@ldap_close($ldap);
		return $ous;
	}

	if ($ldapscope == "one")
		$ldapfunc = "ldap_list";
	else
		$ldapfunc = "ldap_search";

	$search = @$ldapfunc($ldap, $ldapbasedn, $ldapfilter);
	$info = @ldap_get_entries($ldap, $search);

	if (is_array($info)) {
		foreach ($info as $inf) {
			if (!$show_complete_ou) {
				$inf_split = split(",", $inf['dn']);
				$ou = $inf_split[0];
				$ou = str_replace("OU=","", $ou);
				$ou = str_replace("CN=","", $ou);
			} else
				if($inf['dn'])
					$ou = $inf['dn'];
			if($ou)
				$ous[] = $ou;
		}
	}

	@ldap_unbind($ldap);

	return $ous;
}

function ldap_get_groups($username, $authcfg) {
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
        if ($authcfg) {
                if (strstr($authcfg['ldap_urltype'], "Standard"))
                        $ldapproto = "ldap";
                else
                        $ldapproto = "ldaps";
                $ldapserver         = "{$ldapproto}://{$authcfg['host']}";
                $ldapport           = $authcfg['ldap_port'];
		if (!empty($ldapport))
			$ldapserver .= ":{$ldapport}";
                $ldapbasedn         = $authcfg['ldap_basedn'];
                $ldapbindun         = $authcfg['ldap_binddn'];
                $ldapbindpw         = $authcfg['ldap_bindpw'];
                $ldapauthcont       = $authcfg['ldap_authcn'];
                $ldapnameattribute  = strtolower($authcfg['ldap_attr_user']);
                $ldapgroupattribute  = strtolower($authcfg['ldap_attr_member']);
                $ldapfilter         = "({$ldapnameattribute}={$username})";
                $ldaptype           = "";
                $ldapver            = $authcfg['ldap_protver'];
		if (empty($ldapbindun) || empty($ldapbindpw))
                        $ldapanon = true;
                else
                        $ldapanon = false;
                $ldapname           = $authcfg['name'];
                $ldapfallback       = false;
		$ldapscope          = $authcfg['ldap_scope'];
	} else
		return false;

	$ldapdn             = $_SESSION['ldapdn'];

	/*Convert attribute to lowercase.  php ldap arrays put everything in lowercase */
	$ldapgroupattribute = strtolower($ldapgroupattribute);
	$memberof = array();

        /* Setup CA environment if needed. */
        ldap_setup_caenv($authcfg);

	/* connect and see if server is up */
	$error = false;
	if (!($ldap = ldap_connect($ldapserver)))
		$error = true;

	if ($error == true) {
		log_error("ERROR! ldap_get_groups() Could not connect to server {$ldapname}.");
                return memberof;
        }
    
	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);

	/* bind as user that has rights to read group attributes */
	if ($ldapanon == true) {
                if (!($res = @ldap_bind($ldap))) {
			log_error("ERROR! ldap_get_groups() could not bind anonymously to server {$ldapname}.");
			@ldap_close($ldap);
                        return false;
		}
	} else if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		log_error("ERROR! ldap_get_groups() could not bind to server {$ldapname}.");
		@ldap_close($ldap);
		return memberof;
	}

	/* get groups from DN found */
	/* use ldap_read instead of search so we don't have to do a bunch of extra work */
	/* since we know the DN is in $_SESSION['ldapdn'] */
	//$search    = ldap_read($ldap, $ldapdn, "(objectclass=*)", array($ldapgroupattribute));
	if ($ldapscope == "one")
                $ldapfunc = "ldap_list";
        else
                $ldapfunc = "ldap_search";

	$search    = @$ldapfunc($ldap, $ldapdn, $ldapfilter, array($ldapgroupattribute));
	$info      = @ldap_get_entries($ldap, $search);

	$countem = $info["count"];	
	
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
	@ldap_unbind($ldap);
	
	$groups = print_r($memberof,true);
	
	//log_error("Returning groups ".$groups." for user $username");
	
	return $memberof;
}

function ldap_backed($username, $passwd, $authcfg) {
	global $debug, $config;
	
	if(!$username) 
		return;

	if(!function_exists("ldap_connect"))
		return;

	if(stristr($username, "@")) {
		$username_split=split("\@", $username);
		$username = $username_split[0];        
	}
	if(stristr($username, "\\")) {
		$username_split=split("\\", $username);
		$username = $username_split[0];        
	}

	if ($authcfg) {
		if (strstr($authcfg['ldap_urltype'], "Standard"))
			$ldapproto = "ldap";
		else
			$ldapproto = "ldaps";
		$ldapserver         = "{$ldapproto}://{$authcfg['host']}";
		$ldapport	    = $authcfg['ldap_port'];
		if (!empty($ldapport))
			$ldapserver .= ":{$ldapport}";
                $ldapbasedn         = $authcfg['ldap_basedn'];
                $ldapbindun         = $authcfg['ldap_binddn'];
                $ldapbindpw         = $authcfg['ldap_bindpw'];
		if (empty($ldapbindun) || empty($ldapbindpw))
			$ldapanon = true;
		else
			$ldapanon = false;
                $ldapauthcont       = $authcfg['ldap_authcn'];
                $ldapnameattribute  = strtolower($authcfg['ldap_attr_user']);
                $ldapfilter         = "({$ldapnameattribute}={$username})";
                $ldaptype           = "";
                $ldapver            = $authcfg['ldap_protver'];
		$ldapname	    = $authcfg['name'];
		$ldapscope	    = $authcfg['ldap_scope'];
	} else
		return false;

	/* first check if there is even an LDAP server populated */ 
	if(!$ldapserver) {
		if ($ldapfallback) {
			log_error("ERROR! ldap_backed() called with no LDAP authentication server defined.  Defaulting to local user database. Visit System -> User Manager.");
			return local_backed($username, $passwd);
		} else
			log_error("ERROR! ldap_backed() called with no LDAP authentication server defined.");

		return false;
	}
	
        /* Setup CA environment if needed. */
        ldap_setup_caenv($authcfg);

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);

	/* Make sure we can connect to LDAP */
	$error = false;
	if (!($ldap = ldap_connect($ldapserver)))
		$error = true;

	if ($error == true) {
		log_error("ERROR!  Could not connect to server {$ldapname}.");
		return false;
	}

	/* ok, its up.  now, lets bind as the bind user so we can search it */
	$error = false;
	if ($ldapanon == true) {
                if (!($res = @ldap_bind($ldap)))
                        $error = true;
	} else if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw)))
		$error = true;

	if ($error == true) {
		@ldap_close($ldap);
		log_error("ERROR! Could not bind to server {$ldapname}.");
		return false;
	}
	
	/* Get LDAP Authcontainers and split em up. */
	$ldac_splits = split(";", $ldapauthcont);
	
	/* setup the usercount so we think we havn't found anyone yet */
	$usercount  = 0;

	/*****************************************************************/
	/*  We First find the user based on username and filter          */
	/*  Then, once we find the first occurance of that person        */
	/*  We set seesion variables to ponit to the OU and DN of the    */
	/*  Person.  To later be used by ldap_get_groups.                */
	/*  that way we don't have to search twice.                      */
	/*****************************************************************/
	log_error("Now Searching for {$username} in directory.");
	/* Iterate through the user containers for search */
	foreach ($ldac_splits as $i => $ldac_split) {
		/* Make sure we just use the first user we find */
		log_error("Now Searching in server {$ldapname}, container {$ldac_split} with filter {$ldapfilter}.");
		if ($ldapscope == "one")
			$ldapfunc = "ldap_list";
		else
			$ldapfunc = "ldap_search";
		/* Support legacy auth container specification. */
		if (stristr($ldac_split, "DC=") || empty($ldapbasedn))
			$search	 = @$ldapfunc($ldap,$ldac_split,$ldapfilter);
		else
			$search  = @$ldapfunc($ldap,"{$ldac_split},{$ldapbasedn}",$ldapfilter);
		if (!$search) {
			log_error("Search resulted in error: " . ldap_error($ldap));
			continue;
		}
		$info	 = ldap_get_entries($ldap,$search);
		$matches = $info['count'];
		if ($matches == 1){
			$userdn = $_SESSION['ldapdn'] = $info[0]['dn'];
			$_SESSION['ldapou'] = $ldac_split[$i];
			$_SESSION['ldapon'] = "true";
			$usercount = 1;
			break;
		}
	}

	if ($usercount != 1){
		@ldap_unbind($ldap);
		log_error("ERROR! Either LDAP search failed, or multiple users were found.");
		return false;                         
	}

	/* Now lets bind as the user we found */
	if (!($res = @ldap_bind($ldap, $userdn, $passwd))) {
		log_error("ERROR! Could not login to server {$ldapname} as user {$username}.");
		@ldap_unbind($ldap);
		return false;
	}

	log_error("Logged in successfully as {$username} via LDAP server {$ldapname} with DN = {$userdn}.");

	/* At this point we are bound to LDAP so the user was auth'd okay. Close connection. */
	@ldap_unbind($ldap);

	return true;
}

function radius_backed($username, $passwd, $authcfg){
	global $debug, $config;
	$ret = false;

	require_once("radius.inc");

	$rauth = new Auth_RADIUS_PAP($username, $passwd);
	if ($authcfg) {
		$radiusservers = array();
		$radiusservers[0]['ipaddr'] = $authcfg['host'];
		$radiusservers[0]['port'] = $authcfg['radius_auth_port'];
		$radiusservers[0]['sharedsecret'] = $authcfg['radius_secret'];
	} else
		return false;

	/* Add a new servers to our instance */
	foreach ($radiusservers as $radsrv)
		$rauth->addServer($radsrv['ipaddr'], $radsrv['port'], $radsrv['sharedsecret']);

	if (PEAR::isError($rauth->start())) {
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
	$user = getUserEntry($username);
	if ($user['expires']) 
		return $user['expires'];
}

function is_account_expired($username) {
	$expirydate = get_user_expiration_date($username);
	if ($expirydate) {
		if (strtotime("-1 day") > strtotime(date("m/d/Y",strtotime($expirydate))))
			return true;
	}

	return false;
}

function is_account_disabled($username) {
	$user = getUserEntry($username);
	if (isset($user['disabled']))
		return true;

	return false;
}

function auth_get_authserver($name) {
        global $config;

        if (is_array($config['system']['authserver'])) {
                foreach ($config['system']['authserver'] as $authcfg) {
                        if ($authcfg['name'] == $name)
                                return $authcfg;
                }
        }
	if ($name == "Local Database")
		return array("name" => "Local Database", "type" => "Local Auth", "host" => $config['system']['hostname']);
}

function auth_get_authserver_list() {
        global $config;

	$list = array();

        if (is_array($config['system']['authserver'])) {
                foreach ($config['system']['authserver'] as $authcfg) {
			/* Add support for disabled entries? */
			$list[$authcfg['name']] = $authcfg;
                }
        }

	$list["Local Database"] = array( "name" => "Local Database", "type" => "Local Auth", "host" => $config['system']['hostname']);
	return $list;
}

function getUserGroups($username, $authcfg) {
	global $config;

	$allowed_groups = array();

	switch($authcfg['type']) {
        case 'ldap':
		$allowed_groups = @ldap_get_groups($username, $authcfg);
		break;
	case 'radius':
		break;
	default:
		$user = getUserEntry($username);
		$allowed_groups = @local_user_get_groups($user, true);
		break;
	}

	$member_groups = array();
        if (is_array($config['system']['group'])) {
                foreach ($config['system']['group'] as $group)
                        if (in_array($group['name'], $allowed_groups))
				$member_groups[] = $group['name'];
	}

	return $member_groups;
}

function authenticate_user($username, $password, $authcfg = NULL) {

	if (!$authcfg) {
		return local_backed($username, $password);
	}

	$authenticated = false;
	switch($authcfg['type']) {
        case 'ldap':
                if (ldap_backed($username, $password, $authcfg))
                        $authenticated = true;
                break;
        case 'radius':
                if (radius_backed($username, $password, $authcfg))
                        $authenticated = true;
                break;
        default:
                /* lookup user object by name */
                if (local_backed($username, $password))
                        $authenticated = true;
                break;
        }

	return $authenticated;
}

function session_auth() {
	global $HTTP_SERVER_VARS, $config, $_SESSION, $page;

	// Handle HTTPS httponly and secure flags
	if($config['system']['webgui']['protocol'] == "https") {
		$currentCookieParams = session_get_cookie_params();
		session_set_cookie_params(
			$currentCookieParams["lifetime"],
			$currentCookieParams["path"],
			NULL,
			true,
			true
		);
	}

	if (!session_id())
		session_start();

	/* Validate incoming login request */
	if (isset($_POST['login']) && !empty($_POST['usernamefld']) && !empty($_POST['passwordfld'])) {
		$authcfg = auth_get_authserver($config['system']['webgui']['authmode']);
		if (authenticate_user($_POST['usernamefld'], $_POST['passwordfld'], $authcfg) || 
		    authenticate_user($_POST['usernamefld'], $_POST['passwordfld'])) {
			$_SESSION['Logged_In'] = "True";
			$_SESSION['Username'] = $_POST['usernamefld'];
			$_SESSION['last_access'] = time();
			if(! isset($config['system']['webgui']['quietlogin'])) {
				log_auth("Successful webConfigurator login for user '{$_POST['usernamefld']}' from {$_SERVER['REMOTE_ADDR']}");
			}
			$HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
			if (isset($_POST['postafterlogin']))
				return true;
			else {
				if (empty($page))
					$page = "/";
				header("Location: {$page}");
			}
			exit;
		} else {
			/* give the user an error message */
			$_SESSION['Login_Error'] = "Username or Password incorrect";
			log_auth("webConfigurator authentication error for '{$_POST['usernamefld']}' from {$_SERVER['REMOTE_ADDR']}");
			if(isAjax()) {
				echo "showajaxmessage('{$_SESSION['Login_Error']}');";
				return;
			}
		}
	}

	/* Show login page if they aren't logged in */
	if (empty($_SESSION['Logged_In']))
		return false;

	/* If session timeout isn't set, we don't mark sessions stale */
	if (!isset($config['system']['webgui']['session_timeout'])) {
		/* Default to 4 hour timeout if one is not set */
		if ($_SESSION['last_access'] < (time() - 14400)) {
			$_GET['logout'] = true;
			$_SESSION['Logout'] = true;
		} else
			$_SESSION['last_access'] = time();	
	} else if (intval($config['system']['webgui']['session_timeout']) == 0) {
		/* only update if it wasn't ajax */
		if (!isAjax())
			$_SESSION['last_access'] = time();
	} else {
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
	if ($_GET['disable_ajax'])
		$_SESSION['NO_AJAX'] = "True";

	/*
	 * Same to re-enable Ajax.
	 */
	if ($_GET['enable_ajax'])
		unset($_SESSION['NO_AJAX']);

	$HTTP_SERVER_VARS['AUTH_USER'] = $_SESSION['Username'];
	return true;
}

?>
