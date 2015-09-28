<?php
/****h* pfSense/pkg-utils
	NAME
		pkg-utils.inc - Package subsystem
	DESCRIPTION
		This file contains various functions used by the pfSense package system.
	HISTORY
		$Id$

	Copyright (C) 2010 Ermal Luçi
	Copyright (C) 2005-2006 Colin Smith (ethethlay@gmail.com)
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

/*
	pfSense_BUILDER_BINARIES:	/usr/bin/cd	/usr/bin/tar	/usr/sbin/fifolog_create	/bin/chmod
	pfSense_BUILDER_BINARIES:	/usr/sbin/pkg_add	/usr/sbin/pkg_info	/usr/sbin/pkg_delete	/bin/rm
	pfSense_MODULE:	pkg
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
				update_output_window("Warning, could not open log for writing.");
			}
		}
		@fwrite($fd_log, $msg);
	}
}

global $g;
if (!isset($g['platform'])) {
	$g['platform'] = trim(file_get_contents("/etc/platform"));
}

/* Remove pkg_prefix from package name if it's present */
function pkg_remove_prefix(&$pkg_name) {
	global $g;

	if (substr($pkg_name, 0, strlen($g['pkg_prefix'])) == $g['pkg_prefix']) {
		$pkg_name = substr($pkg_name, strlen($g['pkg_prefix']));
	}
}

/* Execute a pkg call */
function pkg_call($params, $mute = false) {
	global $static_output, $g, $config;

	if (empty($params)) {
		return false;
	}

	$user_agent = $g['product_name'] . '/' . $g['product_version'];
	if (!isset($config['system']['host_uuid'])) {
		$user_agent .= ' : ' . get_single_sysctl('kern.hostuuid');
	}

	$env = array(
		"HTTP_USER_AGENT" => $user_agent,
		"ASSUME_ALWAYS_YES" => "true"
	);

	$debug_fifo = $g['tmp_path'] . "/pkg-debug.fifo";
	if (!file_exists($debug_fifo)) {
		posix_mkfifo($debug_fifo, 0600);
	}

	if (filetype($debug_fifo) == 'fifo') {
		$env["EVENT_PIPE"] = $debug_fifo;
	}

	$descriptorspec = array(
		1 => array("pipe", "w"), /* stdout */
		2 => array("pipe", "w")  /* stderr */
	);

	pkg_debug("pkg_call(): {$params}\n");
	$process = proc_open("/usr/sbin/pkg {$params}", $descriptorspec, $pipes, '/', $env);

	if (!is_resource($process)) {
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
						$static_output .= $content;
						update_output_window($static_output);
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
		$static_output .= "\n\n" . sprintf(gettext("ERROR!!! An error occurred on pkg execution (rc = %d) with parameters '%s':"), $rc, $params) . "\n" . $error_log;
		update_output_window($static_output);
	}
	return false;
}

/* Execute pkg with $params, fill stdout and stderr and return pkg rc */
function pkg_exec($params, &$stdout, &$stderr) {
	global $g, $config;

	if (empty($params)) {
		return -1;
	}

	$user_agent = $g['product_name'] . '/' . $g['product_version'];
	if (!isset($config['system']['host_uuid'])) {
		$user_agent .= ' : ' . get_single_sysctl('kern.hostuuid');
	}

	$env = array(
		"HTTP_USER_AGENT" => $user_agent,
		"ASSUME_ALWAYS_YES" => "true"
	);

	$debug_fifo = $g['tmp_path'] . "/pkg-debug.fifo";
	if (!file_exists($debug_fifo)) {
		posix_mkfifo($debug_fifo, 0600);
	}

	if (filetype($debug_fifo) == 'fifo') {
		$env["EVENT_PIPE"] = $debug_fifo;
	}

	$descriptorspec = array(
		1 => array("pipe", "w"), /* stdout */
		2 => array("pipe", "w")  /* stderr */
	);

	pkg_debug("pkg_exec(): {$params}\n");
	$process = proc_open("/usr/sbin/pkg {$params}", $descriptorspec, $pipes, '/', $env);

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

/* Check if package is installed */
function is_pkg_installed($pkg_name) {
	global $g;

	pkg_remove_prefix($pkg_name);

	return pkg_call("info -e " . $g['pkg_prefix'] . $pkg_name, true);
}

/* Install package, $pkg_name should not contain prefix */
function pkg_install($pkg_name) {
	global $g;
	$result = false;

	pkg_remove_prefix($pkg_name);

	pkg_debug("Installing package {$pkg_name}\n");
	if (!is_pkg_installed($pkg_name)) {
		$result = pkg_call("install -y " . $g['pkg_prefix'] . $pkg_name);
		/* Cleanup cacke to free disk space */
		pkg_call("clean -y");
	}

	return $result;
}

/* Delete package from FreeBSD, $pkg_name should not contain prefix */
function pkg_delete($pkg_name) {
	global $g;

	pkg_remove_prefix($pkg_name);

	pkg_debug("Removing package {$pkg_name}\n");
	if (is_pkg_installed($pkg_name)) {
		pkg_call("delete -y " . $g['pkg_prefix'] . $pkg_name);
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

/* Keep backward compatibility since snort/suricata use this function */
function get_pkg_id($package_name) {
	return get_package_id($package_name);
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

/* Get information about packages */
function get_pkg_info($pkgs = 'all', $info = 'all') {
	global $g, $static_output, $input_errors;

	$out = '';
	$err = '';

	if ($pkgs == 'all') {
		$pkgs = $g['pkg_prefix'];
	}

	/* Make sure repo metadata is up2date */
	$static_output .= "\n" . gettext("Updating package repository metadada...") . "\n";
	update_status($static_output);
	if (!pkg_call("update")) {
		$input_errors[] = gettext("ERROR: An error occurred when updating packages repository. Aborting...") . "\n";
		$static_output .= "\n" . gettext("ERROR: An error occurred when updating packages repository. Aborting...") . "\n";
		update_status($static_output);
		return array();
	}

	$rc = pkg_exec("search -U --raw-format json-compact " . $pkgs, $out, $err);

	if ($rc != 0) {
		$static_output .= "\n" . gettext("ERROR: Error trying to get packages list. Aborting...") . "\n";
		$static_output .= $err;
		$input_errors[] =  gettext("ERROR: Error trying to get packages list. Aborting...") . "\n";
		$input_errors[] =  $err;
		update_status($static_output);
		return array();
	}

	$result = array();
	$pkgs_info = explode("\n", $out);
	foreach ($pkgs_info as $pkg_info_json) {
		$pkg_info = json_decode($pkg_info_json, true);
		if (!isset($pkg_info['name'])) {
			continue;
		}

		$result[] = $pkg_info;
		unset($pkg_info);
	}

	return $result;
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
		if ($pkg_interface == "console") {
			echo "\n" . gettext("Syncing packages:");
		}
	}

	if ($show_message == true) {
		echo " done.\n";
	}

	@unlink("/conf/needs_package_sync");
	conf_mount_ro();
}

function uninstall_package($package_name) {
	global $config, $static_output;

	$internal_name = $package_name;
	$id = get_package_id($package_name);
	if ($id >= 0) {
		$internal_name = get_package_internal_name($config['installedpackages']['package'][$id]);
		stop_service($internal_name);
	}

	if (is_pkg_installed($internal_name)) {
		$static_output .= "Removing package...\n";
		update_output_window($static_output);
		pkg_delete($internal_name);
	} else {
		delete_package_xml($package_name);
	}

	$static_output .= gettext("done.") . "\n";
	update_output_window($static_output);
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
		return;  // No package belongs to the pkg_id passed to this function.
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
			log_error("Reinstalling package {$package['name']} because its include file({$include_file}) is missing!");
			uninstall_package($package['name']);
			if (install_package($package['name']) != 0) {
				log_error("Reinstalling package {$package['name']} failed. Take appropriate measures!!!");
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

function install_package($package_name) {
	global $g, $config, $static_output, $pkg_interface;

	if ($pkg_interface == "console") {
		echo "\n";
	}

	return pkg_install($package_name);
}

function install_package_xml($package_name) {
	global $g, $config, $static_output, $pkg_interface;

	if (($pkg_info = read_package_config($package_name)) == false) {
		return false;
	}

	/* safe side. Write config below will send to ro again. */
	conf_mount_rw();

	pkg_debug(gettext("Beginning package installation.") . "\n");
	log_error(sprintf(gettext('Beginning package installation for %s .'), $pkg_info['name']));
	$static_output .= sprintf(gettext("Beginning package installation for %s .\n"), $pkg_info['name']);
	update_status($static_output);

	/* add package information to config.xml */
	$pkgid = get_package_id($pkg_info['name']);
	$static_output .= gettext("Saving updated package information...") . " ";
	update_output_window($static_output);
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
	write_config("Intermediate config write during package install for {$pkg_info['name']}.");
	conf_mount_ro();
	$static_output .= $to_output;
	update_output_window($static_output);

	if (($pkgid = get_package_id($package_name)) == -1) {
		$static_output .= sprintf(gettext("The %s package is not installed.%sInstallation aborted."), $package_name, "\n\n");
		update_output_window($static_output);
		if ($pkg_interface <> "console") {
			echo "\n<script>document.getElementById('progressbar').style.visibility='hidden';</script>";
			echo "\n<script>document.getElementById('progholder').style.visibility='hidden';</script>";
		}

		uninstall_package($package_name);
		write_config($changedesc);
		log_error(sprintf(gettext("Failed to install package: %s."), $pkg_info['name']));
		$static_output .= gettext("Failed to install package.") . "\n";
		update_output_window($static_output);
		return false;
	}

	$configfile = substr(strrchr($pkg_info['config_file'], '/'), 1);
	if (file_exists("/usr/local/pkg/" . $configfile)) {
		$static_output .= gettext("Loading package configuration... ");
		update_output_window($static_output);
		$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $configfile, "packagegui");
		$static_output .= gettext("done.") . "\n";
		update_output_window($static_output);
		$static_output .= gettext("Configuring package components...\n");
		if (!empty($pkg_config['filter_rules_needed'])) {
			$config['installedpackages']['package'][$pkgid]['filter_rule_function'] = $pkg_config['filter_rules_needed'];
		}
		update_output_window($static_output);
		/* modify system files */

		/*   if a require exists, include it.  this will
		 *   show us where an error exists in a package
		 *   instead of making us blindly guess
		 */
		$missing_include = false;
		if ($pkg_config['include_file'] <> "") {
			$static_output .= gettext("Loading package instructions...") . "\n";
			update_output_window($static_output);
			if (file_exists($pkg_config['include_file'])) {
				pkg_debug("require_once('{$pkg_config['include_file']}')\n");
				require_once($pkg_config['include_file']);
			} else {
				pkg_debug("Missing include {$pkg_config['include_file']}\n");
				$missing_include = true;
				$static_output .= "Include " . basename($pkg_config['include_file']) . " is missing!\n";
				update_output_window($static_output);

				uninstall_package($package_name);
				write_config($changedesc);
				log_error(sprintf(gettext("Failed to install package: %s."), $pkg_info['name']));
				$static_output .= gettext("Failed to install package.") . "\n";
				update_output_window($static_output);
				return false;
			}
		}

		/* custom commands */
		$static_output .= gettext("Custom commands...") . "\n";
		update_output_window($static_output);
		if ($missing_include == false) {
			if ($pkg_config['custom_php_global_functions'] <> "") {
				$static_output .= gettext("Executing custom_php_global_functions()...");
				update_output_window($static_output);
				eval_once($pkg_config['custom_php_global_functions']);
				$static_output .= gettext("done.") . "\n";
				update_output_window($static_output);
			}
			if ($pkg_config['custom_php_install_command']) {
				$static_output .= gettext("Executing custom_php_install_command()...");
				update_output_window($static_output);
				eval_once($pkg_config['custom_php_install_command']);
				$static_output .= gettext("done.") . "\n";
				update_output_window($static_output);
			}
			if ($pkg_config['custom_php_resync_config_command'] <> "") {
				$static_output .= gettext("Executing custom_php_resync_config_command()...");
				update_output_window($static_output);
				eval_once($pkg_config['custom_php_resync_config_command']);
				$static_output .= gettext("done.") . "\n";
				update_output_window($static_output);
			}
		}
		/* sidebar items */
		if (is_array($pkg_config['menu'])) {
			$static_output .= gettext("Menu items... ");
			update_output_window($static_output);
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
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* services */
		if (is_array($pkg_config['service'])) {
			$static_output .= gettext("Services... ");
			update_output_window($static_output);
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
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
	} else {
		pkg_debug("Unable to find config file\n");
		$static_output .= gettext("Loading package configuration... failed!") . "\n\n" . gettext("Installation aborted.");
		update_output_window($static_output);
		pkg_debug(gettext("Unable to load package configuration. Installation aborted.") ."\n");
		if ($pkg_interface <> "console") {
			echo "\n<script>document.getElementById('progressbar').style.visibility='hidden';</script>";
			echo "\n<script>document.getElementById('progholder').style.visibility='hidden';</script>";
		}

		uninstall_package($package_name);
		write_config($changedesc);
		log_error(sprintf(gettext("Failed to install package: %s."), $pkg_info['name']));
		$static_output .= gettext("Failed to install package.") . "\n";
		update_output_window($static_output);
		return false;
	}

	/* set up package logging streams */
	if ($pkg_info['logging']) {
		system_syslogd_start();
	}

	$static_output .= gettext("Writing configuration... ");
	update_output_window($static_output);
	write_config($changedesc);
	log_error(sprintf(gettext("Successfully installed package: %s."), $pkg_info['name']));
	$static_output .= gettext("done.") . "\n";
	update_output_window($static_output);
	if ($pkg_info['after_install_info']) {
		update_output_window($pkg_info['after_install_info']);
	}

	return true;
}

function delete_package($package_name) {
	global $config, $g, $static_output;

	if (!is_package_installed($package_name)) {
		return;
	}

	$static_output .= sprintf(gettext("Starting package deletion for %s..."), $package_name);
	update_output_window($static_output);

	pkg_delete($package_name);
	$static_output .= "done.\n";
	update_output_window($static_output);

	return;
}

function delete_package_xml($package_name, $when = "post-deinstall") {
	global $g, $config, $static_output, $pkg_interface;

	conf_mount_rw();

	$pkgid = get_package_id($package_name);
	if ($pkgid == -1) {
		$static_output .= sprintf(gettext("The %s package is not installed.%sDeletion aborted."), $package_name, "\n\n");
		update_output_window($static_output);
		if ($pkg_interface <> "console") {
			echo "\n<script>document.getElementById('progressbar').style.visibility='hidden';</script>";
			echo "\n<script>document.getElementById('progholder').style.visibility='hidden';</script>";
		}
		ob_flush();
		sleep(1);
		conf_mount_ro();
		return;
	}
	pkg_debug(sprintf(gettext("Removing %s package... "), $package_name));
	$static_output .= sprintf(gettext("Removing %s components..."), $package_name) . "\n";
	update_output_window($static_output);
	/* parse package configuration */
	$packages = &$config['installedpackages']['package'];
	$menus =& $config['installedpackages']['menu'];
	$services = &$config['installedpackages']['service'];
	$pkg_info =& $packages[$pkgid];
	if (file_exists("/usr/local/pkg/" . $pkg_info['configurationfile'])) {
		$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $packages[$pkgid]['configurationfile'], "packagegui");
		/* remove menu items */
		if (is_array($pkg_config['menu'])) {
			$static_output .= gettext("Menu items... ");
			update_output_window($static_output);
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
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* remove services */
		if (is_array($pkg_config['service'])) {
			$static_output .= gettext("Services... ");
			update_output_window($static_output);
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
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/*
		 * XXX: Otherwise inclusion of config.inc again invalidates actions taken.
		 * 	Same is done during installation.
		 */
		write_config("Intermediate config write during package removal for {$package_name}.");

		/*
		 * If a require exists, include it.  this will
		 * show us where an error exists in a package
		 * instead of making us blindly guess
		 */
		$missing_include = false;
		if ($pkg_config['include_file'] <> "") {
			$static_output .= gettext("Loading package instructions...") . "\n";
			update_output_window($static_output);
			if (file_exists($pkg_config['include_file'])) {
				pkg_debug("require_once(\"{$pkg_config['include_file']}\")\n");
				require_once($pkg_config['include_file']);
			} else {
				pkg_debug("Missing include {$pkg_config['include_file']}\n");
				$missing_include = true;
				update_output_window($static_output);
				$static_output .= "Include file " . basename($pkg_config['include_file']) . " could not be found for inclusion.\n";
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
			$static_output .= gettext("Deinstall commands... ");
			update_output_window($static_output);
			if ($missing_include == false) {
				eval_once($pkg_config['custom_php_deinstall_command']);
				$static_output .= gettext("done.") . "\n";
			} else {
				$static_output .= "\nNot executing custom deinstall hook because an include is missing.\n";
			}
			update_output_window($static_output);
		}
	}
	/* syslog */
	$need_syslog_restart = false;
	if (is_array($pkg_info['logging']) && $pkg_info['logging']['logfilename'] <> "") {
		$static_output .= "Syslog entries... ";
		update_output_window($static_output);
		@unlink("{$g['varlog_path']}/{$pkg_info['logging']['logfilename']}");
		$static_output .= "done.\n";
		update_output_window($static_output);
		$need_syslog_restart = true;
	}

	/* remove config.xml entries */
	$static_output .= gettext("Configuration... ");
	update_output_window($static_output);
	unset($config['installedpackages']['package'][$pkgid]);
	$static_output .= gettext("done.") . "\n";
	update_output_window($static_output);
	write_config("Removed {$package_name} package.\n");

	/* remove package entry from /etc/syslog.conf if needed */
	/* this must be done after removing the entries from config.xml */
	if ($need_syslog_restart) {
		system_syslogd_start();
	}

	conf_mount_ro();
}

function pkg_reinstall_all() {
	global $g, $config;

	// XXX: implement
	return;
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

	log_error("Stopping all packages.");

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

function verify_all_package_servers() {
	// XXX: Remove it after GUI is ready
	return true;
}

function check_package_server_ssl() {
	// XXX: Remove it after GUI is ready
	return true;
}

?>
