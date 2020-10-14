<?php
/*
 * pkg-utils.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2005-2006 Colin Smith (ethethlay@gmail.com)
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

require_once("globals.inc");
require_once("service-utils.inc");

if (file_exists("/cf/conf/use_xmlreader")) {
	require_once("xmlreader.inc");
} else {
	require_once("xmlparse.inc");
}

require_once("pfsense-utils.inc");

if (!function_exists("pkg_debug")) {
	/* set up logging if needed */
	function pkg_debug($msg) {
		global $g, $debug, $fd_log;

		if (!$debug) {
			return;
		}

		if (!$fd_log) {
			$fd_log = fopen("{$g['tmp_path']}/pkg_mgr_debug.log",
			    "w");
		}

		if (!$fd_log) {
			update_status(gettext("Warning, could not open log " .
			    "for writing.") . "\n");
			return;
		}
		@fwrite($fd_log, $msg);
	}
}

/* Validate if pkg name is valid */
function pkg_valid_name($pkgname) {
	global $g;

	$pattern = "/^{$g['pkg_prefix']}[a-zA-Z0-9\.\-_]+$/";
	return preg_match($pattern, $pkgname);
}

/* Remove pkg_prefix from package name if it's present */
function pkg_remove_prefix(&$pkg_name) {
	global $g;

	if (substr($pkg_name, 0, strlen($g['pkg_prefix'])) ==
	    $g['pkg_prefix']) {
		$pkg_name = substr($pkg_name, strlen($g['pkg_prefix']));
	}
}

/* Execute pkg update when it's necessary */
function pkg_update($force = false) {
	global $g;

	return pkg_call("update" . ($force ? " -f" : ""));
}

/* return an array with necessary environment vars for pkg */
function pkg_env($extra_env = array()) {
	global $config, $g;

	$user_agent = $g['product_name'] . '/' . $g['product_version'];
	if (!isset($config['system']['do_not_send_uniqueid'])) {
		$user_agent .= ':' . system_get_uniqueid();
	}

	$pkg_env_vars = array(
		"LANG" => "C",
		"HTTP_USER_AGENT" => $user_agent,
		"ASSUME_ALWAYS_YES" => "true",
		"FETCH_TIMEOUT" => 5,
		"FETCH_RETRY" => 2
	);

	if (!empty($config['system']['proxyurl'])) {
		$http_proxy = $config['system']['proxyurl'];
		if (!empty($config['system']['proxyport'])) {
			$http_proxy .= ':' . $config['system']['proxyport'];
		}
		$pkg_env_vars['HTTP_PROXY'] = $http_proxy;

		if (!empty($config['system']['proxyuser']) &&
		    !empty($config['system']['proxypass'])) {
			$pkg_env_vars['HTTP_PROXY_AUTH'] = "basic:*:" .
			    $config['system']['proxyuser'] . ":" .
			    $config['system']['proxypass'];
		}
	}

	if (isset($config['system']['use_mfs_tmpvar']) &&
	    !file_exists("/conf/ram_disks_failed")) {
		$pkg_env_vars['PKG_DBDIR'] = '/root/var/db/pkg';
		$pkg_env_vars['PKG_CACHEDIR'] = '/root/var/cache/pkg';
	}

	foreach ($extra_env as $key => $value) {
		$pkg_env_vars[$key] = $value;
	}

	return $pkg_env_vars;
}

/* Execute a pkg call */
function pkg_call($params, $mute = false, $extra_env = array()) {
	global $g, $config;

	if (empty($params)) {
		return false;
	}

	$descriptorspec = array(
		1 => array("pipe", "w"), /* stdout */
		2 => array("pipe", "w")	 /* stderr */
	);

	pkg_debug("pkg_call(): {$params}\n");
	$process = proc_open("/usr/local/sbin/pkg-static {$params}",
	    $descriptorspec, $pipes, '/', pkg_env($extra_env));

	if (!is_resource($process)) {
		return false;
	}

	stream_set_blocking($pipes[1], 0);
	stream_set_blocking($pipes[2], 0);

	/* XXX: should be a tunable? */
	$timeout = 60; // seconds
	$error_log = '';

	do {
		$write = array();
		$read = array($pipes[1], $pipes[2]);
		$except = array();

		$stream = @stream_select($read, $write, $except, $timeout);
		if ($stream !== FALSE && $stream > 0) {
			foreach ($read as $pipe) {
				$content = stream_get_contents($pipe);
				if ($content == '') {
					continue;
				}
				if ($pipe === $pipes[1]) {
					if (!$mute) {
						update_status($content);
					}
					flush();
				} else if ($pipe === $pipes[2]) {
					$error_log .= $content;
				}
			}
		}

		$status = proc_get_status($process);
	} while ($status['running']);

	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($process);


	$rc = $status['exitcode'];

	pkg_debug("pkg_call(): rc = {$rc}\n");
	if ($rc == 0) {
		return true;
	}

	pkg_debug("pkg_call(): error_log\n{$error_log}\n");
	if (!$mute) {
		update_status("\n\n" . sprintf(gettext("ERROR!!! An error " .
		    "occurred on pkg execution (rc = %d) with parameters " .
		    "'%s':"), $rc, $params) . "\n" . $error_log . "\n");
	}

	return false;
}

/* Execute pkg with $params, fill stdout and stderr and return pkg rc */
function pkg_exec($params, &$stdout, &$stderr, $extra_env = array()) {
	global $g, $config;

	if (empty($params)) {
		return -1;
	}

	$descriptorspec = array(
		1 => array("pipe", "w"), /* stdout */
		2 => array("pipe", "w")	 /* stderr */
	);


	pkg_debug("pkg_exec(): {$params}\n");
	$process = proc_open("/usr/local/sbin/pkg-static {$params}",
	    $descriptorspec, $pipes, '/', pkg_env($extra_env));

	if (!is_resource($process)) {
		return -1;
	}

	$stdout = '';
	while (($l = fgets($pipes[1])) !== FALSE) {
		$stdout .= $l;
	}
	fclose($pipes[1]);

	$stderr = '';
	while (($l = fgets($pipes[2])) !== FALSE) {
		$stderr .= $l;
	}
	fclose($pipes[2]);


	return proc_close($process);
}

/* Compare 2 pkg versions and return:
 * '=' - versions are the same
 * '>' - $v1 > $v2
 * '<' - $v1 < $v2
 * '?' - Error
 */
function pkg_version_compare($v1, $v2) {
	if (empty($v1) || empty($v2)) {
		return '?';
	}

	$rc = pkg_exec("version -t '{$v1}' '{$v2}'", $stdout, $stderr);

	if ($rc != 0) {
		return '?';
	}

	return str_replace("\n", "", $stdout);
}

/* Check if package is installed */
function is_pkg_installed($pkg_name) {
	global $g;

	if (empty($pkg_name)) {
		return false;
	}

	return pkg_call("info -e " . $pkg_name, true);
}

/* Install package, $pkg_name should not contain prefix */
function pkg_install($pkg_name, $force = false) {
	global $g;
	$result = false;

	$shortname = $pkg_name;
	pkg_remove_prefix($shortname);

	$pkg_force = "";
	if ($force) {
		$pkg_force = "-f ";
	}

	pkg_debug("Installing package {$shortname}\n");
	if ($force || !is_pkg_installed($pkg_name)) {
		$result = pkg_call("install -y " . $pkg_force . $pkg_name);
		/* Cleanup cache to free disk space */
		pkg_call("clean -y");
	}

	return $result;
}

/* Delete package from FreeBSD, $pkg_name should not contain prefix */
function pkg_delete($pkg_name) {
	global $g;

	$shortname = $pkg_name;
	pkg_remove_prefix($shortname);

	pkg_debug("Removing package {$shortname}\n");
	if (is_pkg_installed($pkg_name)) {
		pkg_call("delete -y " . $pkg_name);
		/* Cleanup unnecessary dependencies */
		pkg_call("autoremove -y");
	}
}

/* Check if package is present in config.xml */
function is_package_installed($package_name) {
	return (get_package_id($package_name) != -1);
}

/* Find package array index */
function get_package_id($package_name) {
	global $config;

	if (!isset($config['installedpackages']['package']) ||
	    !is_array($config['installedpackages']['package'])) {
		return -1;
	}

	foreach ($config['installedpackages']['package'] as $idx => $pkg) {
		if ($pkg['name'] == $package_name ||
		    get_package_internal_name($pkg) == $package_name) {
			return $idx;
		}
	}

	return -1;
}

/* Return internal_name when it's defined, otherwise, returns name */
function get_package_internal_name($package_data) {
	if (isset($package_data['internal_name']) &&
	    ($package_data['internal_name'] != "")) {
		/* e.g. name is Ipguard-dev, internal name is ipguard */
		return $package_data['internal_name'];
	} else {
		return $package_data['name'];
	}
}

// Get information about packages.
function get_pkg_info($pkgs = 'all', $remote_repo_usage_disabled = false,
    $installed_pkgs_only = false) {
	global $g, $input_errors;

	$out = $err = $extra_param = '';
	$rc = 0;

	unset($pkg_filter);

	if (is_array($pkgs)) {
		$pkg_filter = $pkgs;
		$pkgs = $g['pkg_prefix'] . '*';
	} elseif ($pkgs == 'all') {
		$pkgs = $g['pkg_prefix'] . '*';
	}

	$base_packages = (substr($pkgs, 0, strlen($g['pkg_prefix'])) !=
	    $g['pkg_prefix']);

	if ($installed_pkgs_only && !is_pkg_installed($pkgs)) {
		/*
		 * Return early if the caller wants just installed packages
		 * and there are none.  Saves doing any calls that might
		 * access a remote package repo.
		 */
		return array();
	}

	if (!function_exists('is_subsystem_dirty')) {
		require_once("util.inc");
	}

	/* Do not run remote operations if pkg has a lock */
	if (is_subsystem_dirty('pkg')) {
		$remote_repo_usage_disabled = true;
		$lock = false;
	} else {
		$lock = true;
	}

	if ($lock) {
		mark_subsystem_dirty('pkg');
	}

	if ($remote_repo_usage_disabled) {
		$extra_param = "-U ";
	}

	$did_search = false;
	$search_rc = 0;
	$info_rc = 0;
	$search_items = array();
	$info_items = array();

	if ($base_packages) {
		$repo_param = "";
	} else {
		$repo_param = "-r {$g['product_name']}";
	}

	/*
	 * If we want more than just the currently installed packages or
	 * we want up-to-date remote repo info then do a full pkg search
	 */
	if (!$installed_pkgs_only || !$remote_repo_usage_disabled) {
		$did_search = true;
		$search_rc = pkg_exec("search {$repo_param} " .
		    "{$extra_param}-R --raw-format json-compact " .
		    $pkgs, $search_out, $search_err);
		if ($search_rc == 0) {
			$search_items = explode("\n", chop($search_out));
			array_walk($search_items, function(&$v, &$k) {
				$v = json_decode($v, true);
			});
		}
	}

	/*
	 * We always should look for local items to detect packages that
	 * were removed from remote repo but are already installed locally
	 *
	 * Take pkg search return code into consideration to fallback to local
	 * information when remote repo is not accessible
	 */
	if (is_pkg_installed($pkgs) || $search_rc != 0) {
		$info_rc = pkg_exec("info -R --raw-format json-compact " .
		    $pkgs, $info_out, $info_err);
		if ($info_rc == 0) {
			$info_items = explode("\n", chop($info_out));
			array_walk($info_items, function(&$v, &$k) {
				$v = json_decode($v, true);
			});
		}
	}

	if ($lock) {
		clear_subsystem_dirty('pkg');
	}

	if ($search_rc != 0 && $info_rc != 0) {
		update_status("\n" . gettext(
		    "ERROR: Error trying to get packages list. Aborting...")
		    . "\n");
		update_status($search_err . "\n" . $info_err);
		$input_errors[] = gettext(
		    "ERROR: Error trying to get packages list. Aborting...") .
		    "\n";
		$input_errors[] = $search_err . "\n" . $info_err;
		return array();
	}

	/* It was not possible to search, use local information only */
	if ($search_rc != 0 || !$did_search) {
		$search_items = $info_items;
	} else {
		foreach ($info_items as $pkg_info) {
			if (empty($pkg_info['name'])) {
				continue;
			}

			if (array_search($pkg_info['name'], array_column(
			    $search_items, 'name')) === FALSE) {
				$pkg_info['obsolete'] = true;
				$search_items[] = $pkg_info;
			}
		}
	}

	$result = array();
	foreach ($search_items as $pkg_info) {
		if (empty($pkg_info['name'])) {
			continue;
		}

		if (isset($pkg_filter) && !in_array($pkg_info['name'],
		    $pkg_filter)) {
			continue;
		}

		$pkg_info['shortname'] = $pkg_info['name'];
		pkg_remove_prefix($pkg_info['shortname']);

		/* XXX: Add it to globals.inc? */
		$pkg_info['changeloglink'] =
		    "https://github.com/pfsense/FreeBSD-ports/commits/devel/" .
		    $pkg_info['categories'][0] . '/' . $pkg_info['name'];

		$pkg_is_installed = false;

		if (is_pkg_installed($pkg_info['name'])) {
			$rc = pkg_exec("query %R {$pkg_info['name']}", $out,
			    $err);
			if (!$base_packages &&
			    rtrim($out) != $g['product_name']) {
				continue;
			}

			$pkg_info['installed'] = true;
			$pkg_is_installed = true;

			$rc = pkg_exec("query %v {$pkg_info['name']}", $out,
			    $err);

			if ($rc != 0) {
				update_status("\n" . gettext("ERROR: Error " .
				    "trying to get package version. " .
				    "Aborting...") . "\n");
				update_status($err);
				$input_errors[] = gettext("ERROR: Error " .
				    "trying to get package version. " .
				    "Aborting...") . "\n";
				$input_errors[] = $err;
				return array();
			}

			$pkg_info['installed_version'] = str_replace("\n", "",
			    $out);

			/*
			 * We used pkg info to collect pkg data so remote
			 * version is not available. Lets try to collect it
			 * using rquery if possible
			 */
			if ($search_rc != 0 || !$did_search) {
				$rc = pkg_exec(
				    "rquery -U %v {$pkg_info['name']}", $out,
				    $err);

				if ($rc == 0) {
					$pkg_info['version'] =
					    str_replace("\n", "", $out);
				}
			}

		} else if (is_package_installed($pkg_info['shortname'])) {
			$pkg_info['broken'] = true;
			$pkg_is_installed = true;
		}

		$pkg_info['desc'] = preg_replace('/\n+WWW:.*$/', '',
		    $pkg_info['desc']);

		if (!$installed_pkgs_only || $pkg_is_installed) {
			$result[] = $pkg_info;
		}
		unset($pkg_info);
	}

	/* Sort result alphabetically */
	usort($result, function($a, $b) {
		return(strcasecmp ($a['name'], $b['name']));
	});

	return $result;
}

/*
 * If binary pkg is installed but post-install tasks were not
 * executed yet, do it now.
 * This scenario can happen when a pkg is pre-installed during
 * build phase, and at this point, cannot find a running system
 * to register itself in config.xml and also execute custom
 * install functions
 */
function register_all_installed_packages() {
	global $g, $config, $pkg_interface;

	$pkg_info = get_pkg_info('all', true, true);

	foreach ($pkg_info as $pkg) {
		pkg_remove_prefix($pkg['name']);

		if (is_package_installed($pkg['name'])) {
			continue;
		}

		update_status(sprintf(gettext(
		    "Running last steps of %s installation.") . "\n",
		    $pkg['name']));
		install_package_xml($pkg['name']);
	}
}

/*
 * resync_all_package_configs() Force packages to setup their configuration
 * and rc.d files.  This function may also print output to the terminal
 * indicating progress.
 */
function resync_all_package_configs($show_message = false) {
	global $config, $pkg_interface, $g;

	log_error(gettext("Resyncing configuration for all packages."));

	if (!isset($config['installedpackages']['package']) ||
	    !is_array($config['installedpackages']['package'])) {
		return;
	}

	if ($show_message == true) {
		echo "Syncing packages:";
	}


	foreach ($config['installedpackages']['package'] as $idx => $package) {
		if (empty($package['name'])) {
			continue;
		}
		if ($show_message == true) {
			echo " " . $package['name'];
		}
		if (platform_booting() != true) {
			stop_service(get_package_internal_name($package));
		}
		sync_package($package['name']);
		update_status(gettext("Syncing packages...") . "\n");
	}

	if ($show_message == true) {
		echo " done.\n";
	}
}

function uninstall_package($package_name) {
	global $config;

	$internal_name = $package_name;
	$id = get_package_id($package_name);
	if ($id >= 0) {
		$internal_name = get_package_internal_name(
		    $config['installedpackages']['package'][$id]);
		stop_service($internal_name);
	}
	$pkg_name = $g['pkg_prefix'] . $internal_name;

	if (is_pkg_installed($pkg_name)) {
		update_status(gettext("Removing package...") . "\n");
		pkg_delete($pkg_name);
	} else {
		delete_package_xml($package_name);
	}

	update_status(gettext("done.") . "\n");
}

function reinstall_package($package_name) {
	global $config, $g;

	$internal_name = $package_name;
	$id = get_package_id($package_name);
	if ($id >= 0) {
		$internal_name = get_package_internal_name(
		    $config['installedpackages']['package'][$id]);
	}
	$pkg_name = $g['pkg_prefix'] . $internal_name;
	pkg_install($pkg_name);
}

/* Run <custom_php_resync_config_command> */
function sync_package($package_name) {
	global $config, $builder_package_install;

	// If this code is being called by pfspkg_installer
	// which the builder system uses then return (ignore).
	if ($builder_package_install) {
		return;
	}

	if (empty($config['installedpackages']['package'])) {
		return;
	}

	if (($pkg_id = get_package_id($package_name)) == -1) {
		// This package doesn't really exist - exit the function.
		return;
	}

	if (!is_array($config['installedpackages']['package'][$pkg_id])) {
		// No package belongs to the pkg_id passed to this function.
		return;
	}

	$package = &$config['installedpackages']['package'][$pkg_id];
	if (!file_exists("/usr/local/pkg/" . $package['configurationfile'])) {
		log_error(sprintf(gettext("The %s package is missing its " .
		    "configuration file and must be reinstalled."),
		    $package['name']));
		delete_package_xml($package['name']);
		return;
	}

	$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" .
	    $package['configurationfile'], "packagegui");
	if (isset($pkg_config['nosync'])) {
		return;
	}

	/* Bring in package include files */
	if (!empty($pkg_config['include_file'])) {
		$include_file = $pkg_config['include_file'];
		if (file_exists($include_file)) {
			require_once($include_file);
		} else {
			log_error(sprintf(gettext('Reinstalling package %1$s " .
			    "because its include file(%2$s) is missing!'),
			    $package['name'], $include_file));
			uninstall_package($package['name']);
			if (reinstall_package($package['name']) != 0) {
				log_error(sprintf(gettext("Reinstalling " .
				    "package %s failed. Take appropriate " .
				    "measures!!!"), $package['name']));
				return;
			}
			if (file_exists($include_file)) {
				require_once($include_file);
			} else {
				return;
			}
		}
	}

	if (!empty($pkg_config['custom_php_global_functions'])) {
		eval($pkg_config['custom_php_global_functions']);
	}
	if (!empty($pkg_config['custom_php_resync_config_command'])) {
		eval($pkg_config['custom_php_resync_config_command']);
	}
}

/* Read info.xml installed by package and return an array */
function read_package_config($package_name) {
	global $g;

	$pkg_info_xml = '/usr/local/share/' . $g['pkg_prefix'] . $package_name .
	    '/info.xml';

	if (!file_exists($pkg_info_xml)) {
		return false;
	}

	$pkg_info = parse_xml_config_pkg($pkg_info_xml, 'pfsensepkgs');

	if (empty($pkg_info)) {
		return false;
	}

	/* it always returns an array with 1 item */
	return $pkg_info['package'][0];
}

/* Read package configurationfile and return an array */
function read_package_configurationfile($package_name) {
	global $config, $g;

	$pkg_config = array();
	$id = get_package_id($package_name);

	if ($id < 0 || !isset($config['installedpackages']['package'][$id])) {
		return $pkg_config;
	}

	$pkg_data = $config['installedpackages']['package'][$id];

	if (empty($pkg_data['configurationfile'])) {
		return $pkg_config;
	}

	if (!file_exists('/usr/local/pkg/' . $pkg_data['configurationfile'])) {
		return $pkg_config;
	}

	$pkg_config = parse_xml_config_pkg('/usr/local/pkg/' .
	    $pkg_data['configurationfile'], "packagegui");

	return $pkg_config;
}

function get_after_install_info($package_name) {
	$pkg_config = read_package_config($package_name);

	if (isset($pkg_config['after_install_info'])) {
		return $pkg_config['after_install_info'];
	}

	return '';
}

function eval_once($toeval) {
	global $evaled;
	if (!$evaled) {
		$evaled = array();
	}
	$evalmd5 = md5($toeval);
	if (!in_array($evalmd5, $evaled)) {
		@eval($toeval);
		$evaled[] = $evalmd5;
	}
	return;
}

function install_package_xml($package_name) {
	global $g, $config, $pkg_interface;

	if (($pkg_info = read_package_config($package_name)) == false) {
		return false;
	}

	pkg_debug(gettext("Beginning package installation.") . "\n");
	log_error(sprintf(gettext('Beginning package installation for %s .'),
	    $pkg_info['name']));

	/* add package information to config.xml */
	$pkgid = get_package_id($pkg_info['name']);
	update_status(gettext("Saving updated package information...") . "\n");
	if ($pkgid == -1) {
		init_config_arr(array('installedpackages', 'package'));
		$config['installedpackages']['package'][] = $pkg_info;
		$changedesc = sprintf(gettext("Installed %s package."),
		    $pkg_info['name']);
		$to_output = gettext("done.") . "\n";
	} else {
		init_config_arr(array('installedpackages', 'package', $pkgid));
		$config['installedpackages']['package'][$pkgid] = $pkg_info;
		$changedesc = sprintf(gettext("Overwrote previous " .
		    "installation of %s."), $pkg_info['name']);
		$to_output = gettext("overwrite!") . "\n";
	}
	write_config(sprintf(gettext("Intermediate config write during " .
	    "package install for %s."), $pkg_info['name']));
	update_status($to_output);

	if (($pkgid = get_package_id($package_name)) == -1) {
		update_status(sprintf(gettext('The %1$s package is not " .
		    "installed.%2$sInstallation aborted.'), $package_name,
		    "\n\n"));

		uninstall_package($package_name);
		write_config($changedesc);
		log_error(sprintf(gettext("Failed to install package: %s."),
		    $pkg_info['name']));
		update_status(gettext("Failed to install package.") . "\n");
		return false;
	}

	if (!file_exists("/usr/local/pkg/" . $pkg_info['configurationfile'])) {
		pkg_debug("Unable to find config file\n");
		update_status(gettext("Loading package configuration... " .
		    "failed!") .  "\n\n" . gettext("Installation aborted."));
		pkg_debug(gettext("Unable to load package configuration. " .
		    "Installation aborted.") ."\n");

		uninstall_package($package_name);
		write_config($changedesc);
		log_error(sprintf(gettext("Failed to install package: %s."),
		    $pkg_info['name']));
		update_status(gettext("Failed to install package.") . "\n");
		return false;
	}

	$pkg_data = &$config['installedpackages']['package'][$pkgid];

	update_status(gettext("Loading package configuration... "));
	$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" .
	    $pkg_info['configurationfile'], "packagegui");
	update_status(gettext("done.") . "\n");
	update_status(gettext("Configuring package components...") .
	    "\n");
	if (!empty($pkg_config['filter_rules_needed'])) {
		$pkg_data['filter_rule_function'] =
		    $pkg_config['filter_rules_needed'];
	}
	/* modify system files */

	/* if a require exists, include it.  this will
	 * show us where an error exists in a package
	 * instead of making us blindly guess
	 */
	$missing_include = false;
	if ($pkg_config['include_file'] <> "") {
		update_status(gettext("Loading package instructions...") .
		    "\n");
		if (file_exists($pkg_config['include_file'])) {
			pkg_debug("require_once('" .
			    $pkg_config['include_file'] . "')\n");
			require_once($pkg_config['include_file']);
		} else {
			pkg_debug("Missing include " .
			    "{$pkg_config['include_file']}\n");
			$missing_include = true;
			update_status(sprintf(gettext("Include %s is missing!"),
			    basename($pkg_config['include_file'])) . "\n");

			uninstall_package($package_name);
			write_config($changedesc);
			log_error(sprintf(gettext(
			    "Failed to install package: %s."),
			    $pkg_info['name']));
			update_status(gettext("Failed to install package.") .
			    "\n");
			return false;
		}
	}

	/* custom commands */
	update_status(gettext("Custom commands...") . "\n");
	if ($missing_include == false) {
		if ($pkg_config['custom_php_global_functions'] <> "") {
			update_status(gettext(
			    "Executing custom_php_global_functions()..."));
			eval_once($pkg_config['custom_php_global_functions']);
			update_status(gettext("done.") . "\n");
		}
		if ($pkg_config['custom_php_install_command']) {
			update_status(gettext(
			    "Executing custom_php_install_command()..."));
			eval_once($pkg_config['custom_php_install_command']);
			update_status(gettext("done.") . "\n");
		}
		if ($pkg_config['custom_php_resync_config_command'] <> "") {
			update_status(gettext(
			    "Executing custom_php_resync_config_command()..."));
			eval_once(
			    $pkg_config['custom_php_resync_config_command']);
			update_status(gettext("done.") . "\n");
		}
	}
	/* sidebar items */
	init_config_arr(array('installedpackages', 'menu'));
	if (is_array($pkg_config['menu'])) {
		update_status(gettext("Menu items... "));
		foreach ($pkg_config['menu'] as $menu) {
			foreach ($config['installedpackages']['menu'] as
			    $amenu) {
				if ($amenu['name'] == $menu['name']) {
					continue 2;
				}
			}
			$config['installedpackages']['menu'][] = $menu;
		}
		update_status(gettext("done.") . "\n");
	}
	/* services */
	init_config_arr(array('installedpackages', 'service'));
	if (is_array($pkg_config['service'])) {
		update_status(gettext("Services... "));
		foreach ($pkg_config['service'] as $service) {
			foreach ($config['installedpackages']['service'] as
			    $aservice) {
				if ($aservice['name'] == $service['name']) {
					continue 2;
				}
			}
			$config['installedpackages']['service'][] = $service;
		}
		update_status(gettext("done.") . "\n");
	}
	if (is_array($pkg_config['tabs'])) {
		$pkg_data['tabs'] = $pkg_config['tabs'];
	}
	/* plugins */
	if (isset($pkg_config['include_file'])) {
		$pkg_data['include_file'] = $pkg_config['include_file'];
	}
	if (is_array($pkg_config['plugins']['item'])) {
		$pkg_data['plugins']['item'] = $pkg_config['plugins']['item'];
	}

	update_status(gettext("Writing configuration... "));
	write_config($changedesc);
	log_error(sprintf(gettext("Successfully installed package: %s."),
	    $pkg_info['name']));
	update_status(gettext("done.") . "\n");
	if ($pkg_info['after_install_info']) {
		update_status($pkg_info['after_install_info']);
	}

	/* set up package logging streams */
	if ($pkg_info['logging']) {
		system_syslogd_start(true);
	}

	return true;
}

function delete_package_xml($package_name, $when = "post-deinstall") {
	global $g, $config, $pkg_interface;


	$pkgid = get_package_id($package_name);
	if ($pkgid == -1) {
		update_status(sprintf(gettext('The %1$s package is not " .
		    "installed.%2$sDeletion aborted.'), $package_name, "\n\n"));
		ob_flush();
		sleep(1);
		return;
	}
	pkg_debug(sprintf(gettext("Removing %s package... "), $package_name));
	update_status(sprintf(gettext("Removing %s components..."),
	    $package_name) . "\n");
	/* parse package configuration */
	init_config_arr(array('installedpackages', 'package', $pkgid));
	$pkg_info = $config['installedpackages']['package'][$pkgid];
	init_config_arr(array('installedpackages', 'menu'));
	$menus = &$config['installedpackages']['menu'];
	init_config_arr(array('installedpackages', 'service'));
	$services = &$config['installedpackages']['service'];
	if (file_exists("/usr/local/pkg/" . $pkg_info['configurationfile'])) {
		$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" .
		    $pkg_info['configurationfile'], "packagegui");
		/* remove menu items */
		if (is_array($pkg_config['menu']) && is_array($menus)) {
			update_status(gettext("Menu items... "));
			foreach ($pkg_config['menu'] as $menu) {
				foreach ($menus as $key => $instmenu) {
					if ($instmenu['name'] ==
					    $menu['name']) {
						unset($menus[$key]);
						break;
					}
				}
			}
			update_status(gettext("done.") . "\n");
		}
		/* remove services */
		if (is_array($pkg_config['service']) && is_array($services)) {
			update_status(gettext("Services... "));
			foreach ($pkg_config['service'] as $service) {
				foreach ($services as $key => $instservice) {
					if ($instservice['name'] !=
					    $service['name']) {
						continue;
					}
					if (platform_booting() != true) {
						stop_service($service['name']);
					}
					if ($service['rcfile']) {
						if (empty($service['prefix'])) {
							$prefix = RCFILEPREFIX;
						} else {
							$prefix =
							    $service['prefix'];
						}
						unlink_if_exists($prefix .
						    $service['rcfile']);
					}
					unset($services[$key]);
				}
			}
			update_status(gettext("done.") . "\n");
		}
		/*
		 * XXX: Otherwise inclusion of config.inc again invalidates
		 *      actions taken.
		 *	Same is done during installation.
		 */
		write_config(sprintf(gettext("Intermediate config write " .
		    "during package removal for %s."), $package_name));

		/*
		 * If a require exists, include it. this will
		 * show us where an error exists in a package
		 * instead of making us blindly guess
		 */
		$missing_include = false;
		if ($pkg_config['include_file'] <> "") {
			update_status(gettext("Loading package instructions...")
			    . "\n");
			if (file_exists($pkg_config['include_file'])) {
				pkg_debug("require_once(\"" .
				    "{$pkg_config['include_file']}\")\n");
				require_once($pkg_config['include_file']);
			} else {
				pkg_debug("Missing include " .
				    $pkg_config['include_file'] . "\n");
				$missing_include = true;
				update_status(sprintf(gettext("Include file " .
				    "%s could not be found for inclusion."),
				    basename($pkg_config['include_file'])) .
				    "\n");
			}
		}
		/*
		 * NOTE: It is not possible to handle parse errors on eval.
		 * So we prevent it from being run at all to not interrupt all
		 * the other code.
		 */
		if ($when == "deinstall" && $missing_include == false) {
			/*
			 * evaluate this package's global functions and pre
			 * deinstall commands
			 */
			if ($pkg_config['custom_php_global_functions'] <> "") {
				eval_once($pkg_config['custom_php_global_functions']);
			}
			if ($pkg_config['custom_php_pre_deinstall_command'] <> "") {
				eval_once($pkg_config['custom_php_pre_deinstall_command']);
			}
		}
		/* deinstall commands */
		if ($when == "deinstall" &&
		    $pkg_config['custom_php_deinstall_command'] <> "") {
			update_status(gettext("Deinstall commands... "));
			if ($missing_include == false) {
				eval_once($pkg_config['custom_php_deinstall_command']);
				update_status(gettext("done.") . "\n");
			} else {
				update_status("\n". gettext("Not executing " .
				    "custom deinstall hook because an " .
				    "include is missing.") . "\n");
			}
		}
	}
	/* syslog */
	$need_syslog_restart = false;
	if (is_array($pkg_info['logging']) &&
	    $pkg_info['logging']['logfilename'] <> "") {
		update_status(gettext("Syslog entries... "));
		@unlink_if_exists($g['varlog_path'] . "/" .
		    $pkg_info['logging']['logfilename']);
		update_status("done.\n");
		$need_syslog_restart = true;
	}

	if ($when == "post-deinstall") {
		/* remove config.xml entries */
		update_status(gettext("Configuration... "));
		unset($config['installedpackages']['package'][$pkgid]);
		update_status(gettext("done.") . "\n");
		write_config(sprintf(gettext("Removed %s package."),
		    $package_name));
		/*
		 * remove package entry from /etc/syslog.conf if needed
		 * this must be done after removing the entries from config.xml
		 */
		if ($need_syslog_restart) {
			system_syslogd_start(true);
		}
	}
}

/*
 * Used during upgrade process or restore backup process, verify all
 * packages installed in config.xml and install pkg accordingly
 */
function package_reinstall_all() {
	global $g, $config, $pkg_interface;

	if (!isset($config['installedpackages']['package']) ||
	    !is_array($config['installedpackages']['package'])) {
		return true;
	}

	/*
	 * Configure default pkg repo for current version instead of
	 * using it from backup, that could be older
	 */
	$default_repo = pkg_get_default_repo();
	$current_repo_path = "";
	if (!empty($config['system']['pkg_repo_conf_path'])) {
		$current_repo_path = $config['system']['pkg_repo_conf_path'];
	}

	if ($current_repo_path != $default_repo['path']) {
		$config['system']['pkg_repo_conf_path'] = $default_repo['path'];
		write_config( "Configured default pkg repo after restore");
		pkg_switch_repo($default_repo['path']);
	}

	/* wait for internet connection */
	log_error(gettext("Waiting for Internet connection to update pkg " .
	    "metadata and finish package reinstallation"));
	$ntries = 3;
	while ($ntries > 0) {
		if (pkg_update(true)) {
			break;
		}
		sleep(1);
		$ntries--;
	}

	if ($ntries == 0) {
		return false;
	}

	$package_list = array();
	foreach ($config['installedpackages']['package'] as $package) {
		$package_list[] = get_package_internal_name($package);
	}

	if (!empty($package_list)) {
		$pkg_info = get_pkg_info();
	}

	foreach ($package_list as $package) {
		$found = false;
		foreach ($pkg_info as $pkg) {
			pkg_remove_prefix($pkg['name']);
			if ($pkg['name'] == $package) {
				pkg_install($g['pkg_prefix'] . $package, true);
				$found = true;
				break;
			}
		}

		if (!$found) {
			if (!function_exists("file_notice")) {
				require_once("notices.inc");
			}

			file_notice(gettext("Package reinstall"),
			    sprintf(gettext("Package %s does not exist in " .
			    "current %s version and it has been removed."),
			    $package, $g['product_name']));
			uninstall_package($package);
		}
	}

	/*
	 * Verify remaining binary packages not present in current config
	 * during backup restore and remove them
	 */
	$installed_packages = get_pkg_info('all', true, true);
	foreach ($installed_packages as $package) {
		$shortname = $package['name'];
		pkg_remove_prefix($shortname);
		if (get_package_id($shortname) != -1) {
			continue;
		}
		pkg_delete($package['name']);
	}

	return true;
}

function stop_packages() {
	require_once("config.inc");
	require_once("functions.inc");
	require_once("filter.inc");
	require_once("shaper.inc");
	require_once("captiveportal.inc");
	require_once("pkg-utils.inc");
	require_once("pfsense-utils.inc");
	require_once("service-utils.inc");

	global $config, $g;

	log_error(gettext("Stopping all packages."));

	$rcfiles = glob(RCFILEPREFIX . "*.sh");
	if (!$rcfiles) {
		$rcfiles = array();
	} else {
		$rcfiles = array_flip($rcfiles);
		if (!$rcfiles) {
			$rcfiles = array();
		}
	}

	if (isset($config['installedpackages']['package']) &&
	    is_array($config['installedpackages']['package'])) {
		foreach ($config['installedpackages']['package'] as $package) {
			echo " Stopping package {$package['name']}...";
			$internal_name = get_package_internal_name($package);
			stop_service($internal_name);
			unset($rcfiles[RCFILEPREFIX .
			    strtolower($internal_name) . ".sh"]);
			echo "done.\n";
		}
	}

	foreach ($rcfiles as $rcfile => $number) {
		$shell = @popen("/bin/sh", "w");
		if ($shell) {
			echo " Stopping {$rcfile}...";
			if (!@fwrite($shell,
			    "{$rcfile} stop >>/tmp/bootup_messages 2>&1")) {
				if ($shell) {
					pclose($shell);
				}
				$shell = @popen("/bin/sh", "w");
			}
			echo "done.\n";
			pclose($shell);
		}
	}
}

/* Identify which meta package is installed */
function get_meta_pkg_name() {
	global $g;

	/* XXX: Use pkg annotation */
	if (is_pkg_installed($g['product_name'])) {
		return $g['product_name'];
	}
	foreach ($g['alternativemetaports'] as $suffix) {
		if (is_pkg_installed($g['product_name'] . '-' . $suffix)) {
			return $g['product_name'] . '-' . $suffix;
		}
	}
	return false;
}

/* Identify which base package is installed */
function get_base_pkg_name() {
	global $g;

	/* XXX: Use pkg annotation */
	if (is_pkg_installed($g['product_name'] . '-base-' . $g['platform'])) {
		return $g['product_name'] . '-base-' . $g['platform'];
	} else if (is_pkg_installed($g['product_name'] . '-base')) {
		return $g['product_name'] . '-base';
	}
	return false;
}

/* Verify if system needs upgrade (meta package or base) */
function get_system_pkg_version($baseonly = false, $use_cache = true) {
	global $g;

	$cache_file = $g['version_cache_file'];
	$rc_file = $cache_file . '.rc';

	$rc = "";
	if ($use_cache && file_exists($rc_file) &&
	    (time()-filemtime($rc_file) < $g['version_cache_refresh'])) {
		$rc = chop(@file_get_contents($rc_file));
	}

	if ($rc == "2") {
		$output = @file_get_contents($cache_file);
	} else if ($rc != "0") {
		$output = exec(
		    "/usr/local/sbin/{$g['product_name']}-upgrade -c", $_gc,
		    $rc);

		/* Update cache if it succeeded */
		if ($rc == 0 || $rc == 2) {
			@file_put_contents($cache_file, $output);
			@file_put_contents($rc_file, $rc);
		}
	}

	/* pfSense-upgrade returns 2 when there is a new version */
	if ($rc == "2") {
		$new_version = explode(' ', $output)[0];
	}

	$base_pkg = get_base_pkg_name();
	$meta_pkg = get_meta_pkg_name();

	if (!$base_pkg || !$meta_pkg) {
		return false;
	}

	$info = get_pkg_info($base_pkg, true, true);

	$pkg_info = array();
	foreach ($info as $item) {
		if ($item['name'] == $base_pkg) {
			$pkg_info = $item;
			break;
		}
	}

	if (empty($pkg_info) || (!$baseonly && ($pkg_info['version'] ==
	    $pkg_info['installed_version']))) {
		$info = get_pkg_info($meta_pkg, true, true);

		foreach ($info as $item) {
			if ($item['name'] == $meta_pkg) {
				$pkg_info = $item;
				break;
			}
		}
	}

	if (empty($pkg_info)) {
		return false;
	}

	$result = array(
	    'version'           => $new_version ?: $pkg_info['version'],
	    'installed_version' => $pkg_info['installed_version']
	);

	$result['pkg_version_compare'] = pkg_version_compare(
	    $result['installed_version'], $result['version']);

	return $result;
}

/* List available repos */
function pkg_list_repos() {
	global $g;

	$path = "/usr/local/share/{$g['product_name']}/pkg/repos";

	$default_descr = @file_get_contents($path .
	    "/{$g['product_name']}-repo.descr");

	$default = array(
	    'name' => 'Default',
	    'path' => $path . "/{$g['product_name']}-repo.conf",
	    'descr' => $default_descr
	);

	$result = array($default);

	$conf_files = glob("{$path}/{$g['product_name']}-repo-*.conf");
	foreach ($conf_files as $conf_file) {
		$descr_file = preg_replace('/.conf$/', '.descr', $conf_file);
		if (file_exists($descr_file)) {
			$descr_content = file($descr_file);
			$descr = chop($descr_content[0]);
		} else {
			$descr = 'Unknown';
		}
		if (!preg_match('/-repo-(.*).conf/', $conf_file, $matches)) {
			continue;
		}
		$entry = array(
		    'name' => ucfirst(strtolower($matches[1])),
		    'path' => $conf_file,
		    'descr' => $descr
		);
		if (file_exists($conf_file . ".default")) {
			$entry['default'] = true;
		}
		$result[] = $entry;
	}

	return $result;
}

function pkg_get_default_repo() {
	$repos = pkg_list_repos();

	foreach ($repos as $repo) {
		if (isset($repo['default'])) {
			return $repo;
		}
	}

	/* No default found, return the first one */
	return ($repos[0]);
}

/* List available repos on a format to be used by selectors */
function pkg_build_repo_list() {
	$repos = pkg_list_repos();
	$list = array();

	foreach ($repos as $repo) {
		$list[$repo['name']] = $repo['descr'];
	}

	return($list);
}

/* Find repo by path */
function pkg_get_repo_name($path) {
	$repos = pkg_list_repos();

	$default = $repos[0]['name'];
	foreach ($repos as $repo) {
		if ($repo['path'] == $path) {
			return $repo['name'];
		}
		if (isset($repo['default'])) {
			$default = $repo['name'];
		}
	}

	/* Default */
	return $default;
}

/* Setup pkg.conf according current repo */
function pkg_conf_setup() {
	global $g;

	$pkg_conf_path = "/usr/local/etc/pkg.conf";
	$conf = "/usr/local/etc/pkg/repos/{$g['product_name']}.conf";
	if (!file_exists($conf)) {
		return;
	}

	$real_conf = readlink($conf);

	if (!$real_conf) {
		return;
	}

	$abi_file = str_replace('.conf', '.abi', $real_conf);
	$altabi_file = str_replace('.conf', '.altabi', $real_conf);

	$pkg_conf = array();
	if (file_exists($abi_file) && file_exists($altabi_file)) {
		$abi = file_get_contents($abi_file);
		$altabi = file_get_contents($altabi_file);

		$pkg_conf = array(
			"ABI={$abi}",
			"ALTABI={$altabi}"
		);
	}

	file_put_contents($pkg_conf_path, $pkg_conf);
}

/* Switch between stable and devel repos */
function pkg_switch_repo($path) {
	global $g;

	safe_mkdir("/usr/local/etc/pkg/repos");
	@unlink("/usr/local/etc/pkg/repos/{$g['product_name']}.conf");
	@symlink($path, "/usr/local/etc/pkg/repos/{$g['product_name']}.conf");

	pkg_conf_setup();

	/* Update pfSense_version cache */
	mwexec_bg("/etc/rc.update_pkg_metadata now");
	return;
}

$FQDN = "https://ews.netgate.com/pfupdate";
$refreshinterval = (24 * 3600);	// 24 hours
$idfile = "/var/db/uniqueid";
$repopath = "/usr/local/share/{$g['product_name']}/pkg/repos";
$configflename = "{$repopath}/{$g['product_name']}-repo-custom.conf";

/*
 * Update the list of available repositories from the server. This will allow
 * migration to another update repository should the existing one becomes
 * unavailable
 */
function update_repos() {
	global $g, $config, $idfile, $FQDN, $repopath;

	if (!file_exists($idfile) || !function_exists('curl_version')) {
		return;
	}
	/*
	 * If the custom repository definition does not exist, or is more
	 * than 24 hours old fetch a copy from the server
	 */
	if (!file_exists($configflename) ||
	    (time()-filemtime($configflename) > $refreshinterval)) {
		/*
		 * Gather some information about the system so the proper
		 * repo can be returned
		 */
		$nid = file_get_contents($idfile);
		$serial = system_get_serial();

		// Compose a version string in JSON format
		$os = php_uname('s');
		$osver = php_uname('r');
		$v = strtolower(php_uname('v'));
		$ed =  (strpos($v, 'factory') !== false)
		    ? "Factory" : "Community";
		$pkglist = get_pkg_info("all", false, true);
		$platform = system_identify_specific_platform();
		$platformname = gettext('Unknown system');

		if (isset($platform['descr'])) {
			$platformname = $platform['descr'];
		}

		/* XXX: Use json_encode instead of hardcode it */
		$version =
		    "{\"platform\":\"" . $platformname .
		    "\",\"os\":\"" . $os .
		    "\",\"osver\":\"" . $osver .
		    "\",\"prod\":\"" . $g['product_name'] .
		    "\",\"ver\":\"" . $g['product_version'] .
		    "\",\"ed\":\"" . $ed .
		    "\",\"pkgs\":[";

		$first = true;

		foreach($pkglist as $pkg) {
			if (!$first) {
				$version .= ",";
			}

			$version .= "{\"name\":\"" . $pkg['shortname'] .
			    "\",\"ver\":\"" . $pkg['version'] . "\"}";
			$first = false;
		}

		$version .= "]}";

		$arch =  php_uname('m');
		$locale = $config['system']['language'];

		$post = [
		    'uid' => $nid,
		    'language' => $locale,
		    'serial' => $serial,
		    'version' => $version,
		    'arch' => $arch
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' .
		    $g['product_version']);
		curl_setopt($ch, CURLOPT_URL, $FQDN);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,4);
		$response = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($status == 200) {
			save_repo($response);
		}
	}
}

// Parse the received JSON data and save the custom repository information
function save_repo($json) {
	global $repopath, $g;

	$repo = json_decode($json, true);

	if (($repo != NULL) && isset($repo['abi']) && isset($repo['altabi']) &&
	    isset($repo['conf']) && isset($repo['descr']) &&
	    isset($repo['name']) && (strlen($repo['conf']) > 10)) {
		$basename = "{$repopath}/{$g['product_name']}-repo-custom.";

		file_put_contents($basename . "conf", base64_decode(
		    $repo['conf']));
		file_put_contents($basename . "descr", $repo['descr']);
		file_put_contents($basename . "abi", $repo['abi']);
		file_put_contents($basename . "altabi", $repo['altabi']);
		file_put_contents($basename . "name", $repo['name']);
		file_put_contents($basename . "help", $repo['help']);
	} else {
		/*
		 * If there was anything wrong with the custom repository
		 * definition, remove the help text to avoid possible confusion
		 */
		if (file_exists($basename . "help")) {
			unlink($basename . "help");
		}
	}
}

?>
