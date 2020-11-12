<?php
/*
 * auth.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>
 * Copyright (c) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (c) 2006 Paul Taylor <paultaylor@winn-dixie.com>
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

/*
 * NOTE : Portions of the mschapv2 support was based on the BSD licensed CHAP.php
 * file courtesy of Michael Retterklieber.
 */
include_once('phpsessionmanager.inc');
if (!$do_not_include_config_gui_inc) {
	require_once("config.gui.inc");
}

// Will be changed to false if security checks fail
$security_passed = true;

/* If this function doesn't exist, we're being called from Captive Portal or
   another internal subsystem which does not include authgui.inc */
if (function_exists("display_error_form")) {
	/* Extra layer of lockout protection. Check if the user is in the GUI
	 * lockout table before processing a request */

	/* Fetch the contents of the lockout table. */
	$entries = array();
	exec("/sbin/pfctl -t 'sshguard' -T show", $entries);

	/* If the client is in the lockout table, print an error, kill states, and exit */
	if (in_array($_SERVER['REMOTE_ADDR'], array_map('trim', $entries))) {
		if (!security_checks_disabled()) {
			/* They may never see the error since the connection will be cut off, but try to be nice anyhow. */
			display_error_form("501", gettext("Access Denied<br/><br/>Access attempt from a temporarily locked out client address.<br /><br />Try accessing the firewall again after the lockout expires."));
			/* If they are locked out, they shouldn't have a state. Disconnect their connections. */
			$retval = pfSense_kill_states($_SERVER['REMOTE_ADDR']);
			if (is_ipaddrv4($_SERVER['REMOTE_ADDR'])) {
				$retval = pfSense_kill_states("0.0.0.0/0", $_SERVER['REMOTE_ADDR']);
			} elseif (is_ipaddrv6($_SERVER['REMOTE_ADDR'])) {
				$retval = pfSense_kill_states("::", $_SERVER['REMOTE_ADDR']);
			}
			exit;
		}
		$security_passed = false;
	}
}

if (function_exists("display_error_form") && !isset($config['system']['webgui']['nodnsrebindcheck'])) {
	/* DNS ReBinding attack prevention.  https://redmine.pfsense.org/issues/708 */
	$found_host = false;

	/* Either a IPv6 address with or without a alternate port */
	if (strstr($_SERVER['HTTP_HOST'], "]")) {
		$http_host_port = explode("]", $_SERVER['HTTP_HOST']);
		/* v6 address has more parts, drop the last part */
		if (count($http_host_port) > 1) {
			array_pop($http_host_port);
			$http_host = str_replace(array("[", "]"), "", implode(":", $http_host_port));
		} else {
			$http_host = str_replace(array("[", "]"), "", implode(":", $http_host_port));
		}
	} else {
		$http_host = explode(":", $_SERVER['HTTP_HOST']);
		$http_host = $http_host[0];
	}
	if (is_ipaddr($http_host) or $_SERVER['SERVER_ADDR'] == "127.0.0.1" or
		strcasecmp($http_host, "localhost") == 0 or $_SERVER['SERVER_ADDR'] == "::1") {
		$found_host = true;
	}
	if (strcasecmp($http_host, $config['system']['hostname'] . "." . $config['system']['domain']) == 0 or
		strcasecmp($http_host, $config['system']['hostname']) == 0) {
		$found_host = true;
	}

	if (is_array($config['dyndnses']['dyndns']) && !$found_host) {
		foreach ($config['dyndnses']['dyndns'] as $dyndns) {
			if (strcasecmp($dyndns['host'], $http_host) == 0) {
				$found_host = true;
				break;
			}
		}
	}

	if (is_array($config['dnsupdates']['dnsupdate']) && !$found_host) {
		foreach ($config['dnsupdates']['dnsupdate'] as $rfc2136) {
			if (strcasecmp($rfc2136['host'], $http_host) == 0) {
				$found_host = true;
				break;
			}
		}
	}

	if (!empty($config['system']['webgui']['althostnames']) && !$found_host) {
		$althosts = explode(" ", $config['system']['webgui']['althostnames']);
		foreach ($althosts as $ah) {
			if (strcasecmp($ah, $http_host) == 0 or strcasecmp($ah, $_SERVER['SERVER_ADDR']) == 0) {
				$found_host = true;
				break;
			}
		}
	}

	if ($found_host == false) {
		if (!security_checks_disabled()) {
			display_error_form("501", gettext("Potential DNS Rebind attack detected, see http://en.wikipedia.org/wiki/DNS_rebinding<br />Try accessing the router by IP address instead of by hostname."));
			exit;
		}
		$security_passed = false;
	}
}

// If the HTTP_REFERER is something other than ourselves then disallow.
if (function_exists("display_error_form") && !isset($config['system']['webgui']['nohttpreferercheck'])) {
	if ($_SERVER['HTTP_REFERER']) {
		if (file_exists("{$g['tmp_path']}/setupwizard_lastreferrer")) {
			if ($_SERVER['HTTP_REFERER'] == file_get_contents("{$g['tmp_path']}/setupwizard_lastreferrer")) {
				unlink("{$g['tmp_path']}/setupwizard_lastreferrer");
				header("Refresh: 1; url=index.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<link rel="stylesheet" href="/css/pfSense.css" />
	<title><?=gettext("Redirecting..."); ?></title>
</head>
<body id="error" class="no-menu">
	<div id="jumbotron">
		<div class="container">
			<div class="col-sm-offset-3 col-sm-6 col-xs-12">
				<p><?=gettext("Redirecting to the dashboard...")?></p>
			</div>
		</div>
	</div>
</body>
</html>
<?php
				exit;
			}
		}
		$found_host = false;
		$referrer_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
		$referrer_host = str_replace(array("[", "]"), "", $referrer_host);
		if ($referrer_host) {
			if (strcasecmp($referrer_host, $config['system']['hostname'] . "." . $config['system']['domain']) == 0 ||
			    strcasecmp($referrer_host, $config['system']['hostname']) == 0) {
				$found_host = true;
			}

			if (!empty($config['system']['webgui']['althostnames']) && !$found_host) {
				$althosts = explode(" ", $config['system']['webgui']['althostnames']);
				foreach ($althosts as $ah) {
					if (strcasecmp($referrer_host, $ah) == 0) {
						$found_host = true;
						break;
					}
				}
			}

			if (is_array($config['dyndnses']['dyndns']) && !$found_host) {
				foreach ($config['dyndnses']['dyndns'] as $dyndns) {
					if (strcasecmp($dyndns['host'], $referrer_host) == 0) {
						$found_host = true;
						break;
					}
				}
			}

			if (is_array($config['dnsupdates']['dnsupdate']) && !$found_host) {
				foreach ($config['dnsupdates']['dnsupdate'] as $rfc2136) {
					if (strcasecmp($rfc2136['host'], $referrer_host) == 0) {
						$found_host = true;
						break;
					}
				}
			}

			if (!$found_host) {
				$interface_list_ips = get_configured_ip_addresses();
				foreach ($interface_list_ips as $ilips) {
					if (strcasecmp($referrer_host, $ilips) == 0) {
						$found_host = true;
						break;
					}
				}
				$interface_list_ipv6s = get_configured_ipv6_addresses(true);
				foreach ($interface_list_ipv6s as $ilipv6s) {
					$ilipv6s = explode('%', $ilipv6s)[0];
					if (strcasecmp($referrer_host, $ilipv6s) == 0) {
						$found_host = true;
						break;
					}
				}
				if ($referrer_host == "127.0.0.1" || $referrer_host == "localhost") {
					// allow SSH port forwarded connections and links from localhost
					$found_host = true;
				}
			}

			/* Fall back to probing active interface addresses rather than config.xml to allow
			 * changed addresses that have not yet been applied.
			 * See https://redmine.pfsense.org/issues/8822
			 */
			if (!$found_host) {
				$refifs = get_interface_arr();
				foreach ($refifs as $rif) {
					if (($referrer_host == find_interface_ip($rif)) ||
					    ($referrer_host == find_interface_ipv6($rif)) ||
					    ($referrer_host == find_interface_ipv6_ll($rif))) {
						$found_host = true;
						break;
					}
				}
			}
		}
		if ($found_host == false) {
			if (!security_checks_disabled()) {
				display_error_form("501", "An HTTP_REFERER was detected other than what is defined in System -> Advanced (" . htmlspecialchars($_SERVER['HTTP_REFERER']) . ").  If not needed, this check can be disabled in System -> Advanced -> Admin.");
				exit;
			}
			$security_passed = false;
		}
	} else {
		$security_passed = false;
	}
}

if (function_exists("display_error_form") && $security_passed) {
	/* Security checks passed, so it should be OK to turn them back on */
	restore_security_checks();
}
unset($security_passed);

$groupindex = index_groups();
$userindex = index_users();

function index_groups() {
	global $g, $debug, $config, $groupindex;

	$groupindex = array();

	if (is_array($config['system']['group'])) {
		$i = 0;
		foreach ($config['system']['group'] as $groupent) {
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
		foreach ($config['system']['user'] as $userent) {
			$userindex[$userent['name']] = $i;
			$i++;
		}
	}

	return ($userindex);
}

function & getUserEntry($name) {
	global $debug, $config, $userindex;
	$authcfg = auth_get_authserver($config['system']['webgui']['authmode']);

	if (isset($userindex[$name])) {
		return $config['system']['user'][$userindex[$name]];
	} elseif ($authcfg['type'] != "Local Database") {
		$user = array();
		$user['name'] = $name;
		return $user;
	}
}

function & getUserEntryByUID($uid) {
	global $debug, $config;

	if (is_array($config['system']['user'])) {
		foreach ($config['system']['user'] as & $user) {
			if ($user['uid'] == $uid) {
				return $user;
			}
		}
	}

	return false;
}

function & getGroupEntry($name) {
	global $debug, $config, $groupindex;
	if (isset($groupindex[$name])) {
		return $config['system']['group'][$groupindex[$name]];
	}
}

function & getGroupEntryByGID($gid) {
	global $debug, $config;

	if (is_array($config['system']['group'])) {
		foreach ($config['system']['group'] as & $group) {
			if ($group['gid'] == $gid) {
				return $group;
			}
		}
	}

	return false;
}

function get_user_privileges(& $user) {
	global $config, $_SESSION;

	$authcfg = auth_get_authserver($config['system']['webgui']['authmode']);
	$allowed_groups = array();

	$privs = $user['priv'];
	if (!is_array($privs)) {
		$privs = array();
	}

	// cache auth results for a short time to ease load on auth services & logs
	if (isset($config['system']['webgui']['auth_refresh_time'])) {
		$recheck_time = $config['system']['webgui']['auth_refresh_time'];
	} else {
		$recheck_time = 30;
	}

	if ($authcfg['type'] == "ldap") {
		if (isset($_SESSION["ldap_allowed_groups"]) &&
		    (time() <= $_SESSION["auth_check_time"] + $recheck_time)) {
			$allowed_groups = $_SESSION["ldap_allowed_groups"];
		} else {
			$allowed_groups = @ldap_get_groups($user['name'], $authcfg);
			$_SESSION["ldap_allowed_groups"] = $allowed_groups;
			$_SESSION["auth_check_time"] = time();
		}
	} elseif ($authcfg['type'] == "radius") {
		if (isset($_SESSION["radius_allowed_groups"]) &&
		    (time() <= $_SESSION["auth_check_time"] + $recheck_time)) {
			$allowed_groups = $_SESSION["radius_allowed_groups"];
		} else {
			$allowed_groups = @radius_get_groups($_SESSION['user_radius_attributes']);
			$_SESSION["radius_allowed_groups"] = $allowed_groups;
			$_SESSION["auth_check_time"] = time();
		}
	}

	if (empty($allowed_groups)) {
		$allowed_groups = local_user_get_groups($user, true);
	}

	if (!is_array($allowed_groups)) {
		$allowed_groups = array('all');
	} else {
		$allowed_groups[] = 'all';
	}

	foreach ($allowed_groups as $name) {
		$group = getGroupEntry($name);
		if (is_array($group['priv'])) {
			$privs = array_merge($privs, $group['priv']);
		}
	}

	return $privs;
}

function userHasPrivilege($userent, $privid = false) {
	global $config;

	if (!$privid || !is_array($userent)) {
		return false;
	}

	$privs = get_user_privileges($userent);

	if (!is_array($privs)) {
		return false;
	}

	if (!in_array($privid, $privs)) {
		return false;
	}

	/* If someone is in admins group or is admin, do not honor the
	 * user-config-readonly privilege to prevent foot-shooting due to a
	 * bad privilege config.
	 * https://redmine.pfsense.org/issues/10492 */
	$userGroups = getUserGroups($userent['name'],
			auth_get_authserver($config['system']['webgui']['authmode']),
			$_SESSION['user_radius_attributes']);
	if (($privid == 'user-config-readonly') &&
	    (($userent['uid'] === "0") || (in_array('admins', $userGroups)))) {
		return false;
	}

	return true;
}

function local_backed($username, $passwd) {

	$user = getUserEntry($username);
	if (!$user) {
		return false;
	}

	if (is_account_disabled($username) || is_account_expired($username)) {
		return false;
	}

	if ($user['bcrypt-hash']) {
		if (password_verify($passwd, $user['bcrypt-hash'])) {
			return true;
		}
	}

	//for backwards compatibility
	if ($user['password']) {
		if (crypt($passwd, $user['password']) == $user['password']) {
			return true;
		}
	}

	if ($user['md5-hash']) {
		if (md5($passwd) == $user['md5-hash']) {
			return true;
		}
	}

	return false;
}

function local_sync_accounts($u2add, $u2del, $g2add, $g2del) {
	global $config, $debug;

	if (empty($u2add) && empty($u2del) && empty($g2add) && empty($g2del)) {
		/* Nothing to be done here */
		return;
	}

	foreach($u2del as $user) {
		if ($user['uid'] > 65000) {
			continue;
		} else if ($user['uid'] < 2000 && !in_array($user, $u2add)) {
			continue;
		}

		/*
		 * If a crontab was created to user, pw userdel will be
		 * interactive and can cause issues. Just remove crontab
		 * before run it when necessary
		 */
		unlink_if_exists("/var/cron/tabs/{$user['name']}");
		$cmd = "/usr/sbin/pw userdel -n " .
		    escapeshellarg($user['name']);
		if ($debug) {
			log_error(sprintf(gettext("Running: %s"), $cmd));
		}
		mwexec($cmd);
		local_group_del_user($user);

		$system_user = $config['system']['user'];
		for ($i = 0; $i < count($system_user); $i++) {
			if ($system_user[$i]['name'] == $user['name']) {
				log_error("Removing user: {$user['name']}");
				unset($config['system']['user'][$i]);
				break;
			}
		}
	}

	foreach($g2del as $group) {
		if ($group['gid'] < 1999 || $group['gid'] > 65000) {
			continue;
		}

		$cmd = "/usr/sbin/pw groupdel -g " .
		    escapeshellarg($group['name']);
		if ($debug) {
			log_error(sprintf(gettext("Running: %s"), $cmd));
		}
		mwexec($cmd);

		$system_group = $config['system']['group'];
		for ($i = 0; $i < count($system_group); $i++) {
			if ($system_group[$i]['name'] == $group['name']) {
				log_error("Removing group: {$group['name']}");
				unset($config['system']['group'][$i]);
				break;
			}
		}
	}

	foreach ($u2add as $user) {
		log_error("Adding user: {$user['name']}");
		$config['system']['user'][] = $user;
	}

	foreach ($g2add as $group) {
		log_error("Adding group: {$group['name']}");
		$config['system']['group'][] = $group;
	}

	/* Sort it alphabetically */
	usort($config['system']['user'], function($a, $b) {
		return strcmp($a['name'], $b['name']);
	});
	usort($config['system']['group'], function($a, $b) {
		return strcmp($a['name'], $b['name']);
	});

	write_config("Sync'd users and groups via XMLRPC");

	/* make sure the all group exists */
	$allgrp = getGroupEntryByGID(1998);
	local_group_set($allgrp, true);

	foreach ($u2add as $user) {
		local_user_set($user);
	}

	foreach ($g2add as $group) {
		local_group_set($group);
	}
}

function local_reset_accounts() {
	global $debug, $config;

	/* remove local users to avoid uid conflicts */
	$fd = popen("/usr/sbin/pw usershow -a", "r");
	if ($fd) {
		while (!feof($fd)) {
			$line = explode(":", fgets($fd));
			if ($line[0] != "admin") {
				if (!strncmp($line[0], "_", 1)) {
					continue;
				}
				if ($line[2] < 2000) {
					continue;
				}
				if ($line[2] > 65000) {
					continue;
				}
			}
			/*
			 * If a crontab was created to user, pw userdel will be interactive and
			 * can cause issues. Just remove crontab before run it when necessary
			 */
			unlink_if_exists("/var/cron/tabs/{$line[0]}");
			$cmd = "/usr/sbin/pw userdel -n " . escapeshellarg($line[0]);
			if ($debug) {
				log_error(sprintf(gettext("Running: %s"), $cmd));
			}
			mwexec($cmd);
		}
		pclose($fd);
	}

	/* remove local groups to avoid gid conflicts */
	$gids = array();
	$fd = popen("/usr/sbin/pw groupshow -a", "r");
	if ($fd) {
		while (!feof($fd)) {
			$line = explode(":", fgets($fd));
			if (!strncmp($line[0], "_", 1)) {
				continue;
			}
			if ($line[2] < 2000) {
				continue;
			}
			if ($line[2] > 65000) {
				continue;
			}
			$cmd = "/usr/sbin/pw groupdel -g " . escapeshellarg($line[2]);
			if ($debug) {
				log_error(sprintf(gettext("Running: %s"), $cmd));
			}
			mwexec($cmd);
		}
		pclose($fd);
	}

	/* make sure the all group exists */
	$allgrp = getGroupEntryByGID(1998);
	local_group_set($allgrp, true);

	/* sync all local users */
	if (is_array($config['system']['user'])) {
		foreach ($config['system']['user'] as $user) {
			local_user_set($user);
		}
	}

	/* sync all local groups */
	if (is_array($config['system']['group'])) {
		foreach ($config['system']['group'] as $group) {
			local_group_set($group);
		}
	}
}

function local_user_set(& $user) {
	global $g, $debug;

	if (empty($user['password']) && empty($user['bcrypt-hash'])) {
		log_error("There is something wrong in the config because user {$user['name']} password is missing!");
		return;
	}


	$home_base = "/home/";
	$user_uid = $user['uid'];
	$user_name = $user['name'];
	$user_home = "{$home_base}{$user_name}";
	$user_shell = "/etc/rc.initial";
	$user_group = "nobody";

	// Ensure $home_base exists and is writable
	if (!is_dir($home_base)) {
		mkdir($home_base, 0755);
	}

	$lock_account = false;
	/* configure shell type */
	/* Cases here should be ordered by most privileged to least privileged. */
	if (userHasPrivilege($user, "user-shell-access") || userHasPrivilege($user, "page-all")) {
		$user_shell = "/bin/tcsh";
	} elseif (userHasPrivilege($user, "user-copy-files-chroot")) {
		$user_shell = "/usr/local/sbin/scponlyc";
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
		if ($debug) {
			log_error(sprintf(gettext("Running: %s"), $cmd));
		}
		$fd = popen($cmd, "w");
		if (empty($user['bcrypt-hash'])) {
			fwrite($fd, $user['password']);
		} else {
			fwrite($fd, $user['bcrypt-hash']);
		}
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

	$skel_dir = '/etc/skel';

	/* determine add or mod */
	if (($userattrs[0] != $user['name']) || (!strncmp($pwread, "pw:", 3))) {
		$user_op = "useradd -m -k " . escapeshellarg($skel_dir) . " -o";
	} else {
		$user_op = "usermod";
	}

	$comment = str_replace(array(":", "!", "@"), " ", $user['descr']);
	/* add or mod pw db */
	$cmd = "/usr/sbin/pw {$user_op} -q " .
			" -u " . escapeshellarg($user_uid) .
			" -n " . escapeshellarg($user_name) .
			" -g " . escapeshellarg($user_group) .
			" -s " . escapeshellarg($user_shell) .
			" -d " . escapeshellarg($user_home) .
			" -c " . escapeshellarg($comment) .
			" -H 0 2>&1";

	if ($debug) {
		log_error(sprintf(gettext("Running: %s"), $cmd));
	}
	$fd = popen($cmd, "w");
	if (empty($user['bcrypt-hash'])) {
		fwrite($fd, $user['password']);
	} else {
		fwrite($fd, $user['bcrypt-hash']);
	}
	pclose($fd);

	/* create user directory if required */
	if (!is_dir($user_home)) {
		mkdir($user_home, 0700);
	}
	@chown($user_home, $user_name);
	@chgrp($user_home, $user_group);

	/* Make sure all users have last version of config files */
	foreach (glob("{$skel_dir}/dot.*") as $dot_file) {
		$target = $user_home . '/' . substr(basename($dot_file), 3);
		@copy($dot_file, $target);
		@chown($target, $user_name);
		@chgrp($target, $user_group);
	}

	/* write out ssh authorized key file */
	if ($user['authorizedkeys']) {
		if (!is_dir("{$user_home}/.ssh")) {
			@mkdir("{$user_home}/.ssh", 0700);
			@chown("{$user_home}/.ssh", $user_name);
		}
		$keys = base64_decode($user['authorizedkeys']);
		@file_put_contents("{$user_home}/.ssh/authorized_keys", $keys);
		@chown("{$user_home}/.ssh/authorized_keys", $user_name);
	} else {
		unlink_if_exists("{$user_home}/.ssh/authorized_keys");
	}

	$un = $lock_account ? "" : "un";
	exec("/usr/sbin/pw {$un}lock " . escapeshellarg($user_name) . " -q 2>/dev/null");

}

function local_user_del($user) {
	global $debug;

	/* remove all memberships */
	local_user_set_groups($user);

	/* Don't remove /root */
	if ($user['uid'] != 0) {
		$rmhome = "-r";
	}

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
	$cmd = "/usr/sbin/pw userdel -n " . escapeshellarg($user['name']) . " " . escapeshellarg($rmhome);

	if ($debug) {
		log_error(sprintf(gettext("Running: %s"), $cmd));
	}
	mwexec($cmd);

	/* Delete user from groups needs a call to write_config() */
	local_group_del_user($user);
}

function local_user_set_password(&$user, $password) {
	global $config;

	unset($user['password']);
	unset($user['md5-hash']);
	$user['bcrypt-hash'] = password_hash($password, PASSWORD_BCRYPT);
	if (($user['name'] == $config['hasync']['username']) &&
	    ($config['hasync']['adminsync'] == 'on')) {
		$config['hasync']['new_password'] = $password;
	}
}

function local_user_get_groups($user, $all = false) {
	global $debug, $config;

	$groups = array();
	if (!is_array($config['system']['group'])) {
		return $groups;
	}

	foreach ($config['system']['group'] as $group) {
		if ($all || (!$all && ($group['name'] != "all"))) {
			if (is_array($group['member'])) {
				if (in_array($user['uid'], $group['member'])) {
					$groups[] = $group['name'];
				}
			}
		}
	}

	if ($all) {
		$groups[] = "all";
	}

	sort($groups);

	return $groups;

}

function local_user_set_groups($user, $new_groups = NULL) {
	global $debug, $config, $groupindex, $userindex;

	if (!is_array($config['system']['group'])) {
		return;
	}

	$cur_groups = local_user_get_groups($user, true);
	$mod_groups = array();

	if (!is_array($new_groups)) {
		$new_groups = array();
	}

	if (!is_array($cur_groups)) {
		$cur_groups = array();
	}

	/* determine which memberships to add */
	foreach ($new_groups as $groupname) {
		if ($groupname == '' || in_array($groupname, $cur_groups)) {
			continue;
		}
		$group = &$config['system']['group'][$groupindex[$groupname]];
		$group['member'][] = $user['uid'];
		$mod_groups[] = $group;

		/*
		 * If it's a new user, make sure it is added before try to
		 * add it as a member of a group
		 */
		if (!isset($userindex[$user['uid']])) {
			local_user_set($user);
		}
	}
	unset($group);

	/* determine which memberships to remove */
	foreach ($cur_groups as $groupname) {
		if (in_array($groupname, $new_groups)) {
			continue;
		}
		if (!isset($config['system']['group'][$groupindex[$groupname]])) {
			continue;
		}
		$group = &$config['system']['group'][$groupindex[$groupname]];
		if (is_array($group['member'])) {
			$index = array_search($user['uid'], $group['member']);
			array_splice($group['member'], $index, 1);
			$mod_groups[] = $group;
		}
	}
	unset($group);

	/* sync all modified groups */
	foreach ($mod_groups as $group) {
		local_group_set($group);
	}
}

function local_group_del_user($user) {
	global $config;

	if (!is_array($config['system']['group'])) {
		return;
	}

	foreach ($config['system']['group'] as $group) {
		if (is_array($group['member'])) {
			foreach ($group['member'] as $idx => $uid) {
				if ($user['uid'] == $uid) {
					unset($config['system']['group']['member'][$idx]);
				}
			}
		}
	}
}

function local_group_set($group, $reset = false) {
	global $debug;

	$group_name = $group['name'];
	$group_gid = $group['gid'];
	$group_members = '';

	if (!$reset && !empty($group['member']) && count($group['member']) > 0) {
		$group_members = implode(",", $group['member']);
	}

	if (empty($group_name)) {
		return;
	}

	// If the group is now remote, make sure there is no local group with the same name
	if ($group['scope'] == "remote") {
		local_group_del($group);
		return;
	}

	/* determine add or mod */
	if (mwexec("/usr/sbin/pw groupshow -g " . escapeshellarg($group_gid) . " 2>&1", true) == 0) {
		$group_op = "groupmod -l";
	} else {
		$group_op = "groupadd -n";
	}

	/* add or mod group db */
	$cmd = "/usr/sbin/pw {$group_op} " .
		escapeshellarg($group_name) .
		" -g " . escapeshellarg($group_gid) .
		" -M " . escapeshellarg($group_members) . " 2>&1";

	if ($debug) {
		log_error(sprintf(gettext("Running: %s"), $cmd));
	}

	mwexec($cmd);
}

function local_group_del($group) {
	global $debug;

	/* delete from group db */
	$cmd = "/usr/sbin/pw groupdel " . escapeshellarg($group['name']);

	if ($debug) {
		log_error(sprintf(gettext("Running: %s"), $cmd));
	}
	mwexec($cmd);
}

function ldap_test_connection($authcfg) {
	if ($authcfg) {
		if (strstr($authcfg['ldap_urltype'], "SSL")) {
			$ldapproto = "ldaps";
		} else {
			$ldapproto = "ldap";
		}
		$ldapserver = "{$ldapproto}://" . ldap_format_host($authcfg['host']);
		$ldapport = $authcfg['ldap_port'];
		if (!empty($ldapport)) {
			$ldapserver .= ":{$ldapport}";
		}
	} else {
		return false;
	}

	/* first check if there is even an LDAP server populated */
	if (!$ldapserver) {
		return false;
	}

	/* connect and see if server is up */
	$error = false;
	if (!($ldap = ldap_connect($ldapserver))) {
		$error = true;
	}

	if ($error == true) {
		log_error(sprintf(gettext("ERROR!  Could not connect to server %s."), $authcfg['name']));
		return false;
	}

	/* Setup CA environment if needed. */
	ldap_setup_caenv($ldap, $authcfg);

	return true;
}

function ldap_setup_caenv($ldap, $authcfg) {
	global $g;
	require_once("certs.inc");

	unset($caref);
	if (empty($authcfg['ldap_caref']) || strstr($authcfg['ldap_urltype'], "Standard")) {
		ldap_set_option($ldap, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
		return;
	} elseif ($authcfg['ldap_caref'] == "global") {
		ldap_set_option(NULL, LDAP_OPT_X_TLS_CACERTDIR, "/etc/ssl/");
		ldap_set_option(NULL, LDAP_OPT_X_TLS_CACERTFILE, "/etc/ssl/cert.pem");
		ldap_set_option($ldap, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_HARD);
	} else {
		$caref = lookup_ca($authcfg['ldap_caref']);
		$cert_details = openssl_x509_parse(base64_decode($caref['crt']));
		$param = array('caref' => $authcfg['ldap_caref']);
		$cachain = ca_chain($param);
		if (!$caref) {
			log_error(sprintf(gettext("LDAP: Could not lookup CA by reference for host %s."), $authcfg['ldap_caref']));
			/* XXX: Prevent for credential leaking since we cannot setup the CA env. Better way? */
			ldap_set_option($ldap, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_HARD);
			return;
		}

		$cert_path = "{$g['varrun_path']}/certs";
		$cert_filename = "{$cert_path}/{$cert_details['hash']}.0";
		safe_mkdir($cert_path);
		unlink_if_exists($cert_filename);
		file_put_contents($cert_filename, $cachain);
		@chmod($cert_filename, 0600);

		ldap_set_option($ldap, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_HARD);
		ldap_set_option(NULL, LDAP_OPT_X_TLS_CACERTDIR, $cert_path);
		ldap_set_option(NULL, LDAP_OPT_X_TLS_CACERTFILE, $cert_filename);
	}
}

function ldap_test_bind($authcfg) {
	global $debug, $config, $g;

	if ($authcfg) {
		if (strstr($authcfg['ldap_urltype'], "SSL")) {
			$ldapproto = "ldaps";
		} else {
			$ldapproto = "ldap";
		}
		$ldapserver = "{$ldapproto}://" . ldap_format_host($authcfg['host']);
		$ldapport = $authcfg['ldap_port'];
		if (!empty($ldapport)) {
			$ldapserver .= ":{$ldapport}";
		}
		$ldapbasedn = $authcfg['ldap_basedn'];
		$ldapbindun = $authcfg['ldap_binddn'];
		$ldapbindpw = $authcfg['ldap_bindpw'];
		$ldapver = $authcfg['ldap_protver'];
		$ldaptimeout = is_numeric($authcfg['ldap_timeout']) ? $authcfg['ldap_timeout'] : 5;
		if (empty($ldapbindun) || empty($ldapbindpw)) {
			$ldapanon = true;
		} else {
			$ldapanon = false;
		}
	} else {
		return false;
	}

	/* first check if there is even an LDAP server populated */
	if (!$ldapserver) {
		return false;
	}

	/* connect and see if server is up */
	$error = false;
	if (!($ldap = ldap_connect($ldapserver))) {
		$error = true;
	}

	if ($error == true) {
		log_error(sprintf(gettext("ERROR!  Could not connect to server %s."), $ldapname));
		return false;
	}

	/* Setup CA environment if needed. */
	ldap_setup_caenv($ldap, $authcfg);

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_DEREF, LDAP_DEREF_SEARCHING);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);
	ldap_set_option($ldap, LDAP_OPT_TIMELIMIT, (int)$ldaptimeout);
	ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, (int)$ldaptimeout);

	if (strstr($authcfg['ldap_urltype'], "STARTTLS")) {
		if (!(@ldap_start_tls($ldap))) {
			log_error(sprintf(gettext("ERROR! ldap_test_bind() could not STARTTLS to server %s."), $ldapname));
			@ldap_close($ldap);
			return false;
		}
	}

	$ldapbindun = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindun) : $ldapbindun;
	$ldapbindpw = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindpw) : $ldapbindpw;
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

	if (!function_exists("ldap_connect")) {
		return;
	}

	$ous = array();

	if ($authcfg) {
		if (strstr($authcfg['ldap_urltype'], "SSL")) {
			$ldapproto = "ldaps";
		} else {
			$ldapproto = "ldap";
		}
		$ldapserver = "{$ldapproto}://" . ldap_format_host($authcfg['host']);
		$ldapport = $authcfg['ldap_port'];
		if (!empty($ldapport)) {
			$ldapserver .= ":{$ldapport}";
		}
		$ldapbasedn = $authcfg['ldap_basedn'];
		$ldapbindun = $authcfg['ldap_binddn'];
		$ldapbindpw = $authcfg['ldap_bindpw'];
		$ldapver = $authcfg['ldap_protver'];
		if (empty($ldapbindun) || empty($ldapbindpw)) {
			$ldapanon = true;
		} else {
			$ldapanon = false;
		}
		$ldapname = $authcfg['name'];
		$ldapfallback = false;
		$ldapscope = $authcfg['ldap_scope'];
		$ldaptimeout = is_numeric($authcfg['ldap_timeout']) ? $authcfg['ldap_timeout'] : 5;
	} else {
		return false;
	}

	/* first check if there is even an LDAP server populated */
	if (!$ldapserver) {
		log_error(gettext("ERROR!  ldap_get_user_ous() backed selected with no LDAP authentication server defined."));
		return $ous;
	}

	/* connect and see if server is up */
	$error = false;
	if (!($ldap = ldap_connect($ldapserver))) {
		$error = true;
	}

	if ($error == true) {
		log_error(sprintf(gettext("ERROR!  Could not connect to server %s."), $ldapname));
		return $ous;
	}

	/* Setup CA environment if needed. */
	ldap_setup_caenv($ldap, $authcfg);

	$ldapfilter = "(|(ou=*)(cn=Users))";

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_DEREF, LDAP_DEREF_SEARCHING);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);
	ldap_set_option($ldap, LDAP_OPT_TIMELIMIT, (int)$ldaptimeout);
	ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, (int)$ldaptimeout);

	if (strstr($authcfg['ldap_urltype'], "STARTTLS")) {
		if (!(@ldap_start_tls($ldap))) {
			log_error(sprintf(gettext("ERROR! ldap_get_user_ous() could not STARTTLS to server %s."), $ldapname));
			@ldap_close($ldap);
			return false;
		}
	}

	$ldapbindun = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindun) : $ldapbindun;
	$ldapbindpw = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindpw) : $ldapbindpw;
	if ($ldapanon == true) {
		if (!($res = @ldap_bind($ldap))) {
			log_error(sprintf(gettext("ERROR! ldap_get_user_ous() could not bind anonymously to server %s."), $ldapname));
			@ldap_close($ldap);
			return $ous;
		}
	} else if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		log_error(sprintf(gettext("ERROR! ldap_get_user_ous() could not bind to server %s."), $ldapname));
		@ldap_close($ldap);
		return $ous;
	}

	if ($ldapscope == "one") {
		$ldapfunc = "ldap_list";
	} else {
		$ldapfunc = "ldap_search";
	}

	$search = @$ldapfunc($ldap, $ldapbasedn, $ldapfilter);
	$info = @ldap_get_entries($ldap, $search);

	if (is_array($info)) {
		foreach ($info as $inf) {
			if (!$show_complete_ou) {
				$inf_split = explode(",", $inf['dn']);
				$ou = $inf_split[0];
				$ou = str_replace("OU=", "", $ou);
				$ou = str_replace("CN=", "", $ou);
			} else {
				if ($inf['dn']) {
					$ou = $inf['dn'];
				}
			}
			if ($ou) {
				$ous[] = $ou;
			}
		}
	}

	@ldap_unbind($ldap);

	return $ous;
}

function ldap_get_groups($username, $authcfg) {
	global $debug, $config;

	if (!function_exists("ldap_connect")) {
		return;
	}

	if (!$username) {
		return false;
	}

	if (!isset($authcfg['ldap_nostrip_at']) && stristr($username, "@")) {
		$username_split = explode("@", $username);
		$username = $username_split[0];
	}

	if (stristr($username, "\\")) {
		$username_split = explode("\\", $username);
		$username = $username_split[0];
	}

	//log_error("Getting LDAP groups for {$username}.");
	if ($authcfg) {
		if (strstr($authcfg['ldap_urltype'], "SSL")) {
			$ldapproto = "ldaps";
		} else {
			$ldapproto = "ldap";
		}
		$ldapserver = "{$ldapproto}://" . ldap_format_host($authcfg['host']);
		$ldapport = $authcfg['ldap_port'];
		if (!empty($ldapport)) {
			$ldapserver .= ":{$ldapport}";
		}
		$ldapbasedn = $authcfg['ldap_basedn'];
		$ldapbindun = $authcfg['ldap_binddn'];
		$ldapbindpw = $authcfg['ldap_bindpw'];
		$ldapauthcont = $authcfg['ldap_authcn'];
		$ldapnameattribute = strtolower($authcfg['ldap_attr_user']);
		$ldapgroupattribute = strtolower($authcfg['ldap_attr_member']);
		$ldaptype = "";
		$ldapver = $authcfg['ldap_protver'];
		if (empty($ldapbindun) || empty($ldapbindpw)) {
			$ldapanon = true;
		} else {
			$ldapanon = false;
		}
		$ldapname = $authcfg['name'];
		$ldapfallback = false;
		$ldapscope = $authcfg['ldap_scope'];
		$ldaptimeout = is_numeric($authcfg['ldap_timeout']) ? $authcfg['ldap_timeout'] : 5;
	} else {
		return false;
	}

	if (isset($authcfg['ldap_rfc2307'])) {
		$ldapdn = $ldapbasedn;
	} else {
		$ldapdn = $_SESSION['ldapdn'];
	}

	/*Convert attribute to lowercase.  php ldap arrays put everything in lowercase */
	$ldapgroupattribute = strtolower($ldapgroupattribute);
	$memberof = array();

	/* connect and see if server is up */
	$error = false;
	if (!($ldap = ldap_connect($ldapserver))) {
		$error = true;
	}

	if ($error == true) {
		log_error(sprintf(gettext("ERROR! ldap_get_groups() Could not connect to server %s."), $ldapname));
		return $memberof;
	}

	/* Setup CA environment if needed. */
	ldap_setup_caenv($ldap, $authcfg);

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_DEREF, LDAP_DEREF_SEARCHING);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);
	ldap_set_option($ldap, LDAP_OPT_TIMELIMIT, (int)$ldaptimeout);
	ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, (int)$ldaptimeout);

	if (strstr($authcfg['ldap_urltype'], "STARTTLS")) {
		if (!(@ldap_start_tls($ldap))) {
			log_error(sprintf(gettext("ERROR! ldap_get_groups() could not STARTTLS to server %s."), $ldapname));
			@ldap_close($ldap);
			return false;
		}
	}

	/* bind as user that has rights to read group attributes */
	$ldapbindun = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindun) : $ldapbindun;
	$ldapbindpw = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindpw) : $ldapbindpw;
	if ($ldapanon == true) {
		if (!($res = @ldap_bind($ldap))) {
			log_error(sprintf(gettext("ERROR! ldap_get_groups() could not bind anonymously to server %s."), $ldapname));
			@ldap_close($ldap);
			return false;
		}
	} else if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		log_error(sprintf(gettext("ERROR! ldap_get_groups() could not bind to server %s."), $ldapname));
		@ldap_close($ldap);
		return $memberof;
	}

	/* get groups from DN found */
	/* use ldap_read instead of search so we don't have to do a bunch of extra work */
	/* since we know the DN is in $_SESSION['ldapdn'] */
	//$search    = ldap_read($ldap, $ldapdn, "(objectclass=*)", array($ldapgroupattribute));
	if ($ldapscope == "one") {
		$ldapfunc = "ldap_list";
	} else {
		$ldapfunc = "ldap_search";
	}

	if (isset($authcfg['ldap_rfc2307'])) {
		if (isset($authcfg['ldap_rfc2307_userdn'])) {
			$ldac_splits = explode(";", $ldapauthcont);
			foreach ($ldac_splits as $i => $ldac_split) {
				$ldac_split = isset($authcfg['ldap_utf8']) ? utf8_encode($ldac_split) : $ldac_split;
				$ldapsearchbasedn = isset($authcfg['ldap_utf8']) ? utf8_encode("{$ldac_split},{$ldapbasedn}") : "{$ldac_split},{$ldapbasedn}";
				$ldapfilter = "({$ldapnameattribute}={$username})";
				if (stristr($ldac_split, "DC=") || empty($ldapbasedn)) {
					$ldapdn = $ldac_split;
				} else {
					$ldapdn = $ldapsearchbasedn;
				}
				$usersearch = @$ldapfunc($ldap, $ldapdn, $ldapfilter);
				$userinfo = @ldap_get_entries($ldap, $usersearch);
			}
			$username = $userinfo[0]['dn'];
		}
		$ldapfilter = "(&(objectClass={$authcfg['ldap_attr_groupobj']})({$ldapgroupattribute}={$username}))";
	} else {
		$ldapfilter = "({$ldapnameattribute}={$username})";
	}

	$search = @$ldapfunc($ldap, $ldapdn, $ldapfilter, array($ldapgroupattribute));
	$info = @ldap_get_entries($ldap, $search);

	$gresults = isset($authcfg['ldap_rfc2307']) ? $info : $info[0][$ldapgroupattribute];

	if (is_array($gresults)) {
		/* Iterate through the groups and throw them into an array */
		foreach ($gresults as $grp) {
			if (((isset($authcfg['ldap_rfc2307'])) && (stristr($grp["dn"], "CN=") !== false)) ||
			    ((!isset($authcfg['ldap_rfc2307'])) && (stristr($grp, "CN=") !== false))) {
				$grpsplit = isset($authcfg['ldap_rfc2307']) ? explode(",", $grp["dn"]) : explode(",", $grp);
				$memberof[] = preg_replace("/CN=/i", "", $grpsplit[0]);
			}
		}
	}

	/* Time to close LDAP connection */
	@ldap_unbind($ldap);

	$groups = print_r($memberof, true);

	//log_error("Returning groups ".$groups." for user $username");

	return $memberof;
}

function ldap_format_host($host) {
	return is_ipaddrv6($host) ? "[$host]" : $host ;
}

function ldap_backed($username, $passwd, $authcfg, &$attributes = array()) {
	global $debug, $config;

	if (!$username) {
		$attributes['error_message'] = gettext("Invalid Login.");
		return false;
	}

	if (!isset($authcfg['ldap_allow_unauthenticated']) && $passwd == '') {
		$attributes['error_message'] = gettext("Invalid credentials.");
		return false;
	}

	if (!function_exists("ldap_connect")) {
		log_error(gettext("ERROR! unable to find ldap_connect() function."));
		$attributes['error_message'] = gettext("Internal error during authentication.");
		return null;
	}

	if (!isset($authcfg['ldap_nostrip_at']) && stristr($username, "@")) {
		$username_split = explode("@", $username);
		$username = $username_split[0];
	}
	if (stristr($username, "\\")) {
		$username_split = explode("\\", $username);
		$username = $username_split[0];
	}

	$username = ldap_escape($username, null, LDAP_ESCAPE_FILTER);

	if ($authcfg) {
		if (strstr($authcfg['ldap_urltype'], "SSL")) {
			$ldapproto = "ldaps";
		} else {
			$ldapproto = "ldap";
		}
		$ldapserver = "{$ldapproto}://" . ldap_format_host($authcfg['host']);
		$ldapport = $authcfg['ldap_port'];
		if (!empty($ldapport)) {
			$ldapserver .= ":{$ldapport}";
		}
		$ldapbasedn = $authcfg['ldap_basedn'];
		$ldapbindun = $authcfg['ldap_binddn'];
		$ldapbindpw = $authcfg['ldap_bindpw'];
		if (empty($ldapbindun) || empty($ldapbindpw)) {
			$ldapanon = true;
		} else {
			$ldapanon = false;
		}
		$ldapauthcont = $authcfg['ldap_authcn'];
		$ldapnameattribute = strtolower($authcfg['ldap_attr_user']);
		$ldapgroupattribute = $authcfg['ldap_attr_member'];
		$ldapextendedqueryenabled = $authcfg['ldap_extended_enabled'];
		$ldapextendedquery = $authcfg['ldap_extended_query'];
		$ldapfilter = "";
		if (!$ldapextendedqueryenabled) {
			$ldapfilter = "({$ldapnameattribute}={$username})";
		} else {
			if (isset($authcfg['ldap_rfc2307'])) {
				$ldapfilter = "({$ldapnameattribute}={$username})";
				$ldapgroupfilter = "(&({$ldapgroupattribute}={$username})({$ldapextendedquery}))";
			} else {
				$ldapfilter = "(&({$ldapnameattribute}={$username})({$ldapextendedquery}))";
			}
		}
		$ldaptype = "";
		$ldapver = $authcfg['ldap_protver'];
		$ldapname = $authcfg['name'];
		$ldapscope = $authcfg['ldap_scope'];
		$ldaptimeout = is_numeric($authcfg['ldap_timeout']) ? $authcfg['ldap_timeout'] : 5;
	} else {
		return null;
	}

	/* first check if there is even an LDAP server populated */
	if (!$ldapserver) {
		log_error(gettext("ERROR! could not find details of the LDAP server used for authentication."));
		$attributes['error_message'] =  gettext("Internal error during authentication.");
		return null;
	}

	/* Make sure we can connect to LDAP */
	$error = false;
	if (!($ldap = ldap_connect($ldapserver))) {
		$error = true;
	}

	/* Setup CA environment if needed. */
	ldap_setup_caenv($ldap, $authcfg);

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_DEREF, LDAP_DEREF_SEARCHING);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);
	ldap_set_option($ldap, LDAP_OPT_TIMELIMIT, (int)$ldaptimeout);
	ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, (int)$ldaptimeout);

	if (strstr($authcfg['ldap_urltype'], "STARTTLS")) {
		if (!(@ldap_start_tls($ldap))) {
			log_error(sprintf(gettext("ERROR! could not connect to LDAP server %s using STARTTLS."), $ldapname));
			$attributes['error_message'] = gettext("Error : could not connect to authentication server.");
			@ldap_close($ldap);
			return null;
		}
	}

	if ($error == true) {
		$errormsg = sprintf(gettext("ERROR! Could not connect to server %s."), $ldapname);
		$attributes['error_message'] = gettext("Error : could not connect to authentication server.");
		return null;
	}

	/* ok, its up.  now, lets bind as the bind user so we can search it */
	$error = false;
	$ldapbindun = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindun) : $ldapbindun;
	$ldapbindpw = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindpw) : $ldapbindpw;
	if ($ldapanon == true) {
		if (!($res = @ldap_bind($ldap))) {
			$error = true;
		}
	} else if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		$error = true;
	}

	if ($error == true) {
		@ldap_close($ldap);
		log_error(sprintf(gettext("ERROR! Could not bind to LDAP server %s. Please check the bind credentials."), $ldapname));
		$attributes['error_message'] = gettext("Error : could not connect to authentication server.");
		return null;
	}

	/* Get LDAP Authcontainers and split em up. */
	$ldac_splits = explode(";", $ldapauthcont);

	/* setup the usercount so we think we haven't found anyone yet */
	$usercount = 0;

	/*****************************************************************/
	/*  We first find the user based on username and filter          */
	/*  then, once we find the first occurrence of that person       */
	/*  we set session variables to point to the OU and DN of the    */
	/*  person.  To later be used by ldap_get_groups.                */
	/*  that way we don't have to search twice.                      */
	/*****************************************************************/
	if ($debug) {
		log_auth(sprintf(gettext("Now Searching for %s in directory."), $username));
	}
	/* Iterate through the user containers for search */
	foreach ($ldac_splits as $i => $ldac_split) {
		$ldac_split = isset($authcfg['ldap_utf8']) ? utf8_encode($ldac_split) : $ldac_split;
		$ldapfilter = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapfilter) : $ldapfilter;
		$ldapsearchbasedn = isset($authcfg['ldap_utf8']) ? utf8_encode("{$ldac_split},{$ldapbasedn}") : "{$ldac_split},{$ldapbasedn}";
		/* Make sure we just use the first user we find */
		if ($debug) {
			log_auth(sprintf(gettext('Now Searching in server %1$s, container %2$s with filter %3$s.'), $ldapname, utf8_decode($ldac_split), utf8_decode($ldapfilter)));
		}
		if ($ldapscope == "one") {
			$ldapfunc = "ldap_list";
		} else {
			$ldapfunc = "ldap_search";
		}
		/* Support legacy auth container specification. */
		if (stristr($ldac_split, "DC=") || empty($ldapbasedn)) {
			$ldapdn = $ldac_split;
		} else {
			$ldapdn = $ldapsearchbasedn;
		}
		$search = @$ldapfunc($ldap, $ldapdn, $ldapfilter);
		if (!$search) {
			log_error(sprintf(gettext("Search resulted in error: %s"), ldap_error($ldap)));
			continue;
		}
		if (isset($authcfg['ldap_rfc2307']) && isset($ldapgroupfilter)) {
			if (isset($authcfg['ldap_rfc2307_userdn'])) {
				$info = ldap_get_entries($ldap, $search);
				$username = $info[0]['dn'];
			}
			$ldapgroupfilter = "(&({$ldapgroupattribute}={$username})({$ldapextendedquery}))";
			$groupsearch = @$ldapfunc($ldap, $ldapdn, $ldapgroupfilter);
		}

		if (isset($ldapgroupfilter) && !$groupsearch) {
			log_error(sprintf(gettext("Extended group search resulted in error: %s"), ldap_error($ldap)));
			continue;
		}
		if (isset($groupsearch)) {
			$validgroup = ldap_count_entries($ldap, $groupsearch);
			if ($debug) {
				log_auth(sprintf(gettext("LDAP group search: %s results."), $validgroup));
			}
		}
		$info = ldap_get_entries($ldap, $search);
		$matches = $info['count'];
		if ($matches == 1) {
			$userdn = $_SESSION['ldapdn'] = $info[0]['dn'];
			$_SESSION['ldapou'] = $ldac_split[$i];
			$_SESSION['ldapon'] = "true";
			$usercount = 1;
			break;
		}
	}

	if ($usercount != 1) {
		@ldap_unbind($ldap);
		if ($debug) {
			if ($usercount === 0) {
				log_error(sprintf(gettext("ERROR! LDAP search failed, no user matching %s was found."), $username));
			} else {
				log_error(sprintf(gettext("ERROR! LDAP search failed, multiple users matching %s were found."), $username));
			}
		}
		$attributes['error_message'] = gettext("Invalid login specified.");
		return false;
	}

	/* Now lets bind as the user we found */
	$passwd = isset($authcfg['ldap_utf8']) ? utf8_encode($passwd) : $passwd;
	if (!($res = @ldap_bind($ldap, $userdn, $passwd))) {
		if ($debug) {
			log_error(sprintf(gettext('ERROR! Could not login to server %1$s as user %2$s: %3$s'), $ldapname, $username, ldap_error($ldap)));
		}
		@ldap_unbind($ldap);
		return false;
	}

	if ($debug) {
		$userdn = isset($authcfg['ldap_utf8']) ? utf8_decode($userdn) : $userdn;
		log_auth(sprintf(gettext('Logged in successfully as %1$s via LDAP server %2$s with DN = %3$s.'), $username, $ldapname, $userdn));
	}

	if ($debug && isset($ldapgroupfilter) && $validgroup < 1) {
		log_auth(sprintf(gettext('Logged in successfully as %1$s but did not match any field in extended query.'), $username));
	}

	/* At this point we are bound to LDAP so the user was auth'd okay. Close connection. */
	@ldap_unbind($ldap);

	if (isset($ldapgroupfilter) && $validgroup < 1) {
		return false;
	}

	return true;
}

function radius_backed($username, $password, $authcfg, &$attributes = array()) {
	global $debug, $config;
	$ret = false;

	require_once("Auth/RADIUS.php");
	require_once("Crypt/CHAP.php");

	if ($authcfg) {
		$radiusservers = array();
		$radiusservers[0]['ipaddr'] = $authcfg['host'];
		$radiusservers[0]['port'] = $authcfg['radius_auth_port'];
		$radiusservers[0]['sharedsecret'] = $authcfg['radius_secret'];
		$radiusservers[0]['timeout'] = $authcfg['radius_timeout'];
		if(isset($authcfg['radius_protocol'])) {
			$radius_protocol = $authcfg['radius_protocol'];
		} else {
			$radius_protocol = 'PAP';
		}
	} else {
		log_error(gettext("ERROR! could not find details of the RADIUS server used for authentication."));
		$attributes['error_message'] =  gettext("Internal error during authentication.");
		return null;
	}

	// Create our instance
	$classname = 'Auth_RADIUS_' . $radius_protocol;
	$rauth = new $classname($username, $password);

	/* Add new servers to our instance */
	foreach ($radiusservers as $radsrv) {
		$timeout = (is_numeric($radsrv['timeout'])) ? $radsrv['timeout'] : 5;
		$rauth->addServer($radsrv['ipaddr'], $radsrv['port'], $radsrv['sharedsecret'], $timeout);
	}

	// Construct data package
	$rauth->username = $username;
	switch ($radius_protocol) {
		case 'CHAP_MD5':
		case 'MSCHAPv1':
			$classname = $radius_protocol == 'MSCHAPv1' ? 'Crypt_CHAP_MSv1' : 'Crypt_CHAP_MD5';
			$crpt = new $classname;
			$crpt->username = $username;
			$crpt->password = $password;
			$rauth->challenge = $crpt->challenge;
			$rauth->chapid = $crpt->chapid;
			$rauth->response = $crpt->challengeResponse();
			$rauth->flags = 1;
			break;

		case 'MSCHAPv2':
			$crpt = new Crypt_CHAP_MSv2;
			$crpt->username = $username;
			$crpt->password = $password;
			$rauth->challenge = $crpt->authChallenge;
			$rauth->peerChallenge = $crpt->peerChallenge;
			$rauth->chapid = $crpt->chapid;
			$rauth->response = $crpt->challengeResponse();
			break;

		default:
			$rauth->password = $password;
			break;
	}

	if (PEAR::isError($rauth->start())) {
		$ret = null;
		log_error(sprintf(gettext("Error during RADIUS authentication : %s"), $rauth->getError()));
		$attributes['error_message'] = gettext("Error : could not connect to authentication server.");
	} else {
		$nasid = $attributes['nas_identifier'];
		$nasip = $authcfg['radius_nasip_attribute'];
		if (empty($nasid)) {
			$nasid = gethostname(); //If no RADIUS NAS-Identifier is given : we use pfsense's hostname as NAS-Identifier
		}
		if (!is_ipaddr($nasip)) {
			$nasip = get_interface_ip($nasip);

			if (!is_ipaddr($nasip)) {
				$nasip = get_interface_ip();//We use wan interface IP as fallback for NAS-IP-Address
			}
		}
		$nasmac = get_interface_mac(find_ip_interface($nasip));

		$rauth->putAttribute(RADIUS_NAS_IP_ADDRESS, $nasip, "addr");
		$rauth->putAttribute(RADIUS_NAS_IDENTIFIER, $nasid);

		if(!empty($attributes['calling_station_id'])) {
			$rauth->putAttribute(RADIUS_CALLING_STATION_ID, $attributes['calling_station_id']);
		}
		// Carefully check that interface has a MAC address
		if(!empty($nasmac)) {
			$nasmac = mac_format($nasmac);
			$rauth->putAttribute(RADIUS_CALLED_STATION_ID, $nasmac.':'.gethostname());
		}
		if(!empty($attributes['nas_port_type'])) {
			$rauth->putAttribute(RADIUS_NAS_PORT_TYPE, $attributes['nas_port_type']);
		}
		if(!empty($attributes['nas_port'])) {
			$rauth->putAttribute(RADIUS_NAS_PORT, intval($attributes['nas_port']), 'integer');
		}
		if(!empty($attributes['framed_ip']) && is_ipaddr($attributes['framed_ip'])) {
			$rauth->putAttribute(RADIUS_FRAMED_IP_ADDRESS, $attributes['framed_ip'], "addr");
		}
	}

	// XXX - billm - somewhere in here we need to handle securid challenge/response

	/* Send request */
	$result = $rauth->send();
	if (PEAR::isError($result)) {
		log_error(sprintf(gettext("Error during RADIUS authentication : %s"), $rauth->getError()));
		$attributes['error_message'] = gettext("Error : could not connect to authentication server.");
		$ret = null;
	} else if ($result === true) {
		$ret = true;
	} else {
		$ret = false;
	}


	// Get attributes, even if auth failed.
	if ($rauth->getAttributes()) {
	$attributes = array_merge($attributes,$rauth->listAttributes());

	// We convert the session_terminate_time to unixtimestamp if its set before returning the whole array to our caller
	if (!empty($attributes['session_terminate_time'])) {
			$stt = &$attributes['session_terminate_time'];
			$stt = strtotime(preg_replace("/\+(\d+):(\d+)$/", " +\${1}\${2}", preg_replace("/(\d+)T(\d+)/", "\${1} \${2}",$stt)));
		}
	}

	// close OO RADIUS_AUTHENTICATION
	$rauth->close();

	return $ret;
}

/*
	$attributes must contain a "class" key containing the groups and local
	groups must exist to match.
*/
function radius_get_groups($attributes) {
	$groups = array();
	if (!empty($attributes) && is_array($attributes) && (!empty($attributes['class']) || !empty($attributes['class_int']))) {
		/* Some RADIUS servers return multiple class attributes, so check them all. */
		$groups = array();
		if (!empty($attributes['class']) && is_array($attributes['class'])) {
			foreach ($attributes['class'] as $class) {
				$groups = array_unique(array_merge($groups, explode(";", $class)));
			}
		}

		foreach ($groups as & $grp) {
			$grp = trim($grp);
			if (strtolower(substr($grp, 0, 3)) == "ou=") {
				$grp = substr($grp, 3);
			}
		}
	}
	return $groups;
}

function get_user_expiration_date($username) {
	$user = getUserEntry($username);
	if ($user['expires']) {
		return $user['expires'];
	}
}

function is_account_expired($username) {
	$expirydate = get_user_expiration_date($username);
	if ($expirydate) {
		if (strtotime("-1 day") > strtotime(date("m/d/Y", strtotime($expirydate)))) {
			return true;
		}
	}

	return false;
}

function is_account_disabled($username) {
	$user = getUserEntry($username);
	if (isset($user['disabled'])) {
		return true;
	}

	return false;
}

function get_user_settings($username) {
	global $config;
	$settings = array();
	$settings['widgets'] = $config['widgets'];
	$settings['webgui']['dashboardcolumns'] = $config['system']['webgui']['dashboardcolumns'];
	$settings['webgui']['webguihostnamemenu'] = $config['system']['webgui']['webguihostnamemenu'];
	$settings['webgui']['webguicss'] = $config['system']['webgui']['webguicss'];
	$settings['webgui']['logincss'] = $config['system']['webgui']['logincss'];
	$settings['webgui']['interfacessort'] = isset($config['system']['webgui']['interfacessort']);
	$settings['webgui']['dashboardavailablewidgetspanel'] = isset($config['system']['webgui']['dashboardavailablewidgetspanel']);
	$settings['webgui']['webguifixedmenu'] = isset($config['system']['webgui']['webguifixedmenu']);
	$settings['webgui']['webguileftcolumnhyper'] = isset($config['system']['webgui']['webguileftcolumnhyper']);
	$settings['webgui']['disablealiaspopupdetail'] = isset($config['system']['webgui']['disablealiaspopupdetail']);
	$settings['webgui']['systemlogsfilterpanel'] = isset($config['system']['webgui']['systemlogsfilterpanel']);
	$settings['webgui']['systemlogsmanagelogpanel'] = isset($config['system']['webgui']['systemlogsmanagelogpanel']);
	$settings['webgui']['statusmonitoringsettingspanel'] = isset($config['system']['webgui']['statusmonitoringsettingspanel']);
	$settings['webgui']['pagenamefirst'] = isset($config['system']['webgui']['pagenamefirst']);
	$user = getUserEntry($username);
	if (isset($user['customsettings'])) {
		$settings['customsettings'] = true;
		if (isset($user['widgets'])) {
			// This includes the 'sequence', and any widgetname-config per-widget settings.
			$settings['widgets'] = $user['widgets'];
		}
		if (isset($user['dashboardcolumns'])) {
			$settings['webgui']['dashboardcolumns'] = $user['dashboardcolumns'];
		}
		if (isset($user['webguicss'])) {
			$settings['webgui']['webguicss'] = $user['webguicss'];
		}
		if (isset($user['webguihostnamemenu'])) {
			$settings['webgui']['webguihostnamemenu'] = $user['webguihostnamemenu'];
		}
		$settings['webgui']['interfacessort'] = isset($user['interfacessort']);
		$settings['webgui']['dashboardavailablewidgetspanel'] = isset($user['dashboardavailablewidgetspanel']);
		$settings['webgui']['webguifixedmenu'] = isset($user['webguifixedmenu']);
		$settings['webgui']['webguileftcolumnhyper'] = isset($user['webguileftcolumnhyper']);
		$settings['webgui']['disablealiaspopupdetail'] = isset($user['disablealiaspopupdetail']);
		$settings['webgui']['systemlogsfilterpanel'] = isset($user['systemlogsfilterpanel']);
		$settings['webgui']['systemlogsmanagelogpanel'] = isset($user['systemlogsmanagelogpanel']);
		$settings['webgui']['statusmonitoringsettingspanel'] = isset($user['statusmonitoringsettingspanel']);
		$settings['webgui']['pagenamefirst'] = isset($user['pagenamefirst']);
	} else {
		$settings['customsettings'] = false;
	}

	if ($settings['webgui']['dashboardcolumns'] < 1) {
		$settings['webgui']['dashboardcolumns'] = 2;
	}

	return $settings;
}

function save_widget_settings($username, $settings, $message = "") {
	global $config, $userindex;
	$user = getUserEntry($username);

	if (strlen($message) > 0) {
		$msgout = $message;
	} else {
		$msgout = gettext("Widget configuration has been changed.");
	}

	if (isset($user['customsettings'])) {
		$config['system']['user'][$userindex[$username]]['widgets'] = $settings;
		write_config($msgout . " " . sprintf(gettext("(User %s)"), $username));
	} else {
		$config['widgets'] = $settings;
		write_config($msgout);
	}
}

function auth_get_authserver($name) {
	global $config;

	if (is_array($config['system']['authserver'])) {
		foreach ($config['system']['authserver'] as $authcfg) {
			if ($authcfg['name'] == $name) {
				return $authcfg;
			}
		}
	}
	if ($name == "Local Database") {
		return array("name" => "Local Database", "type" => "Local Auth", "host" => $config['system']['hostname']);
	}
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

	$list["Local Database"] = array("name" => "Local Database", "type" => "Local Auth", "host" => $config['system']['hostname']);
	return $list;
}

function getUserGroups($username, $authcfg, &$attributes = array()) {
	global $config;

	$allowed_groups = array();

	switch ($authcfg['type']) {
		case 'ldap':
			$allowed_groups = @ldap_get_groups($username, $authcfg);
			break;
		case 'radius':
			$allowed_groups = @radius_get_groups($attributes);
			break;
		default:
			$user = getUserEntry($username);
			$allowed_groups = @local_user_get_groups($user, true);
			break;
	}

	$member_groups = array();
	if (is_array($config['system']['group'])) {
		foreach ($config['system']['group'] as $group) {
			if (in_array($group['name'], $allowed_groups)) {
				$member_groups[] = $group['name'];
			}
		}
	}

	return $member_groups;
}

/*
Possible return values :
true : authentication worked
false : authentication failed (invalid login/password, not enough permission, etc...)
null : error during authentication process (unable to reach remote server, etc...)
*/
function authenticate_user($username, $password, $authcfg = NULL, &$attributes = array()) {

	if (is_array($username) || is_array($password)) {
		return false;
	}

	if (!$authcfg) {
		return local_backed($username, $password, $attributes);
	}

	$authenticated = false;
	switch ($authcfg['type']) {
		case 'ldap':
			try {
				$authenticated = ldap_backed($username, $password, $authcfg, $attributes);
			} catch (Exception $e) {
				log_error(sprintf(gettext("LDAP authentication error: %s"), $e->getMessage()));
			}
			break;

			break;
		case 'radius':
			try {
				$authenticated = radius_backed($username, $password, $authcfg, $attributes);
			} catch (Exception $e) {
				log_error(sprintf(gettext("RADIUS authentication error: %s"), $e->getMessage()));
			}
			break;
		default:
			/* lookup user object by name */
			try {
				$authenticated = local_backed($username, $password, $attributes);
			} catch (Exception $e) {
				log_error(sprintf(gettext("Local authentication error: %s"), $e->getMessage()));
			}
			break;
		}

	return $authenticated;
}

function session_auth() {
	global $config, $_SESSION, $page;

	// Handle HTTPS httponly and secure flags
	$currentCookieParams = session_get_cookie_params();
	session_set_cookie_params(
		$currentCookieParams["lifetime"],
		$currentCookieParams["path"],
		NULL,
		($config['system']['webgui']['protocol'] == "https"),
		true
	);

	phpsession_begin();

	// Detect protocol change
	if (!isset($_POST['login']) && !empty($_SESSION['Logged_In']) && $_SESSION['protocol'] != $config['system']['webgui']['protocol']) {
		phpsession_end();
		return false;
	}

	/* Validate incoming login request */
	$attributes = array('nas_identifier' => 'webConfigurator-' . gethostname());
	if (isset($_POST['login']) && !empty($_POST['usernamefld'])) {
		$authcfg = auth_get_authserver($config['system']['webgui']['authmode']);
		$remoteauth = authenticate_user($_POST['usernamefld'], $_POST['passwordfld'], $authcfg, $attributes);
		if ($remoteauth || authenticate_user($_POST['usernamefld'], $_POST['passwordfld'])) {
			// Generate a new id to avoid session fixation
			session_regenerate_id();
			$_SESSION['Logged_In'] = "True";
			$_SESSION['remoteauth'] = $remoteauth;
			if ($remoteauth) {
				if (empty($authcfg['type']) || ($authcfg['type'] == "Local Auth")) {
					$_SESSION['authsource'] = "Local Database";
				} else {
					$_SESSION['authsource'] = strtoupper($authcfg['type']) . "/{$authcfg['name']}";
				}
			} else {
				$_SESSION['authsource'] = 'Local Database Fallback';
			}
			$_SESSION['Username'] = $_POST['usernamefld'];
			$_SESSION['user_radius_attributes'] = $attributes;
			$_SESSION['last_access'] = time();
			$_SESSION['protocol'] = $config['system']['webgui']['protocol'];
			phpsession_end(true);
			if (!isset($config['system']['webgui']['quietlogin'])) {
				log_auth(sprintf(gettext("Successful login for user '%1\$s' from: %2\$s"), $_POST['usernamefld'], get_user_remote_address() . get_user_remote_authsource()));
			}
			if (isset($_POST['postafterlogin'])) {
				return true;
			} else {
				if (empty($page)) {
					$page = "/";
				}
				header("Location: {$page}");
			}
			exit;
		} else {
			/* give the user an error message */
			$_SESSION['Login_Error'] = gettext("Username or Password incorrect");
			log_auth(sprintf(gettext("webConfigurator authentication error for user '%1\$s' from: %2\$s"), $_POST['usernamefld'], get_user_remote_address() . get_user_remote_authsource()));
			if (isAjax()) {
				echo "showajaxmessage('{$_SESSION['Login_Error']}');";
				return;
			}
		}
	}

	/* Show login page if they aren't logged in */
	if (empty($_SESSION['Logged_In'])) {
		phpsession_end(true);
		return false;
	}

	/* If session timeout isn't set, we don't mark sessions stale */
	if (!isset($config['system']['webgui']['session_timeout'])) {
		/* Default to 4 hour timeout if one is not set */
		if ($_SESSION['last_access'] < (time() - 14400)) {
			$_POST['logout'] = true;
			$_SESSION['Logout'] = true;
		} else {
			$_SESSION['last_access'] = time();
		}
	} else if (intval($config['system']['webgui']['session_timeout']) == 0) {
		/* only update if it wasn't ajax */
		if (!isAjax()) {
			$_SESSION['last_access'] = time();
		}
	} else {
		/* Check for stale session */
		if ($_SESSION['last_access'] < (time() - ($config['system']['webgui']['session_timeout'] * 60))) {
			$_POST['logout'] = true;
			$_SESSION['Logout'] = true;
		} else {
			/* only update if it wasn't ajax */
			if (!isAjax()) {
				$_SESSION['last_access'] = time();
			}
		}
	}

	/* user hit the logout button */
	if (isset($_POST['logout'])) {

		if ($_SESSION['Logout']) {
			log_error(sprintf(gettext("Session timed out for user '%1\$s' from: %2\$s"), $_SESSION['Username'], get_user_remote_address() . get_user_remote_authsource()));
		} else {
			log_error(sprintf(gettext("User logged out for user '%1\$s' from: %2\$s"), $_SESSION['Username'], get_user_remote_address() . get_user_remote_authsource()));
		}

		/* wipe out $_SESSION */
		$_SESSION = array();

		if (isset($_COOKIE[session_name()])) {
			setcookie(session_name(), '', time()-42000, '/');
		}

		/* and destroy it */
		phpsession_destroy();

		$scriptName = explode("/", $_SERVER["SCRIPT_FILENAME"]);
		$scriptElms = count($scriptName);
		$scriptName = $scriptName[$scriptElms-1];

		if (isAjax()) {
			return false;
		}

		/* redirect to page the user is on, it'll prompt them to login again */
		header("Location: {$scriptName}");

		return false;
	}

	/*
	 * this is for debugging purpose if you do not want to use Ajax
	 * to submit a HTML form. It basically disables the observation
	 * of the submit event and hence does not trigger Ajax.
	 */
	if ($_REQUEST['disable_ajax']) {
		$_SESSION['NO_AJAX'] = "True";
	}

	/*
	 * Same to re-enable Ajax.
	 */
	if ($_REQUEST['enable_ajax']) {
		unset($_SESSION['NO_AJAX']);
	}
	phpsession_end(true);
	return true;
}

function print_credit() {
	global $g;

	return  '<a target="_blank" href="https://pfsense.org">' . $g["product_name"] . '</a>' .
			gettext(' is developed and maintained by ') .
			'<a target="_blank" href="https://netgate.com">Netgate. </a>' . ' &copy; ESF ' . $g["product_copyright_years"] .
			'<a target="_blank" href="https://pfsense.org/license">' .
			gettext(' View license.') . '</a>';
}
function get_user_remote_address() {
	$remote_address = $_SERVER['REMOTE_ADDR'];
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$remote_address .= "[{$_SERVER['HTTP_CLIENT_IP']}]";
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$remote_address .= "[{$_SERVER['HTTP_X_FORWARDED_FOR']}]";
	}
	return $remote_address;
}
function get_user_remote_authsource() {
	$authsource = "";
	if (!empty($_SESSION['authsource'])) {
		$authsource .= " ({$_SESSION['authsource']})";
	}
	return $authsource;
}

function set_pam_auth() {
	global $config;

	$authcfg = auth_get_authserver($config['system']['webgui']['authmode']);

	unlink_if_exists("/etc/radius.conf");
	unlink_if_exists("/var/etc/pam_ldap.conf");
	unlink_if_exists("/var/etc/pam_ldap_ca.crt");

	$header = "# This file is automatically generated. Do not edit.\n\n";
	$pam_sshd =<<<EOD
# auth
auth		required	pam_unix.so		no_warn try_first_pass

# account
account		required	pam_nologin.so
account		required	pam_login_access.so
account		required	pam_unix.so

# session
session		required	pam_permit.so

# password
password	required	pam_unix.so		no_warn try_first_pass

EOD;

	$pam_system =<<<EOD
# auth
auth		required	pam_unix.so		no_warn try_first_pass

# account
account		required	pam_login_access.so
account		required	pam_unix.so

# session
session		required	pam_lastlog.so		no_fail

# password
password	required	pam_unix.so		no_warn try_first_pass

EOD;

	$nsswitch =<<<EOD
group: files
hosts: files dns
netgroup: files
networks: files
passwd: files
shells: files
services: files
protocols: files
rpc: files

EOD;

	if (isset($config['system']['webgui']['shellauth'])) {
		if (($authcfg['type'] == "radius") && isset($authcfg['radius_auth_port'])) {
			$radius_conf = "auth {$authcfg['host']}:{$authcfg['radius_auth_port']} " .
					"{$authcfg['radius_secret']} {$authcfg['radius_timeout']}\n";
			if (isset($authcfg['radius_acct_port'])) {
				$radius_conf .= "acct {$authcfg['host']}:{$authcfg['radius_acct_port']} " .
					"{$authcfg['radius_secret']} {$authcfg['radius_timeout']}\n";
			}
			
			$pam_sshd =<<<EOD
# auth
auth            sufficient      pam_radius.so
auth		required	pam_unix.so		no_warn try_first_pass

# account
account		required	pam_nologin.so
account		required	pam_login_access.so
account         sufficient      pam_radius.so

# session
session		required	pam_permit.so

# password
password        sufficient      pam_radius.so
password	required	pam_unix.so		no_warn try_first_pass

EOD;

			$pam_system =<<<EOD
# auth
auth            sufficient      pam_radius.so
auth		required	pam_unix.so		no_warn try_first_pass

# account
account		required	pam_login_access.so
account         sufficient      pam_radius.so

# session
session		required	pam_lastlog.so		no_fail

# password
password        sufficient      pam_radius.so
password	required	pam_unix.so		no_warn try_first_pass

EOD;

			@file_put_contents("/etc/radius.conf", $header . $radius_conf);
		} elseif (($authcfg['type'] == "ldap") && !empty($authcfg['ldap_pam_groupdn'])) {
			// do not try to reconnect
			$ldapconf = "bind_policy soft\n";
			// Bind/connect timelimit
			$ldapconf = "bind_timelimit {$authcfg['ldap_timeout']}\n";
			$uri = ($authcfg['ldap_urltype'] == 'SSL/TLS Encrypted') ? 'ldaps' : 'ldap';
			$ldapconf .= "uri {$uri}://{$authcfg['host']}/\n";
			$ldapconf .= "port {$authcfg['ldap_port']}\n";
			if ($authcfg['ldap_urltype'] == 'STARTTLS Encrypted')  {
				$ldapconf .= "ssl start_tls\n";
			} elseif ($authcfg['ldap_urltype'] == 'SSL/TLS Encrypted')  {
				$ldapconf .= "ssl on\n";
			}
			if ($authcfg['ldap_urltype'] != 'Standard TCP') {
				if ($authcfg['ldap_caref'] == 'global') {
					$ldapconf .= "tls_cacertfile /etc/ssl/cert.pem\n";
				} else {
					$ca = array();
					$ldappamcafile = "/var/etc/pam_ldap_ca.crt";
					$ca['caref'] = $authcfg['ldap_caref'];
					$cacrt = ca_chain($ca);
					@file_put_contents($ldappamcafile, $cacrt); 
					$ldapconf .= "tls_cacertfile {$ldappamcafile}\n";
				}
				$ldapconf .= "tls_checkpeer yes\n";
			}
			$ldapconf .= "ldap_version {$authcfg['ldap_protver']}\n";
			$ldapconf .= "timelimit {$authcfg['ldap_timeout']}\n";
			$ldapconf .= "base {$authcfg['ldap_basedn']}\n";
			$scope = ($authcfg['ldap_scope' == 'one']) ? 'one' : 'sub';
			$ldapconf .= "scope {$scope}\n";
			$ldapconf .= "binddn {$authcfg['ldap_binddn']}\n";
			$ldapconf .= "bindpw {$authcfg['ldap_bindpw']}\n";
			$ldapconf .= "pam_login_attribute {$authcfg['ldap_attr_user']}\n";
			$ldapconf .= "pam_member_attribute {$authcfg['ldap_attr_member']}\n";
			//$ldapconf .= "pam_filter objectclass={$authcfg['ldap_attr_user']}\n";
			$ldapconf .= "pam_groupdn {$authcfg['ldap_pam_groupdn']}\n";
			//$ldapconf .= "pam_password ad\n";

			$pam_sshd =<<<EOD
# auth
auth            sufficient      /usr/local/lib/pam_ldap.so	config=/var/etc/pam_ldap.conf
auth		required	pam_unix.so		no_warn try_first_pass

# account
account		required	pam_nologin.so
account		required	pam_login_access.so
account         sufficient      /usr/local/lib/pam_ldap.so	ignore_authinfo_unavail ignore_unknown_user config=/var/etc/pam_ldap.conf

# session
session		required	/usr/local/lib/pam_mkhomedir.so	umask=0077 skel=/etc/skel/ silent
session		sufficient	/usr/local/lib/pam_ldap.so	config=/var/etc/pam_ldap.conf
session		required	pam_permit.so

# password
password        sufficient      /usr/local/lib/pam_ldap.so	config=/var/etc/pam_ldap.conf
password	required	pam_unix.so		no_warn try_first_pass

EOD;

			$pam_system =<<<EOD
# auth
auth            sufficient      /usr/local/lib/pam_ldap.so	config=/var/etc/pam_ldap.conf
auth		required	pam_unix.so		no_warn try_first_pass

# account
account		required	pam_login_access.so
account         sufficient      pam_radius.so
account         sufficient      /usr/local/lib/pam_ldap.so	ignore_authinfo_unavail ignore_unknown_user config=/var/etc/pam_ldap.conf

# session
session		required	/usr/local/lib/pam_mkhomedir.so	umask=0077 skel=/etc/skel/ silent
session		sufficient	/usr/local/lib/pam_ldap.so	config=/var/etc/pam_ldap.conf
session		required	pam_lastlog.so		no_fail

# password
password        sufficient      /usr/local/lib/pam_ldap.so	config=/var/etc/pam_ldap.conf
password	required	pam_unix.so		no_warn try_first_pass

EOD;

			$nsswitch =<<<EOD
group: files ldap
hosts: files dns
netgroup: files
networks: files
passwd: files ldap
shells: files
services: files
protocols: files
rpc: files

EOD;

			@file_put_contents("/var/etc/pam_ldap.conf", $ldapconf);
			@chmod("/var/etc/pam_ldap.conf", 0600);
			@unlink_if_exists("/usr/local/etc/nss_ldap.conf");
			@symlink("/var/etc/pam_ldap.conf", "/usr/local/etc/nss_ldap.conf");
		}
	}

	@file_put_contents("/etc/pam.d/sshd", $header . $pam_sshd);
	@file_put_contents("/etc/pam.d/system", $header . $pam_system);
	@file_put_contents("/etc/nsswitch.conf", $header . $nsswitch);
}
?>
