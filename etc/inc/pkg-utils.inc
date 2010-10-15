<?php
/****h* pfSense/pkg-utils
 * NAME
 *   pkg-utils.inc - Package subsystem
 * DESCRIPTION
 *   This file contains various functions used by the pfSense package system.
 * HISTORY
 *   $Id$
 ******
 *
 * Copyright (C) 2005-2006 Colin Smith (ethethlay@gmail.com)
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * RISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 */

/*
	pfSense_BUILDER_BINARIES:	/usr/bin/cd	/usr/bin/tar	/bin/cat	/usr/sbin/fifolog_create	/bin/chmod
	pfSense_BUILDER_BINARIES:	/usr/bin/killall	/usr/sbin/pkg_info	/usr/sbin/pkg_delete	/bin/rm	/bin/ls
	pfSense_BUILDER_BINARIES:	/sbin/pfctl
	pfSense_MODULE:	pkg
*/

require_once("xmlrpc.inc");
if(file_exists("/cf/conf/use_xmlreader"))
	require_once("xmlreader.inc");
else
	require_once("xmlparse.inc");
require_once("service-utils.inc");
require_once("pfsense-utils.inc");
require_once("globals.inc");

if(!function_exists("update_status")) {
	function update_status($status) {
		echo $status . "\n";
	}
}
if(!function_exists("update_output_window")) {
	function update_output_window($status) {
		echo $status . "\n";
	}
}

safe_mkdir("/var/db/pkg");

conf_mount_rw();
$g['platform'] = trim(file_get_contents("/etc/platform"));
if(!is_dir("/usr/local/pkg") or !is_dir("/usr/local/pkg/pf")) {
	safe_mkdir("/usr/local/pkg");
	safe_mkdir("/usr/local/pkg/pf");	
}
conf_mount_ro();

$version = split("-", trim(file_get_contents("/etc/version")));
$ver = split("\.", $version[0]);
$g['version'] = intval($ver[1]);

/****f* pkg-utils/remove_package
 * NAME
 *   remove_package - Removes package from FreeBSD if it exists
 * INPUTS
 *   $packagestring	- name/string to check for
 * RESULT
 *   none
 * NOTES
 *   
 ******/
function remove_freebsd_package($packagestring) {
	$todel = substr(reverse_strrchr($packagestring, "."), 0, -1);
	exec("echo y | /usr/sbin/pkg_delete -x {$todel}");
}

/****f* pkg-utils/is_package_installed
 * NAME
 *   is_package_installed - Check whether a package is installed.
 * INPUTS
 *   $packagename	- name of the package to check
 * RESULT
 *   boolean	- true if the package is installed, false otherwise
 * NOTES
 *   This function is deprecated - get_pkg_id() can already check for installation.
 ******/
function is_package_installed($packagename) {
	$pkg = get_pkg_id($packagename);
	if($pkg == -1)
		return false;
	return true;
}

/****f* pkg-utils/get_pkg_id
 * NAME
 *   get_pkg_id - Find a package's numeric ID.
 * INPUTS
 *   $pkg_name	- name of the package to check
 * RESULT
 *   integer    - -1 if package is not found, >-1 otherwise
 ******/
function get_pkg_id($pkg_name) {
	global $config;

	if(is_array($config['installedpackages']['package'])) {
		$i = 0;
		foreach($config['installedpackages']['package'] as $pkg) {
			if($pkg['name'] == $pkg_name)
				return $i;
			$i++;
		}
	}
	return -1;
}

/****f* pkg-utils/get_pkg_info
 * NAME
 *   get_pkg_info - Retrive package information from pfsense.com.
 * INPUTS
 *   $pkgs - 'all' to retrive all packages, an array containing package names otherwise
 *   $info - 'all' to retrive all information, an array containing keys otherwise
 * RESULT
 *   $raw_versions - Array containing retrieved information, indexed by package name.
 ******/
function get_pkg_info($pkgs = 'all', $info = 'all') {
	global $g;

	$freebsd_version = str_replace("\n", "", `uname -r | cut -d'-' -f1 | cut -d'.' -f1`);
	$freebsd_machine = str_replace("\n", "", `uname -m`);
	$params = array(
		"pkg" => $pkgs, 
		"info" => $info, 
		"freebsd_version" => $freebsd_version,
		"freebsd_machine" => $freebsd_machine
	);
	$resp = call_pfsense_method('pfsense.get_pkgs', $params, 10);
	return $resp ? $resp : array();
}

function get_pkg_sizes($pkgs = 'all') {
	global $g;

	$params = array("pkg" => $pkgs);
	$msg = new XML_RPC_Message('pfsense.get_pkg_sizes', array(php_value_to_xmlrpc($params)));
	$xmlrpc_base_url = isset($config['system']['altpkgrepo']['enable']) ? $config['system']['altpkgrepo']['xmlrpcbaseurl'] : $g['xmlrpcbaseurl'];
	$cli = new XML_RPC_Client($g['xmlrpcpath'], $xmlrpc_base_url);
	$resp = $cli->send($msg, 10);
	if($resp and !$resp->faultCode()) {
		$raw_versions = $resp->value();
		return xmlrpc_value_to_php($raw_versions);
	}

	return array();
}

/*
 * resync_all_package_configs() Force packages to setup their configuration and rc.d files.
 * This function may also print output to the terminal indicating progress.
 */
function resync_all_package_configs($show_message = false) {
	global $config, $restart_sync, $pkg_interface;

	$i = 0;
	log_error(gettext("Resyncing configuration for all packages."));
	if(!$config['installedpackages']['package'])
		return;
	if($show_message == true)
		echo "Syncing packages:";

	if (is_array($config['installedpackages']['package'])) {
		foreach($config['installedpackages']['package'] as $package) {
			if (empty($package['name']))
				continue;
			if($show_message == true)
				echo " " . $package['name'];
			get_pkg_depends($package['name'], "all");
			stop_service($package['name']);
			sync_package($i, true, true);
			if($restart_sync == true) {
				$restart_sync = false;
				if($pkg_interface == "console") 
					echo "\n" . gettext("Syncing packages:");
			}
			$i++;
		}
	}
	if($show_message == true)
		echo "done.\n";
	@unlink("/conf/needs_package_sync");
}

/*
 * is_freebsd_pkg_installed() - Check /var/db/pkg to determine whether or not a FreeBSD
 *				package is installed.
 */
function is_freebsd_pkg_installed($pkg) {
	global $g;

	if(in_array($pkg, return_dir_as_array("{$g['vardb_path']}/pkg")))
		return true;
	return false;
}

/*
 * get_pkg_depends($pkg_name, $filetype = ".xml", $format = "files", return_nosync = 1):  Return a package's dependencies.
 *
 * $filetype = "all" || ".xml", ".tgz", etc.
 * $format = "files" (full filenames) || "names" (stripped / parsed depend names)
 * $return_nosync = 1 (return depends that have nosync set) | 0 (ignore packages with nosync)
 *
 */
function get_pkg_depends($pkg_name, $filetype = ".xml", $format = "files", $return_nosync = 1) {
	global $config;
	require_once("notices.inc");

	$pkg_id = get_pkg_id($pkg_name);
	if($pkg_id == -1)
		return -1; // This package doesn't really exist - exit the function.
	else if (!isset($config['installedpackages']['package'][$pkg_id]))
		return; // No package belongs to the pkg_id passed to this function.

	$package =& $config['installedpackages']['package'][$pkg_id];
	if(!file_exists("/usr/local/pkg/" . $package['configurationfile'])) {
		log_error(sprintf(gettext('The %1$s package is missing required dependencies and must be reinstalled. %2$s'), $package['name'], $package['configurationfile']));
		uninstall_package($package['name']);
		if (install_package($package['name']) < 0)
			return false;
	}
	$pkg_xml = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], "packagegui");
	if (!empty($pkg_xml['additional_files_needed'])) {
		foreach($pkg_xml['additional_files_needed'] as $item) {
			if ($return_nosync == 0 && isset($item['nosync']))
				continue; // Do not return depends with nosync set if not required.
			$depend_file = substr(strrchr($item['item']['0'], '/'),1); // Strip URLs down to filenames.
			$depend_name = substr(substr($depend_file,0,strpos($depend_file,".")+1),0,-1); // Strip filename down to dependency name.
			if (($filetype != "all") && (!preg_match("/{$filetype}/i", $depend_file)))
					continue;
			if ($item['prefix'] != "")
				$prefix = $item['prefix'];
			else
				$prefix = "/usr/local/pkg/";
			// Ensure that the prefix exists to avoid installation errors.
			if(!is_dir($prefix)) 
				exec("/bin/mkdir -p {$prefix}");
			if(!file_exists($prefix . $depend_file))
				log_error(sprintf(gettext("The %s package is missing required dependencies and must be reinstalled."), $package['name']));
			switch ($format) {
			case "files":
				$depends[] = $prefix . $depend_file;
				break;
			case "names":
				switch ($filetype) {
				case "all":
					if(preg_match("/\.xml/i", $depend_file)) {
						$depend_xml = parse_xml_config_pkg("/usr/local/pkg/{$depend_file}", "packagegui");
						if (!empty($depend_xml))
							$depends[] = $depend_xml['name'];
					} else
						$depends[] = $depend_name; // If this dependency isn't package XML, use the stripped filename.
					break;
				case ".xml":
					$depend_xml = parse_xml_config_pkg("/usr/local/pkg/" . $depend_file, "packagegui");
					if (!empty($depend_xml))
						$depends[] = $depend_xml['name'];
					break;
				default:
					$depends[] = $depend_name; // If we aren't looking for XML, use the stripped filename (it's all we have).
					break;
				}
			}
		}
		return $depends;
	}
}

function uninstall_package($pkg_name) {
	global $config;

	$id = get_pkg_id($pkg_name);
	if ($id >= 0) {
		$pkg_depends =& $config['installedpackages']['package'][$id]['depends_on_package'];
		if (is_array($pkg_depends)) {
			foreach ($pkg_depends as $pkg_depend)
				delete_package($pkg_depend, $id);
		}
	}
	delete_package_xml($pkg_name);
}

function force_remove_package($pkg_name) {
	global $config;
	delete_package_xml($pkg_name);
}

/*
 * sync_package($pkg_name, $sync_depends = true, $show_message = false) Force a package to setup its configuration and rc.d files.
 */
function sync_package($pkg_name, $sync_depends = true, $show_message = false) {
	global $config;
	require_once("notices.inc");
	
	if(empty($config['installedpackages']['package']))
		return;
	if(!is_numeric($pkg_name)) {
		$pkg_id = get_pkg_id($pkg_name);
		if($pkg_id == -1)
			return -1; // This package doesn't really exist - exit the function.
	} else {
		$pkg_id = $pkg_name;
		if(empty($config['installedpackages']['package'][$pkg_id]))
			return;  // No package belongs to the pkg_id passed to this function.
	}
        if (is_array($config['installedpackages']['package'][$pkg_id]))
		$package =& $config['installedpackages']['package'][$pkg_id];
        else
		return; /* empty package tag */
	if(!file_exists("/usr/local/pkg/" . $package['configurationfile'])) {
		log_error(sprintf(gettext("The %s package is missing its configuration file and must be reinstalled."), $package['name']));
		force_remove_package($package['name']);
		return -1;
	}
	$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], "packagegui");

	/* Bring in package include files */
	if (!empty($pkg_config['include_file'])) {
		$include_file = $pkg_config['include_file'];
		if (file_exists($include_file))
			require_once($include_file);
		else {
			/* XXX: What the heck is this?! */
			log_error("Reinstalling package {$package['name']} because its include file({$include_file}) is missing!");
			uninstall_package($package['name']);
			if (install_package($package['name']) < 0) {
				log_error("Reinstalling package {$package['name']} failed. Take appropriate measures!!!");
				return -1;
			}
		}
	}

	/* XXX: Zend complains about the next line "Wrong break depth"
	 * The code is obviously wrong, but I'm not sure what it's supposed to do?
	 */
	if(isset($pkg_config['nosync']))
		continue;
	if(!empty($pkg_config['custom_php_global_functions']))
		eval($pkg_config['custom_php_global_functions']);
	if(!empty($pkg_config['custom_php_resync_config_command']))
		eval($pkg_config['custom_php_resync_config_command']);
	if($sync_depends == true) {
		$depends = get_pkg_depends($pkg_name, ".xml", "files", 1); // Call dependency handler and do a little more error checking.
		if(is_array($depends)) {
			foreach($depends as $item) {
				if(!file_exists($item)) {
					file_notice($package['name'], sprintf(gettext("The %s package is missing required dependencies and must be reinstalled."), $package['name']), "Packages", "/pkg_mgr_install.php?mode=reinstallpkg&pkg={$package['name']}", 1);
					log_error("Could not find {$item}. Reinstalling package.");
					uninstall_package($pkg_name);
					install_package($pkg_name);
				} else {
					$item_config = parse_xml_config_pkg($item, "packagegui");
					if (empty($item_config))
						continue;
					if(isset($item_config['nosync']))
						continue;
					if($item_config['custom_php_command_before_form'] <> "")
						eval($item_config['custom_php_command_before_form']);
					if($item_config['custom_php_resync_config_command'] <> "")
						eval($item_config['custom_php_resync_config_command']);
					if($show_message == true)
						print " " . $item_config['name'];
				}
			}
		}
	}
}

/*
 * pkg_fetch_recursive: Download and install a FreeBSD package and its dependencies. This function provides output to
 * 			a progress bar and output window.
 *
 * XXX: This function needs to return where a pkg_add fails. Our current error messages aren't very descriptive.
 */
function pkg_fetch_recursive($pkgname, $filename, $dependlevel = 0, $base_url = 'http://ftp2.freebsd.org/pub/FreeBSD/ports/i386/packages-8.1-release/Latest') {
	global $pkgent, $static_output, $g, $fd_log;

	$pkg_extension = strrchr($filename, '.');
	$static_output .= "\n" . str_repeat(" ", $dependlevel * 2) . $pkgname . " ";
	$fetchto = "{$g['tmp_path']}/apkg_{$pkgname}{$pkg_extension}";
	download_file_with_progress_bar("{$base_url}/{$filename}", $fetchto);
	$static_output .= " (extracting)";
	update_output_window($static_output);
	$slaveout = "";
	exec("/usr/bin/tar --fast-read -O -f {$fetchto} -x +CONTENTS 2>&1", $slaveout);
	$workingdir = preg_grep("/instmp/", $slaveout);
	$workingdir = $workingdir[0];
	$raw_depends_list = array_values(preg_grep("/\@pkgdep/", $slaveout));
	if($raw_depends_list != "") {
		if($pkgent['exclude_dependency'] != "")
			$raw_depends_list = array_values(preg_grep($pkgent['exclude_dependency'], PREG_GREP_INVERT));
		foreach($raw_depends_list as $adepend) {
			$working_depend = explode(" ", $adepend);
			//$working_depend = explode("-", $working_depend[1]);
			$depend_filename = $working_depend[1] . $pkg_extension;
			if(is_freebsd_pkg_installed($working_depend[1]) === false) {
				pkg_fetch_recursive($working_depend[1], $depend_filename, $dependlevel + 1, $base_url);
			} else {
				//$dependlevel++;
				$static_output .= "\n" . str_repeat(" ", $dependlevel * 2) . $working_depend[1] . " ";
				@fwrite($fd_log, $working_depend[1] . "\n");
			}
		}
	}
	$pkgaddout = "";
	exec("/bin/cat {$g['tmp_path']}/y | /usr/sbin/pkg_add -fv {$fetchto} 2>&1", $pkgaddout);
	@fwrite($fd_log, $pkgname . " " . print_r($pkgaddout, true) . "\n");

	return true;
}

function install_package($package, $pkg_info = "") {
	global $g, $config, $pkg_interface, $fd_log, $static_output, $pkg_interface, $restart_sync;

	/* safe side. Write config below will send to ro again. */
	conf_mount_rw();

	if($pkg_interface == "console") 	
		echo "\n";
	/* open logfiles and begin installation */
	if (!$fd_log) {
		if (!$fd_log = fopen("{$g['tmp_path']}/pkg_mgr_{$package}.log", "w"))
			update_output_window(gettext("Warning, could not open log for writing."));
	}
	/* fetch package information if needed */
	if(empty($pkg_info) or !is_array($pkg_info[$package])) {
		$pkg_info = get_pkg_info(array($package));
		$pkg_info = $pkg_info[$package]; // We're only dealing with one package, so we can strip away the extra array.
	}
	@fwrite($fd_log, gettext("Beginning package installation.\n"));
	log_error(gettext('Beginning package installation for') . ' ' . $pkg_info['name'] . '.');
	update_status(gettext("Beginning package installation for") . " " . $pkg_info['name'] . "...");	
	/* fetch the package's configuration file */
	if($pkg_info['config_file'] != "") {
		$static_output .= gettext("Downloading package configuration file...") . " ";
		update_output_window($static_output);
		@fwrite($fd_log, gettext("Downloading package configuration file...\n"));
		$fetchto = substr(strrchr($pkg_info['config_file'], '/'), 1);
		download_file_with_progress_bar($pkg_info['config_file'], '/usr/local/pkg/' . $fetchto);
		if(!file_exists('/usr/local/pkg/' . $fetchto)) {
			@fwrite($fd_log, gettext("ERROR! Unable to fetch package configuration file. Aborting installation.\n"));
			if($pkg_interface == "console") {
				conf_mount_ro();
				print gettext("\nERROR! Unable to fetch package configuration file. Aborting package installation.\n");
				return;
			} else {
				$static_output .= gettext("failed!") . "\n\n" . gettext("Installation aborted.");
				update_output_window($static_output);
				echo "<br>Show <a href=\"pkg_mgr_install.php?showlog=true\">" . gettext("install log") . "</a></center>";
				conf_mount_ro();
			 	return -1;
			}
		}
		$static_output .= gettext("done.") . "\n";
		update_output_window($static_output);
	}
	/* add package information to config.xml */
	$pkgid = get_pkg_id($pkg_info['name']);
	$static_output .= gettext("Saving updated package information...") . " ";
	update_output_window($static_output);
	if($pkgid == -1) {
		$config['installedpackages']['package'][] = $pkg_info;
		$changedesc = gettext("Installed") . $pkg_info['name'] . gettext("package.");
		$to_output = gettext("done.") . "\n";
	} else {
		$config['installedpackages']['package'][$pkgid] = $pkg_info;
		$changedesc = sprintf(gettext("Overwrote previous installation of %s."), $pkg_info['name']);
		$to_output = gettext("overwrite!") . "\n";
	}
	/* XXX: Fix inclusion of config.inc that causes data loss! */
	conf_mount_ro();
	write_config();
	$static_output .= $to_output;
	update_output_window($static_output);
	/* install other package components */
	if (!install_package_xml($package)) {
		uninstall_package($package);
		write_config($changedesc);
		$static_output .= gettext("Failed to install package.") . "\n";
		update_output_window($static_output);
		return -1;
	} else {
		$static_output .= gettext("Writing configuration... ");
		update_output_window($static_output);
		write_config($changedesc);
		$static_output .= gettext("done.") . "\n";
		update_output_window($static_output);
		$static_output .= gettext("Starting service.") . "\n";
		update_output_window($static_output);
		if($pkg_info['after_install_info']) 
			update_output_window($pkg_info['after_install_info']);	
		start_service($pkg_info['name']);
		$restart_sync = true;
	}
}

function get_after_install_info($package) {
	global $pkg_info;
	/* fetch package information if needed */
	if(!$pkg_info or !is_array($pkg_info[$package])) {
		$pkg_info = get_pkg_info(array($package));
		$pkg_info = $pkg_info[$package]; // We're only dealing with one package, so we can strip away the extra array.
	}
	if($pkg_info['after_install_info'])
		return $pkg_info['after_install_info'];
}

function eval_once($toeval) {
	global $evaled;
	if(!$evaled) $evaled = array();
	$evalmd5 = md5($toeval);
	if(!in_array($evalmd5, $evaled)) {
		@eval($toeval);
		$evaled[] = $evalmd5;
	}
	return;
}

function install_package_xml($pkg) {
	global $g, $config, $fd_log, $static_output, $pkg_interface;

	if(($pkgid = get_pkg_id($pkg)) == -1) {
		$static_output .= sprintf(gettext("The %s package is not installed.%sInstallation aborted."), $pkg, "\n\n");
		update_output_window($static_output);
		if($pkg_interface <> "console") {
			echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";
			echo "\n<script language=\"JavaScript\">document.progholder.style.visibility='hidden';</script>";
		}
		sleep(1);
		return false;
	} else
		$pkg_info = $config['installedpackages']['package'][$pkgid];

	/* set up logging if needed */
	if(!$fd_log) {
		if(!$fd_log = fopen("{$g['tmp_path']}/pkg_mgr_{$pkg}.log", "w")) {
			update_output_window(gettext("Warning, could not open log for writing."));
		}
	}

	/* make 'y' file */
	$fd = fopen("{$g['tmp_path']}/y", "w");
	for($line = 0; $line < 10; $line++) {
		fwrite($fd, "y\n");
	}
	fclose($fd);

	/* pkg_add the package and its dependencies */
	if($pkg_info['depends_on_package_base_url'] != "") {
		if($pkg_interface == "console") 
			echo "\n";
		update_status(gettext("Installing") . " " . $pkg_info['name'] . " " . gettext("and its dependencies."));
		$static_output .= gettext("Downloading") . " " . $pkg_info['name'] . " " . gettext("and its dependencies... ");
		$static_orig = $static_output;
		$static_output .= "\n";
		update_output_window($static_output);
		foreach((array) $pkg_info['depends_on_package'] as $pkgdep) {
			$pkg_name = substr(reverse_strrchr($pkgdep, "."), 0, -1);
			if(isset($pkg_info['skip_install_checks']))
				$pkg_installed = true;
			else
				$pkg_installed = is_freebsd_pkg_installed($pkg_name);

			if($pkg_installed == false)
				pkg_fetch_recursive($pkg_name, $pkgdep, 0, $pkg_info['depends_on_package_base_url']);
			$static_output = $static_orig . gettext("done.") . "\n" . gettext("Checking for successful package installation... ");
			update_output_window($static_output);
			/* make sure our package was successfully installed */
			if($pkg_installed == false)
				$pkg_installed = is_freebsd_pkg_installed($pkg_name);
			if($pkg_installed == true) {
				$static_output .= gettext("done.") . "\n";
				update_output_window($static_output);
				fwrite($fd_log, gettext("pkg_add successfully completed.\n"));
			} else {
				$static_output .= "of {$pkg_name} failed!\n\nInstallation aborted.";
				update_output_window($static_output);
				fwrite($fd_log, gettext("Package WAS NOT installed properly.\n"));
				fclose($fd_log);
				if($pkg_interface <> "console") {
					echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";
					echo "\n<script language=\"JavaScript\">document.progholder.style.visibility='hidden';</script>";
				}
				sleep(1);
				return false;
			}
		}
	}
	$configfile = substr(strrchr($pkg_info['config_file'], '/'), 1);
	if(file_exists("/usr/local/pkg/" . $configfile)) {
		$static_output .= gettext("Loading package configuration... ");
		update_output_window($static_output);
		$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $configfile, "packagegui");
		$static_output .= gettext("done.") . "\n";
		update_output_window($static_output);
		$static_output .= gettext("Configuring package components...") . "\n";
		if (!empty($pkg_config['filter_rules_needed']))
			$config['installedpackages']['package'][$pkgid]['filter_rule_function'] = $pkg_config['filter_rules_needed'];
		update_output_window($static_output);
		/* modify system files */
		if(is_array($pkg_config['modify_system']['item'])) {
			$static_output .= "\t" . gettext("System files... ");
			update_output_window($static_output);
			foreach($pkg_config['modify_system']['item'] as $ms) {
				if($ms['textneeded']) {
					add_text_to_file($ms['modifyfilename'], $ms['textneeded']);
				}
			}
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* download additional files */
		if(is_array($pkg_config['additional_files_needed'])) {
			$static_output .= "\t" . gettext("Additional files... ");
			$static_orig = $static_output;
			update_output_window($static_output);
			foreach($pkg_config['additional_files_needed'] as $afn) {
				$filename = get_filename_from_url($afn['item'][0]);
				if($afn['chmod'] <> "")
					$pkg_chmod = $afn['chmod'];
				else
					$pkg_chmod = "";

				if($afn['prefix'] <> "")
					$prefix = $afn['prefix'];
				else
					$prefix = "/usr/local/pkg/";

				if(!is_dir($prefix)) 
					safe_mkdir($prefix);
 				$static_output .= $filename . " ";
                                update_output_window($static_output);
				download_file_with_progress_bar($afn['item'][0], $prefix . $filename);
				if(stristr($filename, ".tgz") <> "") {
					fwrite($fd_log, gettext("Extracting tarball to -C for") . " " . $filename . "...\n");
					$tarout = "";
					exec("/usr/bin/tar xvzf " . $prefix . $filename . " -C / 2>&1", $tarout);
					fwrite($fd_log, print_r($tarout, true) . "\n");
				}
				if($pkg_chmod <> "") {
					fwrite($fd_log, sprintf(gettext('Changing file mode to %1$s for %2$s%3$s%4$s'), $pkg_chmod, $prefix, $filename, "\n"));
					@chmod($prefix . $filename, $pkg_chmod);
					system("/bin/chmod {$pkg_chmod} {$prefix}{$filename}");
				}
				$static_output = $static_orig;
                                update_output_window($static_output);
			}
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/*   if a require exists, include it.  this will
		 *   show us where an error exists in a package
		 *   instead of making us blindly guess
		 */
		if($pkg_config['include_file'] <> "") {
			$static_output = gettext("Loading package instructions...");
			update_output_window($static_output);
			fwrite($fd_log, "require_once('{$pkg_config['include_file']}')\n");
			if (file_exists($pkg_config['include_file']))
				require_once($pkg_config['include_file']);
		}
		/* sidebar items */
		if(is_array($pkg_config['menu'])) {
			$static_output .= "\t" . gettext("Menu items... ");
			update_output_window($static_output);
			foreach($pkg_config['menu'] as $menu) {
				if(is_array($config['installedpackages']['menu']))
					foreach($config['installedpackages']['menu'] as $amenu)
						if($amenu['name'] == $menu['name'])
							continue 2;
				$config['installedpackages']['menu'][] = $menu;
			}
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* integrated tab items */
		if(is_array($pkg_config['tabs']['tab'])) {
			$static_output .= "\t" . gettext("Integrated Tab items... ");
			update_output_window($static_output);
			foreach($pkg_config['tabs']['tab'] as $tab) {
				if(is_array($config['installedpackages']['tab']))
					foreach($config['installedpackages']['tab'] as $atab)
						if($atab['name'] == $tab['name'])
							continue 2;
				$config['installedpackages']['tab'][] = $tab;
			}
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* services */
		if(is_array($pkg_config['service'])) {
			$static_output .= "\t" . gettext("Services... ");
			update_output_window($static_output);
			foreach($pkg_config['service'] as $service) {
				if(is_array($config['installedpackages']['service']))
					foreach($config['installedpackages']['service'] as $aservice)
						if($aservice['name'] == $service['name'])
							continue 2;
				$config['installedpackages']['service'][] = $service;
			}
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* custom commands */
		$static_output .= "\t" . gettext("Custom commands... ");
		update_output_window($static_output);
		if($pkg_config['custom_php_global_functions'] <> "") {
			$static_output = gettext("Executing custom_php_global_functions()...");
			update_output_window($static_output);
			eval_once($pkg_config['custom_php_global_functions']);
		}
		if($pkg_config['custom_php_install_command']) {
			$static_output = gettext("Executing custom_php_install_command()...");
			update_output_window($static_output);
			eval_once($pkg_config['custom_php_install_command']);
		}
		if($pkg_config['custom_php_resync_config_command'] <> "") {
			$static_output = gettext("Executing custom_php_resync_config_command()...");
			update_output_window($static_output);
			eval_once($pkg_config['custom_php_resync_config_command']);
		}
		$static_output .= gettext("done.") . "\n";
		update_output_window($static_output);
	} else {
		$static_output .= gettext("Loading package configuration... failed!") . "\n\n" . gettext("Installation aborted.");
		update_output_window($static_output);
		fwrite($fd_log, gettext("Unable to load package configuration. Installation aborted.\n"));
		fclose($fd_log);
		if($pkg_interface <> "console") {
			echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";
			echo "\n<script language=\"JavaScript\">document.progholder.style.visibility='hidden';</script>";
		}
		sleep(1);
		return false;
	}

	/* set up package logging streams */
	if($pkg_info['logging']) {
		mwexec("/usr/sbin/fifolog_create -s 32768 {$g['varlog_path']}/{$pkg_info['logging']['logfilename']}");
		@chmod($g['varlog_path'] . '/' . $pkg_info['logging']['logfilename'], 0600);
		@fwrite($fd_log, "Adding text to file /etc/syslog.conf\n");
		if(is_process_running("syslogd"))
			mwexec("killall syslogd");
		system_syslogd_start();
	}

	return true;
}

function delete_package($pkg, $pkgid) {
	global $g, $config, $fd_log, $static_output;

	update_status(gettext("Removing package..."));
	$static_output .= gettext("Removing package... ");
	update_output_window($static_output);
	if (!is_array($config['installedpackages']['package']))
		return;

	$pkg_info =& $config['installedpackages']['package'][$pkgid];
	if (empty($pkg_info))
		return;
	if (empty($pkg_info['configurationfile'])) 
		return;

	$static_output .= sprintf(gettext("%sStarting package deletion for %s...%s"), "\n", $pkg_info['name'], "\n");
	update_output_window($static_output);
	if (!empty($pkg))
		delete_package_recursive($pkg);
	$static_output .= gettext("done.") . "\n";
	update_output_window($static_output);

	return;
}

function delete_package_recursive($pkg) {
	global $config, $g;

	$fd = fopen("{$g['tmp_path']}/y", "w");
	for($line = 0; $line < 10; $line++) {
		fwrite($fd, "y\n");
	}
	fclose($fd);
	$pkg = substr(reverse_strrchr($pkg, "."), 0, -1);
	$info = "";
	exec("/usr/sbin/pkg_info -r {$pkg} 2>&1", $info);
	remove_freebsd_package($pkg);
	$pkgdb = "";
	exec("/bin/ls {$g['vardb_path']}/pkg", $pkgdb);
	foreach($info as $line) {
		$depend = trim(array_pop(explode(":", $line)));
		if(in_array($depend, $pkgdb)) 
			delete_package_recursive($depend);
	}
	return;
}

function delete_package_xml($pkg) {
	global $g, $config, $fd_log, $static_output, $pkg_interface;

	conf_mount_rw();

	$pkgid = get_pkg_id($pkg);
	if ($pkgid == -1) {
		$static_output .= sprintf(gettext("The %s package is not installed.%sDeletion aborted."), $pkg, "\n\n");
		update_output_window($static_output);
		if($pkg_interface <> "console") {
			echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";
			echo "\n<script language=\"JavaScript\">document.progholder.style.visibility='hidden';</script>";
		}
		ob_flush();
		sleep(1);
		conf_mount_ro();
		return;
	}
	/* set up logging if needed */
	if(!$fd_log) {
		if(!$fd_log = fopen("{$g['tmp_path']}/pkg_mgr_{$pkg}.log", "w")) {
			update_output_window(gettext("Warning, could not open log for writing."));
		}
	}
	update_status(sprintf(gettext("Removing %s components..."), $pkg));
	fwrite($fd_log, sprintf(gettext("Removing %s package... "), $pkg));
	$static_output .= sprintf(gettext("Removing %s components...%s"), $pkg, "\n");
	update_output_window($static_output);
	/* parse package configuration */
	$packages = &$config['installedpackages']['package'];
	$tabs =& $config['installedpackages']['tab'];
	$menus =& $config['installedpackages']['menu'];
	$services = &$config['installedpackages']['service'];
	if(file_exists("/usr/local/pkg/" . $packages[$pkgid]['configurationfile'])) {
		$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $packages[$pkgid]['configurationfile'], "packagegui");
		/* remove tab items */
		if(is_array($pkg_config['tabs'])) {
			$static_output .= "\t" . gettext("Tabs items... ");
			update_output_window($static_output);
			if(is_array($pkg_config['tabs']['tab']) && is_array($tabs)) {
				foreach($pkg_config['tabs']['tab'] as $tab) {
					foreach($tabs as $key => $insttab) {
						if($insttab['name'] == $tab['name']) {
							unset($tabs[$key]);
							break;
						}
					}
				}
			}
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* remove menu items */
		if(is_array($pkg_config['menu'])) {
			$static_output .= "\t" . gettext("Menu items... ");
			update_output_window($static_output);
			if (is_array($pkg_config['menu']) && is_array($menus)) {
				foreach($pkg_config['menu'] as $menu) {
					foreach($menus as $key => $instmenu) {
						if($instmenu['name'] == $menu['name']) {
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
		if(is_array($pkg_config['service'])) {
			$static_output .= "\t" . gettext("Services... ");
			update_output_window($static_output);
			if (is_array($pkg_config['service']) && is_array($services)) {
				foreach($pkg_config['service'] as $service) {
					foreach($services as $key => $instservice) {
						if($instservice['name'] == $service['name']) {
							stop_service($service['name']);
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
		write_config();

		/*
		 * If a require exists, include it.  this will
		 * show us where an error exists in a package
		 * instead of making us blindly guess
		 */
		if($pkg_config['include_file'] <> "") {
			$static_output = gettext("Loading package instructions...");
			update_output_window($static_output);
			fwrite($fd_log, "require_once(\"{$pkg_config['include_file']}\")\n");
			if(file_exists($pkg_config['include_file']))
				require_once($pkg_config['include_file']);
			fwrite($fd_log, "require_once(\"{$pkg_config['include_file']}\") included\n");
		}
		/* evalate this package's global functions and pre deinstall commands */
		if($pkg_config['custom_php_global_functions'] <> "")
			eval_once($pkg_config['custom_php_global_functions']);
		if($pkg_config['custom_php_pre_deinstall_command'] <> "")
			eval_once($pkg_config['custom_php_pre_deinstall_command']);
		/* system files */
		if(is_array($pkg_config['modify_system']['item'])) {
			$static_output .= "\t" . gettext("System files... ");
			update_output_window($static_output);
			foreach($pkg_config['modify_system']['item'] as $ms)
				if($ms['textneeded']) remove_text_from_file($ms['modifyfilename'], $ms['textneeded']);

			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* syslog */
		if($pkg_config['logging']['logfile_name'] <> "") {
			$static_output .= "\t" . gettext("Syslog entries... ");
			update_output_window($static_output);
			remove_text_from_file("/etc/syslog.conf", $pkg_config['logging']['facilityname'] . "\t\t\t\t" . $pkg_config['logging']['logfilename']);
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* deinstall commands */
		if($pkg_config['custom_php_deinstall_command'] <> "") {
			$static_output .= "\t" . gettext("Deinstall commands... ");
			update_output_window($static_output);
			eval_once($pkg_config['custom_php_deinstall_command']);
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		if($pkg_config['include_file'] <> "") {
                        $static_output = "\t" . gettext("Removing pacakge instructions...");
                        update_output_window($static_output);
                        fwrite($fd_log, sprintf(gettext("Remove '%s'%s"), $pkg_config['include_file'], "\n"));
                        unlink_if_exists("/usr/local/pkg/" . $pkg_config['include_file']);
			$static_output .= "done.\n";
                        update_output_window($static_output);
			
                }
		/* remove all additional files */
		if(is_array($pkg_config['additional_files_needed'])) {
			$static_output .= "\t" . gettext("Auxiliary files... ");
			update_output_window($static_output);
			foreach($pkg_config['additional_files_needed'] as $afn) {
				$filename = get_filename_from_url($afn['item'][0]);
				if($afn['prefix'] <> "")
					$prefix = $afn['prefix'];
				else
					$prefix = "/usr/local/pkg/";

				unlink_if_exists($prefix . $filename);
			}
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* package XML file */
		$static_output .= "\t" . gettext("Package XML... ");
		update_output_window($static_output);
		unlink_if_exists("/usr/local/pkg/" . $packages[$pkgid]['configurationfile']);
		$static_output .= gettext("done.") . "\n";
		update_output_window($static_output);
	}
	/* remove config.xml entries */
	conf_mount_ro();
	$static_output .= "\t" . gettext("Configuration... ");
	update_output_window($static_output);
	unset($config['installedpackages']['package'][$pkgid]);
	$static_output .= gettext("done.") . "\n";
	update_output_window($static_output);
	write_config(sprintf(gettext("Removed %s package."), $pkg));
	/* file cleanup */
	$ctag = file("/etc/crontab");
	foreach($ctag as $line)
		if(trim($line) != "")
			$towrite[] = $line;

	$tmptab = fopen("{$g['tmp_path']}/crontab", "w");
	foreach($towrite as $line)
		fwrite($tmptab, $line);
	fclose($tmptab);

	// Go RW again since the write_config above will put it back to RO
	conf_mount_rw();
	rename("{$g['tmp_path']}/crontab", "/etc/crontab");
	conf_mount_ro();
}

function expand_to_bytes($size) {
	$conv = array(
			"G" =>	"3",
			"M" =>  "2",
			"K" =>  "1",
			"B" =>  "0"
		);
	$suffix = substr($size, -1);
	if(!in_array($suffix, array_keys($conv))) return $size;
	$size = substr($size, 0, -1);
	for($i = 0; $i < $conv[$suffix]; $i++) {
		$size *= 1024;
	}
	return $size;
}

function get_pkg_db() {
	global $g;
	return return_dir_as_array($g['vardb_path'] . '/pkg');
}

function walk_depend($depend, $pkgdb = "", $alreadyseen = "") {
	if(!$pkgdb)
		$pkgdb = get_pkg_db();
	if(!is_array($alreadyseen))
		$alreadyseen = array();
	if (!is_array($depend))
		$depend = array();
	foreach($depend as $adepend) {
		$pkgname = reverse_strrchr($adepend['name'], '.');
		if(in_array($pkgname, $alreadyseen)) {
			continue;
		} elseif(!in_array($pkgname, $pkgdb)) {
			$size += expand_to_bytes($adepend['size']);
			$alreadyseen[] = $pkgname;
			if(is_array($adepend['depend'])) $size += walk_depend($adepend['depend'], $pkgdb, $alreadyseen);
		}
	}
	return $size;
}

function get_package_install_size($pkg = 'all', $pkg_info = "") {
	global $config, $g;
	if((!is_array($pkg)) and ($pkg != 'all'))
		$pkg = array($pkg);
	$pkgdb = get_pkg_db();
	if(!$pkg_info)
		$pkg_info = get_pkg_sizes($pkg);
	foreach($pkg as $apkg) {
		if(!$pkg_info[$apkg]) continue;
		$toreturn[$apkg] = expand_to_bytes(walk_depend(array($pkg_info[$apkg]), $pkgdb));
	}
	return $toreturn;
}

function squash_from_bytes($size, $round = "") {
	$conv = array(1 => "B", "K", "M", "G");
	foreach($conv as $div => $suffix) {
		$sizeorig = $size;
		if(($size /= 1024) < 1) {
			if($round) {
				$sizeorig = round($sizeorig, $round);
			}
			return $sizeorig . $suffix;
		}
	}
	return;
}

?>
