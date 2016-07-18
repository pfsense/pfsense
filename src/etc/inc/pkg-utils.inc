<?php
/*
 * pkg-utils.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2005-2006 Colin Smith (ethethlay@gmail.com)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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
			if (!$fd_log = fopen("{$g['tmp_path']}/pkg_mgr_debug.log", "w")) {
				update_status(gettext("Warning, could not open log for writing.") . "\n");
			}
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

	if (substr($pkg_name, 0, strlen($g['pkg_prefix'])) == $g['pkg_prefix']) {
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
	}

	if ($g['platform'] == "nanobsd" ||
	    isset($config['system']['use_mfs_tmpvar'])) {
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

	$user_agent = $g['product_name'] . '/' . $g['product_version'];
	if (!isset($config['system']['do_not_send_host_uuid'])) {
		$user_agent .= ' : ' . get_single_sysctl('kern.hostuuid');
	}

	$descriptorspec = array(
		1 => array("pipe", "w"), /* stdout */
		2 => array("pipe", "w")	 /* stderr */
	);

	conf_mount_rw();

	pkg_debug("pkg_call(): {$params}\n");
	$process = proc_open("/usr/sbin/pkg {$params}", $descriptorspec, $pipes,
	    '/', pkg_env($extra_env));

	if (!is_resource($process)) {
		conf_mount_ro();
		return false;
	}

	stream_set_blocking($pipes[1], 0);
	stream_set_blocking($pipes[2], 0);

	/* XXX: should be a tunnable? */
	$timeout = 300; // seconds
	$error_log = '';

	do {
		$write = array();
		$read = array($pipes[1], $pipes[2]);
		$except = array();

		$stream = stream_select($read, $write, $except, null, $timeout);
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

	conf_mount_ro();

	$rc = $status['exitcode'];

	pkg_debug("pkg_call(): rc = {$rc}\n");
	if ($rc == 0) {
		return true;
	}

	pkg_debug("pkg_call(): error_log\n{$error_log}\n");
	if (!$mute) {
		update_status("\n\n" .  sprintf(gettext(
		    "ERROR!!! An error occurred on pkg execution (rc = %d) with parameters '%s':"),
		    $rc, $params) . "\n" . $error_log . "\n");
	}

	return false;
}

/* Execute pkg with $params, fill stdout and stderr and return pkg rc */
function pkg_exec($params, &$stdout, &$stderr, $extra_env = array()) {
	global $g, $config;

	if (empty($params)) {
		return -1;
	}

	$user_agent = $g['product_name'] . '/' . $g['product_version'];
	if (!isset($config['system']['do_not_send_host_uuid'])) {
		$user_agent .= ' : ' . get_single_sysctl('kern.hostuuid');
	}

	$descriptorspec = array(
		1 => array("pipe", "w"), /* stdout */
		2 => array("pipe", "w")	 /* stderr */
	);

	conf_mount_rw();

	pkg_debug("pkg_exec(): {$params}\n");
	$process = proc_open("/usr/sbin/pkg {$params}", $descriptorspec, $pipes,
	    '/', pkg_env($extra_env));

	if (!is_resource($process)) {
		conf_mount_ro();
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

	conf_mount_ro();

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
		/* Cleanup cacke to free disk space */
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
		/* Cleanup unecessary dependencies */
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

	if (!is_array($config['installedpackages']['package'])) {
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
	if (isset($package_data['internal_name']) && ($package_data['internal_name'] != "")) {
		/* e.g. name is Ipguard-dev, internal name is ipguard */
		return $package_data['internal_name'];
	} else {
		return $package_data['name'];
	}
}

// Get information about packages.
function get_pkg_info($pkgs = 'all', $info = 'all', $only_local = false) {
	global $g, $input_errors;

	$out = '';
	$err = '';

	unset($pkg_filter);
	if (is_array($pkgs)) {
		$pkg_filter = $pkgs;
		$pkgs = 'all';
	}

	if ($pkgs == 'all') {
		$pkgs = $g['pkg_prefix'];
	}

	if (!function_exists('is_subsystem_dirty')) {
		require_once("util.inc");
	}

	/* Do not run remote operations if pkg has a lock */
	if (is_subsystem_dirty('pkg')) {
		$only_local = true;
		$lock = false;
	} else {
		$lock = true;
	}

	$extra_param = "";
	if ($only_local) {
		$extra_param = "-U ";
	}

	if ($lock) {
		mark_subsystem_dirty('pkg');
	}
	$rc = pkg_exec("search {$extra_param}-R --raw-format json-compact " . $pkgs, $out, $err);
	if ($lock) {
		clear_subsystem_dirty('pkg');
	}

	if ($rc != 0) {
		update_status("\n" . gettext(
		    "ERROR: Error trying to get packages list. Aborting...")
		    . "\n");
		update_status($err);
		$input_errors[] =  gettext("ERROR: Error trying to get packages list. Aborting...") . "\n";
		$input_errors[] =  $err;
		return array();
	}

	$result = array();
	$pkgs_info = explode("\n", $out);
	foreach ($pkgs_info as $pkg_info_json) {
		$pkg_info = json_decode($pkg_info_json, true);
		if (!isset($pkg_info['name'])) {
			continue;
		}

		if (isset($pkg_filter) && !in_array($pkg_info['name'], $pkg_filter)) {
			continue;
		}

		$pkg_info['shortname'] = $pkg_info['name'];
		pkg_remove_prefix($pkg_info['shortname']);

		/* XXX: Add it to globals.inc? */
		$pkg_info['changeloglink'] =
		    "https://github.com/pfsense/FreeBSD-ports/commits/devel/" .
		    $pkg_info['categories'][0] . '/' . $pkg_info['name'];

		if (is_pkg_installed($pkg_info['name'])) {
			$pkg_info['installed'] = true;

			$rc = pkg_exec("query %v {$pkg_info['name']}", $out, $err);

			if ($rc != 0) {
				update_status("\n" . gettext(
				    "ERROR: Error trying to get package version. Aborting...")
				    . "\n");
				update_status($err);
				$input_errors[] =  gettext("ERROR: Error trying to get package version. Aborting...") . "\n";
				$input_errors[] =  $err;
				return array();
			}

			$pkg_info['installed_version'] = str_replace("\n", "", $out);
		} else if (is_package_installed($pkg_info['shortname'])) {
			$pkg_info['broken'] = true;
		}

		$pkg_info['desc'] = preg_replace('/\n+WWW:.*$/', '', $pkg_info['desc']);

		$result[] = $pkg_info;
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

	$pkg_info = get_pkg_info('all', 'all', true);

	foreach ($pkg_info as $pkg) {
		if (!isset($pkg['installed'])) {
			continue;
		}

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
 * resync_all_package_configs() Force packages to setup their configuration and rc.d files.
 * This function may also print output to the terminal indicating progress.
 */
function resync_all_package_configs($show_message = false) {
	global $config, $pkg_interface, $g;

	log_error(gettext("Resyncing configuration for all packages."));

	if (!is_array($config['installedpackages']['package'])) {
		return;
	}

	if ($show_message == true) {
		echo "Syncing packages:";
	}

	conf_mount_rw();

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

	@unlink("/conf/needs_package_sync");
	conf_mount_ro();
}

function uninstall_package($package_name) {
	global $config;

	$internal_name = $package_name;
	$id = get_package_id($package_name);
	if ($id >= 0) {
		$internal_name = get_package_internal_name($config['installedpackages']['package'][$id]);
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
		return; // This package doesn't really exist - exit the function.
	}

	if (!is_array($config['installedpackages']['package'][$pkg_id])) {
		return;	 // No package belongs to the pkg_id passed to this function.
	}

	$package =& $config['installedpackages']['package'][$pkg_id];
	if (!file_exists("/usr/local/pkg/" . $package['configurationfile'])) {
		log_error(sprintf(gettext("The %s package is missing its configuration file and must be reinstalled."), $package['name']));
		delete_package_xml($package['name']);
		return;
	}

	$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], "packagegui");
	if (isset($pkg_config['nosync'])) {
		return;
	}

	/* Bring in package include files */
	if (!empty($pkg_config['include_file'])) {
		$include_file = $pkg_config['include_file'];
		if (file_exists($include_file)) {
			require_once($include_file);
		} else {
			log_error(sprintf(gettext('Reinstalling package %1$s because its include file(%2$s) is missing!'), $package['name'], $include_file));
			uninstall_package($package['name']);
			if (install_package($package['name']) != 0) {
				log_error(sprintf(gettext("Reinstalling package %s failed. Take appropriate measures!!!"), $package['name']));
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

	$pkg_info_xml = '/usr/local/share/' . $g['pkg_prefix'] . $package_name . '/info.xml';

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

	if ($id < 0 || !isset($config['installedpackages']['package'][$id]['configurationfile'])) {
		return $pkg_config;
	}

	$pkg_configurationfile = $config['installedpackages']['package'][$id]['configurationfile'];

	if (empty($pkg_configurationfile) || !file_exists('/usr/local/pkg/' . $pkg_configurationfile)) {
		return $pkg_config;
	}

	$pkg_config = parse_xml_config_pkg('/usr/local/pkg/' . $pkg_configurationfile, "packagegui");

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

	/* safe side. Write config below will send to ro again. */
	conf_mount_rw();

	pkg_debug(gettext("Beginning package installation.") . "\n");
	log_error(sprintf(gettext('Beginning package installation for %s .'), $pkg_info['name']));

	/* add package information to config.xml */
	$pkgid = get_package_id($pkg_info['name']);
	update_status(gettext("Saving updated package information...") . "\n");
	if ($pkgid == -1) {
		$config['installedpackages']['package'][] = $pkg_info;
		$changedesc = sprintf(gettext("Installed %s package."), $pkg_info['name']);
		$to_output = gettext("done.") . "\n";
	} else {
		$config['installedpackages']['package'][$pkgid] = $pkg_info;
		$changedesc = sprintf(gettext("Overwrote previous installation of %s."), $pkg_info['name']);
		$to_output = gettext("overwrite!") . "\n";
	}
	unlink_if_exists('/conf/needs_package_sync');
	write_config(sprintf(gettext("Intermediate config write during package install for %s."), $pkg_info['name']));
	conf_mount_ro();
	update_status($to_output);

	if (($pkgid = get_package_id($package_name)) == -1) {
		update_status(sprintf(gettext("The %s package is not installed.%sInstallation aborted."), $package_name, "\n\n"));

		uninstall_package($package_name);
		write_config($changedesc);
		log_error(sprintf(gettext("Failed to install package: %s."), $pkg_info['name']));
		update_status(gettext("Failed to install package.") . "\n");
		return false;
	}

	if (file_exists("/usr/local/pkg/" . $pkg_info['configurationfile'])) {
		update_status(gettext("Loading package configuration... "));
		$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $pkg_info['configurationfile'], "packagegui");
		update_status(gettext("done.") . "\n");
		update_status(gettext("Configuring package components...") . "\n");
		if (!empty($pkg_config['filter_rules_needed'])) {
			$config['installedpackages']['package'][$pkgid]['filter_rule_function'] = $pkg_config['filter_rules_needed'];
		}
		/* modify system files */

		/* if a require exists, include it.  this will
		 * show us where an error exists in a package
		 * instead of making us blindly guess
		 */
		$missing_include = false;
		if ($pkg_config['include_file'] <> "") {
			update_status(gettext("Loading package instructions...") . "\n");
			if (file_exists($pkg_config['include_file'])) {
				pkg_debug("require_once('{$pkg_config['include_file']}')\n");
				require_once($pkg_config['include_file']);
			} else {
				pkg_debug("Missing include {$pkg_config['include_file']}\n");
				$missing_include = true;
				update_status(sprintf(gettext("Include %s is missing!"), basename($pkg_config['include_file'])) . "\n");

				uninstall_package($package_name);
				write_config($changedesc);
				log_error(sprintf(gettext("Failed to install package: %s."), $pkg_info['name']));
				update_status(gettext("Failed to install package.") . "\n");
				return false;
			}
		}

		/* custom commands */
		update_status(gettext("Custom commands...") . "\n");
		if ($missing_include == false) {
			if ($pkg_config['custom_php_global_functions'] <> "") {
				update_status(gettext("Executing custom_php_global_functions()..."));
				eval_once($pkg_config['custom_php_global_functions']);
				update_status(gettext("done.") . "\n");
			}
			if ($pkg_config['custom_php_install_command']) {
				update_status(gettext("Executing custom_php_install_command()..."));
				eval_once($pkg_config['custom_php_install_command']);
				update_status(gettext("done.") . "\n");
			}
			if ($pkg_config['custom_php_resync_config_command'] <> "") {
				update_status(gettext("Executing custom_php_resync_config_command()..."));
				eval_once($pkg_config['custom_php_resync_config_command']);
				update_status(gettext("done.") . "\n");
			}
		}
		/* sidebar items */
		if (is_array($pkg_config['menu'])) {
			update_status(gettext("Menu items... "));
			foreach ($pkg_config['menu'] as $menu) {
				if (is_array($config['installedpackages']['menu'])) {
					foreach ($config['installedpackages']['menu'] as $amenu) {
						if ($amenu['name'] == $menu['name']) {
							continue 2;
						}
					}
				} else {
					$config['installedpackages']['menu'] = array();
				}
				$config['installedpackages']['menu'][] = $menu;
			}
			update_status(gettext("done.") . "\n");
		}
		/* services */
		if (is_array($pkg_config['service'])) {
			update_status(gettext("Services... "));
			foreach ($pkg_config['service'] as $service) {
				if (is_array($config['installedpackages']['service'])) {
					foreach ($config['installedpackages']['service'] as $aservice) {
						if ($aservice['name'] == $service['name']) {
							continue 2;
						}
					}
				} else {
					$config['installedpackages']['service'] = array();
				}
				$config['installedpackages']['service'][] = $service;
			}
			update_status(gettext("done.") . "\n");
		}
	} else {
		pkg_debug("Unable to find config file\n");
		update_status(gettext("Loading package configuration... failed!") . "\n\n" . gettext("Installation aborted."));
		pkg_debug(gettext("Unable to load package configuration. Installation aborted.") ."\n");

		uninstall_package($package_name);
		write_config($changedesc);
		log_error(sprintf(gettext("Failed to install package: %s."), $pkg_info['name']));
		update_status(gettext("Failed to install package.") . "\n");
		return false;
	}

	/* set up package logging streams */
	if ($pkg_info['logging']) {
		system_syslogd_start();
	}

	update_status(gettext("Writing configuration... "));
	write_config($changedesc);
	log_error(sprintf(gettext("Successfully installed package: %s."), $pkg_info['name']));
	update_status(gettext("done.") . "\n");
	if ($pkg_info['after_install_info']) {
		update_status($pkg_info['after_install_info']);
	}

	return true;
}

function delete_package_xml($package_name, $when = "post-deinstall") {
	global $g, $config, $pkg_interface;

	conf_mount_rw();

	$pkgid = get_package_id($package_name);
	if ($pkgid == -1) {
		update_status(sprintf(gettext("The %s package is not installed.%sDeletion aborted."), $package_name, "\n\n"));
		ob_flush();
		sleep(1);
		conf_mount_ro();
		return;
	}
	pkg_debug(sprintf(gettext("Removing %s package... "), $package_name));
	update_status(sprintf(gettext("Removing %s components..."), $package_name) . "\n");
	/* parse package configuration */
	$packages = &$config['installedpackages']['package'];
	$menus =& $config['installedpackages']['menu'];
	$services = &$config['installedpackages']['service'];
	$pkg_info =& $packages[$pkgid];
	if (file_exists("/usr/local/pkg/" . $pkg_info['configurationfile'])) {
		$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $packages[$pkgid]['configurationfile'], "packagegui");
		/* remove menu items */
		if (is_array($pkg_config['menu'])) {
			update_status(gettext("Menu items... "));
			if (is_array($pkg_config['menu']) && is_array($menus)) {
				foreach ($pkg_config['menu'] as $menu) {
					foreach ($menus as $key => $instmenu) {
						if ($instmenu['name'] == $menu['name']) {
							unset($menus[$key]);
							break;
						}
					}
				}
			}
			update_status(gettext("done.") . "\n");
		}
		/* remove services */
		if (is_array($pkg_config['service'])) {
			update_status(gettext("Services... "));
			if (is_array($pkg_config['service']) && is_array($services)) {
				foreach ($pkg_config['service'] as $service) {
					foreach ($services as $key => $instservice) {
						if ($instservice['name'] == $service['name']) {
							if (platform_booting() != true) {
								stop_service($service['name']);
							}
							if ($service['rcfile']) {
								$prefix = RCFILEPREFIX;
								if (!empty($service['prefix'])) {
									$prefix = $service['prefix'];
								}
								if (file_exists("{$prefix}{$service['rcfile']}")) {
									@unlink("{$prefix}{$service['rcfile']}");
								}
							}
							unset($services[$key]);
						}
					}
				}
			}
			update_status(gettext("done.") . "\n");
		}
		/*
		 * XXX: Otherwise inclusion of config.inc again invalidates actions taken.
		 *	Same is done during installation.
		 */
		write_config(sprintf(gettext("Intermediate config write during package removal for %s."), $package_name));

		/*
		 * If a require exists, include it.	 this will
		 * show us where an error exists in a package
		 * instead of making us blindly guess
		 */
		$missing_include = false;
		if ($pkg_config['include_file'] <> "") {
			update_status(gettext("Loading package instructions...") . "\n");
			if (file_exists($pkg_config['include_file'])) {
				pkg_debug("require_once(\"{$pkg_config['include_file']}\")\n");
				require_once($pkg_config['include_file']);
			} else {
				pkg_debug("Missing include {$pkg_config['include_file']}\n");
				$missing_include = true;
				update_status(sprintf(gettext("Include file %s could not be found for inclusion."), basename($pkg_config['include_file'])) . "\n");
			}
		}
		/* ermal
		 * NOTE: It is not possible to handle parse errors on eval.
		 * So we prevent it from being run at all to not interrupt all the other code.
		 */
		if ($when == "deinstall" && $missing_include == false) {
			/* evaluate this package's global functions and pre deinstall commands */
			if ($pkg_config['custom_php_global_functions'] <> "") {
				eval_once($pkg_config['custom_php_global_functions']);
			}
			if ($pkg_config['custom_php_pre_deinstall_command'] <> "") {
				eval_once($pkg_config['custom_php_pre_deinstall_command']);
			}
		}
		/* deinstall commands */
		if ($when == "post-deinstall" && $pkg_config['custom_php_deinstall_command'] <> "") {
			update_status(gettext("Deinstall commands... "));
			if ($missing_include == false) {
				eval_once($pkg_config['custom_php_deinstall_command']);
				update_status(gettext("done.") . "\n");
			} else {
				update_status("\n". gettext("Not executing custom deinstall hook because an include is missing.") . "\n");
			}
		}
	}
	/* syslog */
	$need_syslog_restart = false;
	if (is_array($pkg_info['logging']) && $pkg_info['logging']['logfilename'] <> "") {
		update_status(gettext("Syslog entries... "));
		@unlink("{$g['varlog_path']}/{$pkg_info['logging']['logfilename']}");
		update_status("done.\n");
		$need_syslog_restart = true;
	}

	if ($when == "post-deinstall") {
		/* remove config.xml entries */
		update_status(gettext("Configuration... "));
		unset($config['installedpackages']['package'][$pkgid]);
		update_status(gettext("done.") . "\n");
		write_config(sprintf(gettext("Removed %s package."), $package_name));
	}

	/* remove package entry from /etc/syslog.conf if needed */
	/* this must be done after removing the entries from config.xml */
	if ($need_syslog_restart) {
		system_syslogd_start();
	}

	conf_mount_ro();
}

/*
 * Used during upgrade process or retore backup process, verify all
 * packages installed in config.xml and install pkg accordingly
 */
function package_reinstall_all() {
	global $g, $config, $pkg_interface;

	$upgrade = (file_exists('/conf/needs_package_sync') && platform_booting());

	if ((!isset($config['installedpackages']['package']) ||
	    !is_array($config['installedpackages']['package'])) && !$upgrade) {
		return true;
	}

	/* During boot after upgrade, wait for internet connection */
	if ($upgrade) {
		update_status(gettext("Waiting for Internet connection to update pkg metadata and finish package reinstallation"));
		$ntries = 3;
		while ($ntries > 0) {
			if (pkg_update(true)) {
				break;
			}
			update_status('.');
			sleep(1);
			$ntries--;
		}
		update_status("\n");

		if ($ntries == 0) {
			file_notice(gettext("Package reinstall"),
			    gettext("Package reinstall process was ABORTED due to lack of internet connectivity"));
			return false;
		}
	}

	$pkg_info = get_pkg_info();

	if ($upgrade &&
	    file_exists("{$g['cf_conf_path']}/packages_to_reinstall_after_upgrade.txt")) {
		$package_list = file("{$g['cf_conf_path']}/packages_to_reinstall_after_upgrade.txt",
		    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		unlink_if_exists("{$g['cf_conf_path']}/packages_to_reinstall_after_upgrade.txt");
	} else {
		$package_list = array();
		foreach ($config['installedpackages']['package'] as $package) {
			$package_list[] = get_package_internal_name($package);
		}
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
			    sprintf(gettext("Package %s does not exist in current %s version and it has been removed."),
			    $package, $g['product_name']));
			uninstall_package($package);
		}
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

	if (is_array($config['installedpackages']['package'])) {
		foreach ($config['installedpackages']['package'] as $package) {
			echo " Stopping package {$package['name']}...";
			$internal_name = get_package_internal_name($package);
			stop_service($internal_name);
			unset($rcfiles[RCFILEPREFIX . strtolower($internal_name) . ".sh"]);
			echo "done.\n";
		}
	}

	foreach ($rcfiles as $rcfile => $number) {
		$shell = @popen("/bin/sh", "w");
		if ($shell) {
			echo " Stopping {$rcfile}...";
			if (!@fwrite($shell, "{$rcfile} stop >>/tmp/bootup_messages 2>&1")) {
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
	} else if (is_pkg_installed($g['product_name'] . '-vmware')) {
		return $g['product_name'] . '-vmware';
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
function get_system_pkg_version($baseonly = false) {
	global $g;

	$base_pkg = get_base_pkg_name();
	$meta_pkg = get_meta_pkg_name();

	if (!$base_pkg || !$meta_pkg) {
		return false;
	}

	$info = get_pkg_info($base_pkg);
	$pkg_name = $base_pkg;

	$pkg_info = array();
	foreach ($info as $item) {
		if ($item['name'] == $base_pkg) {
			$pkg_info = $item;
		}
	}

	if (empty($pkg_info) || (!$baseonly && ($pkg_info['version'] == $pkg_info['installed_version']))) {
		$info = get_pkg_info($meta_pkg);
		$pkg_name = $meta_pkg;

		foreach ($info as $item) {
			if ($item['name'] == $meta_pkg) {
				$pkg_info = $item;
			}
		}
	}

	if (empty($pkg_info)) {
		return false;
	}

	return array(
	    'pkg_name'          => $pkg_name,
	    'version'           => $pkg_info['version'],
	    'installed_version' => $pkg_info['installed_version']
	);
}

/* List available repos */
function pkg_list_repos() {
	global $g;

	$path = "/usr/local/share/{$g['product_name']}/pkg/repos";

	$default_descr = @file_get_contents($path . "/{$g['product_name']}-repo.descr");

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
		$result[] = $entry;
	}

	return $result;
}

/* Switch between stable and devel repos */
function pkg_switch_repo($path) {
	global $g;

	safe_mkdir("/usr/local/etc/pkg/repos");
	@unlink("/usr/local/etc/pkg/repos/{$g['product_name']}.conf");
	@symlink($path, "/usr/local/etc/pkg/repos/{$g['product_name']}.conf");

	return pkg_update(true);
}

?>
