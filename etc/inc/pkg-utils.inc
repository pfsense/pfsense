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
 * Copyright (C) 2010 Ermal Luçi
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
	pfSense_BUILDER_BINARIES:	/usr/bin/cd	/usr/bin/tar	/usr/sbin/fifolog_create	/bin/chmod
	pfSense_BUILDER_BINARIES:	/usr/sbin/pkg_add	/usr/sbin/pkg_info	/usr/sbin/pkg_delete	/bin/rm
	pfSense_MODULE:	pkg
*/

require_once("globals.inc");
require_once("xmlrpc.inc");
require_once("service-utils.inc");
if(file_exists("/cf/conf/use_xmlreader"))
	require_once("xmlreader.inc");
else
	require_once("xmlparse.inc");
require_once("service-utils.inc");
require_once("pfsense-utils.inc");

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

if (!function_exists("pkg_debug")) {
	/* set up logging if needed */
	function pkg_debug($msg) {
		global $g, $debug, $fd_log;

		if (!$debug)
			return;

		if (!$fd_log) {
			if (!$fd_log = fopen("{$g['tmp_path']}/pkg_mgr_{$package}.log", "w"))
				update_output_window("Warning, could not open log for writing.");
		}
		@fwrite($fd_log, $msg);
	}
}

$vardb = "/var/db/pkg";
safe_mkdir($vardb);
$g['platform'] = trim(file_get_contents("/etc/platform"));

conf_mount_rw();
if(!is_dir("/usr/local/pkg") or !is_dir("/usr/local/pkg/pf")) {
	safe_mkdir("/usr/local/pkg");
	safe_mkdir("/usr/local/pkg/pf");	
}
conf_mount_ro();

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
	exec("/usr/sbin/pkg_delete -x {$packagestring} 2>>/tmp/pkg_delete_errors.txt");
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

	if (is_array($config['installedpackages']['package'])) {
		foreach($config['installedpackages']['package'] as $idx => $pkg) {
			if($pkg['name'] == $pkg_name)
				return $idx;
		}
	}
	return -1;
}

/****f* pkg-utils/get_pkg_info
 * NAME
 *   get_pkg_info - Retrieve package information from pfsense.com.
 * INPUTS
 *   $pkgs - 'all' to retrieve all packages, an array containing package names otherwise
 *   $info - 'all' to retrieve all information, an array containing keys otherwise
 * RESULT
 *   $raw_versions - Array containing retrieved information, indexed by package name.
 ******/
function get_pkg_info($pkgs = 'all', $info = 'all') {
	global $g;

	$freebsd_version = php_uname("r");
	$freebsd_machine = php_uname("m");
	$params = array(
		"pkg" => $pkgs, 
		"info" => $info, 
		"freebsd_version" => $freebsd_version[0],
		"freebsd_machine" => $freebsd_machine
	);
	$resp = call_pfsense_method('pfsense.get_pkgs', $params, 10);
	return $resp ? $resp : array();
}

function get_pkg_sizes($pkgs = 'all') {
	global $config, $g;

	$freebsd_version = php_uname("r");
	$freebsd_machine = php_uname("m");
	$params = array(
		"pkg" => $pkgs, 
		"freebsd_version" => $freebsd_version,
		"freebsd_machine" => $freebsd_machine
	);
	$msg = new XML_RPC_Message('pfsense.get_pkg_sizes', array(php_value_to_xmlrpc($params)));
	$xmlrpc_base_url = isset($config['system']['altpkgrepo']['enable']) ? $config['system']['altpkgrepo']['xmlrpcbaseurl'] : $g['xmlrpcbaseurl'];
	$cli = new XML_RPC_Client($g['xmlrpcpath'], $xmlrpc_base_url);
	// If the ALT PKG Repo has a username/password set, use it.
	if($config['system']['altpkgrepo']['username'] && 
	   $config['system']['altpkgrepo']['password']) {
		$username = $config['system']['altpkgrepo']['username'];
		$password = $config['system']['altpkgrepo']['password'];
		$cli->setCredentials($username, $password);
	}
	elseif($g['xmlrpcauthuser'] && $g['xmlrpcauthpass']) {
		$username = $g['xmlrpcauthuser'];
		$password = $g['xmlrpcauthpass'];
		$cli->setCredentials($username, $password);
	}
	$resp = $cli->send($msg, 10);
	if(!is_object($resp))
		log_error("Could not get response from XMLRPC server!");
 	else if (!$resp->faultCode()) {
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
	global $config, $pkg_interface, $g;

	log_error("Resyncing configuration for all packages.");

	if (!is_array($config['installedpackages']['package']))
		return;

	if($show_message == true)
		echo "Syncing packages:";

	conf_mount_rw();

	foreach($config['installedpackages']['package'] as $idx => $package) {
		if (empty($package['name']))
			continue;
		if($show_message == true)
			echo " " . $package['name'];
		get_pkg_depends($package['name'], "all");
		if($g['booting'] != true)
			stop_service($package['name']);
		sync_package($idx, true, true);
		if($pkg_interface == "console") 
			echo "\nSyncing packages:";
	}

	if($show_message == true)
		echo " done.\n";

	@unlink("/conf/needs_package_sync");
	conf_mount_ro();
}

/*
 * is_freebsd_pkg_installed() - Check /var/db/pkg to determine whether or not a FreeBSD
 *				package is installed.
 */
function is_freebsd_pkg_installed($pkg) {
	if(!$pkg) 
		return;
	$output = "";
	exec("/usr/sbin/pkg_info -E \"{$pkg}*\"", $output, $retval);

	return (intval($retval) == 0);
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

	$pkg_id = get_pkg_id($pkg_name);
	if($pkg_id == -1)
		return -1; // This package doesn't really exist - exit the function.
	else if (!isset($config['installedpackages']['package'][$pkg_id]))
		return; // No package belongs to the pkg_id passed to this function.

	$package =& $config['installedpackages']['package'][$pkg_id];
	if(!file_exists("/usr/local/pkg/" . $package['configurationfile'])) {
		log_error("The {$package['name']} package is missing required dependencies and is being reinstalled." . $package['configurationfile']);
		uninstall_package($package['name']);
		if (install_package($package['name']) < 0) {
			log_error("Failed reinstalling package {$package['name']}.");
			return false;
		}
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
				log_error("The {$package['name']} package is missing required dependencies and must be reinstalled.");
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
	global $config, $static_output;
	global $builder_package_install;

	// Back up /usr/local/lib libraries first if
	// not running from the builder code.
	// also take into account rrd binaries
	if(!$builder_package_install) {
		if(!file_exists("/tmp/pkg_libs.tgz")) {
			$static_output .= "Backing up libraries... ";
			update_output_window($static_output);
			exec("/usr/bin/tar czPf /tmp/pkg_libs.tgz `/bin/cat /etc/pfSense_md5.txt | /usr/bin/grep 'local/lib' | /usr/bin/awk '{ print $2 }' | /usr/bin/cut -d'(' -f2 | /usr/bin/cut -d')' -f1`");
			exec("/usr/bin/tar czPf /tmp/pkg_bins.tgz `/bin/cat /etc/pfSense_md5.txt | /usr/bin/grep 'rrd' | /usr/bin/awk '{ print $2 }' | /usr/bin/cut -d'(' -f2 | /usr/bin/cut -d')' -f1`");
			$static_output .= "\n";
		}
	}

	stop_service($pkg_name);

	$id = get_pkg_id($pkg_name);
	if ($id >= 0) {
		$pkg_depends =& $config['installedpackages']['package'][$id]['depends_on_package'];
		$static_output .= "Removing package...\n";
		update_output_window($static_output);
		if (is_array($pkg_depends)) {
			foreach ($pkg_depends as $pkg_depend)
				delete_package($pkg_depend);
		}
	}
	delete_package_xml($pkg_name);

	// Restore libraries that we backed up if not 
	// running from the builder code.
	if(!$builder_package_install) {
		$static_output .= "Cleaning up... ";
		update_output_window($static_output);
		exec("/usr/bin/tar xzPfU /tmp/pkg_libs.tgz -C /");
		exec("/usr/bin/tar xzPfU /tmp/pkg_bins.tgz -C /");
		@unlink("/tmp/pkg_libs.tgz");
		@unlink("/tmp/pkg_bins.tgz");
	}
}

function force_remove_package($pkg_name) {
	delete_package_xml($pkg_name);
}

/*
 * sync_package($pkg_name, $sync_depends = true, $show_message = false) Force a package to setup its configuration and rc.d files.
 */
function sync_package($pkg_name, $sync_depends = true, $show_message = false) {
	global $config, $config_parsed;
	global $builder_package_install;
	
	// If this code is being called by pfspkg_installer 
	// which the builder system uses then return (ignore).
	if($builder_package_install)
		return;
	
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
		log_error("The {$package['name']} package is missing its configuration file and must be reinstalled.");
		force_remove_package($package['name']);
		return -1;
	}
	$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], "packagegui");
	if(isset($pkg_config['nosync']))
		return;
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

	if(!empty($pkg_config['custom_php_global_functions']))
		eval($pkg_config['custom_php_global_functions']);
	if(!empty($pkg_config['custom_php_resync_config_command']))
		eval($pkg_config['custom_php_resync_config_command']);
	if($sync_depends == true) {
		$depends = get_pkg_depends($pkg_name, ".xml", "files", 1); // Call dependency handler and do a little more error checking.
		if(is_array($depends)) {
			foreach($depends as $item) {
				if(!file_exists($item)) {
					require_once("notices.inc");
					file_notice($package['name'], "The {$package['name']} package is missing required dependencies and must be reinstalled.", "Packages", "/pkg_mgr_install.php?mode=reinstallpkg&pkg={$package['name']}", 1);
					log_error("Could not find {$item}. Reinstalling package.");
					uninstall_package($pkg_name);
					if (install_package($pkg_name) < 0) {
						log_error("Reinstalling package {$package['name']} failed. Take appropriate measures!!!");
						return -1;
					}
				} else {
					$item_config = parse_xml_config_pkg($item, "packagegui");
					if (empty($item_config))
						continue;
					if(isset($item_config['nosync']))
						continue;
					if (!empty($item_config['include_file'])) {
						if (file_exists($item_config['include_file']))	
							require_once($item_config['include_file']);
						else {
							log_error("Not calling package sync code for dependency {$item_config['name']} of {$package['name']} because some include files are missing.");
							continue;
						}
					}
					if($item_config['custom_php_global_functions'] <> "")
						eval($item_config['custom_php_global_functions']);
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
 */
function pkg_fetch_recursive($pkgname, $filename, $dependlevel = 0, $base_url = "") {
	global $static_output, $g;

	if (($g['platform'] == "nanobsd") || ($g['platform'] == "embedded")) {
		$pkgtmpdir = "/usr/bin/env PKG_TMPDIR=/root/ ";
		$pkgstagingdir = "/root/tmp";
		if (!is_dir($pkgstagingdir))
			mkdir($pkgstagingdir);
		$pkgstaging = "-t {$pkgstagingdir}/instmp.XXXXXX";
		$fetchdir = $pkgstagingdir;
	} else {
		$fetchdir = $g['tmp_path'];
	}

	$osname = php_uname("s");
	$arch =  php_uname("m");
	$rel = strtolower(php_uname("r"));
	if (substr_count($rel, '-') > 1)
		$rel = substr($rel, 0, strrpos($rel, "-"));
	$priv_url = "http://ftp2.{$osname}.org/pub/{$osname}/ports/{$arch}/packages-{$rel}/All";
	if (empty($base_url))
		$base_url = $priv_url;
	if (substr($base_url, -1) == "/")
		$base_url = substr($base_url, 0, -1);
	$fetchto = "{$fetchdir}/apkg_{$filename}";
	$static_output .= "\n" . str_repeat(" ", $dependlevel * 2 + 1) . "Downloading {$base_url}/{$filename} ... ";
	update_output_window($static_output);
	if (download_file_with_progress_bar("{$base_url}/{$filename}", $fetchto) !== true) {
		if ($base_url != $priv_url && download_file_with_progress_bar("{$priv_url}/{$filename}", $fetchto) !== true) {
			$static_output .= " could not download from there or {$priv_url}/{$filename}.\n";
			update_output_window($static_output);
			return false;
		} else if ($base_url == $priv_url) {
			$static_output .= " failed to download.\n";
			update_output_window($static_output);
			return false;
		} else {
			$static_output .= " [{$osname} repository]\n";
			update_output_window($static_output);
		}
	}
	$static_output .= " (extracting)\n";
	update_output_window($static_output);
	$slaveout = "";
	exec("/usr/bin/tar --fast-read -O -f {$fetchto} -x +CONTENTS 2>&1", $slaveout);
	$raw_depends_list = array_values(preg_grep("/\@pkgdep/", $slaveout));
	if ($raw_depends_list != "") {
		$pkg_extension = ".tbz";
		foreach($raw_depends_list as $adepend) {
			$working_depend = explode(" ", trim($adepend, "\n"));
			if (substr($working_depend[1], -4) != ".tbz")
				$depend_filename = $working_depend[1] . $pkg_extension;
			else
				$depend_filename = $working_depend[1];
			if (!is_freebsd_pkg_installed($working_depend[1])) {
				if (pkg_fetch_recursive($working_depend[1], $depend_filename, $dependlevel + 1, $base_url) == false)
					return false;
			} else {
				pkg_debug($working_depend[1] . "\n");
			}
		}
	}

	$pkgaddout = "";
	exec("{$pkgtmpdir}/usr/sbin/pkg_add {$pkgstaging} -fv {$fetchto} 2>&1", $pkgaddout);
	pkg_debug($pkgname . " " . print_r($pkgaddout, true) . "\npkg_add successfully completed.\n");

	return true;
}

function install_package($package, $pkg_info = "") {
	global $g, $config, $static_output, $pkg_interface;

	/* safe side. Write config below will send to ro again. */
	conf_mount_rw();

	if($pkg_interface == "console") 	
		echo "\n";
	/* fetch package information if needed */
	if(empty($pkg_info) or !is_array($pkg_info[$package])) {
		$pkg_info = get_pkg_info(array($package));
		$pkg_info = $pkg_info[$package]; // We're only dealing with one package, so we can strip away the extra array.
		if (empty($pkg_info)) {
			conf_mount_ro();
			return -1;
		}
	}
	pkg_debug("Beginning package installation.\n");
	log_error('Beginning package installation for ' . $pkg_info['name'] . '.');
	$static_output .= "Beginning package installation for " . $pkg_info['name'] . "...";
	update_status($static_output);
	/* fetch the package's configuration file */
	if($pkg_info['config_file'] != "") {
		$static_output .= "\nDownloading package configuration file... ";
		update_output_window($static_output);
		pkg_debug("Downloading package configuration file...\n");
		$fetchto = substr(strrchr($pkg_info['config_file'], '/'), 1);
		download_file_with_progress_bar($pkg_info['config_file'], '/usr/local/pkg/' . $fetchto);
		if(!file_exists('/usr/local/pkg/' . $fetchto)) {
			pkg_debug("ERROR! Unable to fetch package configuration file. Aborting installation.\n");
			if($pkg_interface == "console")
				print "\nERROR! Unable to fetch package configuration file. Aborting package installation.\n";
			else {
				$static_output .= "failed!\n\nInstallation aborted.\n";
				update_output_window($static_output);
				echo "<br>Show <a href=\"pkg_mgr_install.php?showlog=true\">install log</a></center>";
			}
			conf_mount_ro();
			return -1;
		}
		$static_output .= "done.\n";
		update_output_window($static_output);
	}
	/* add package information to config.xml */
	$pkgid = get_pkg_id($pkg_info['name']);
	$static_output .= "Saving updated package information... ";
	update_output_window($static_output);
	if($pkgid == -1) {
		$config['installedpackages']['package'][] = $pkg_info;
		$changedesc = "Installed {$pkg_info['name']} package.";
		$to_output = "done.\n";
	} else {
		$config['installedpackages']['package'][$pkgid] = $pkg_info;
		$changedesc = "Overwrote previous installation of {$pkg_info['name']}.";
		$to_output = "overwrite!\n";
	}

	if(file_exists('/conf/needs_package_sync'))
		@unlink('/conf/needs_package_sync');
	write_config("Intermediate config write during package install for {$pkg_info['name']}.");
	conf_mount_rw(); //Compensate write_config() sending to _ro

	$static_output .= $to_output;
	update_output_window($static_output);
	/* install other package components */
	if (!install_package_xml($package)) {
		uninstall_package($package);
		write_config($changedesc);
		conf_mount_rw(); //Compensate write_config() sending to _ro
		$static_output .= "Failed to install package.\n";
		update_output_window($static_output);
		return -1;
	} else {
		$static_output .= "Writing configuration... ";
		update_output_window($static_output);
		write_config($changedesc);
		conf_mount_rw(); //Compensate write_config() sending to _ro
		$static_output .= "done.\n";
		update_output_window($static_output);
		if($pkg_info['after_install_info']) 
			update_output_window($pkg_info['after_install_info']);	
	}
	conf_mount_ro();
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
	global $g, $config, $static_output, $pkg_interface, $config_parsed;

	if(($pkgid = get_pkg_id($pkg)) == -1) {
		$static_output .= "The {$pkg} package is not installed.\n\nInstallation aborted.";
		update_output_window($static_output);
		if($pkg_interface <> "console") {
			echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";
			echo "\n<script language=\"JavaScript\">document.progholder.style.visibility='hidden';</script>";
		}
		sleep(1);
		return false;
	} else
		$pkg_info = $config['installedpackages']['package'][$pkgid];

	/* pkg_add the package and its dependencies */
	if($pkg_info['depends_on_package_base_url'] != "") {
		if($pkg_interface == "console") 
			echo "\n";
		update_status("Installing " . $pkg_info['name'] . " and its dependencies.");
		$static_output .= "Downloading " . $pkg_info['name'] . " and its dependencies... ";
		$static_orig = $static_output;
		$static_output .= "\n";
		update_output_window($static_output);
		foreach((array) $pkg_info['depends_on_package'] as $pkgdep) {
			$pkg_name = substr(reverse_strrchr($pkgdep, "."), 0, -1);
			$static_output = $static_orig . "\nChecking for package installation... ";
			update_output_window($static_output);
			if (!is_freebsd_pkg_installed($pkg_name)) {
				if (!pkg_fetch_recursive($pkg_name, $pkgdep, 0, $pkg_info['depends_on_package_base_url'])) {
					$static_output .= "of {$pkg_name} failed!\n\nInstallation aborted.";
					update_output_window($static_output);
					pkg_debug("Package WAS NOT installed properly.\n");
					if($pkg_interface <> "console") {
						echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";
						echo "\n<script language=\"JavaScript\">document.progholder.style.visibility='hidden';</script>";
					}
					sleep(1);
					return false;
				}
			}
		}
	}
	$configfile = substr(strrchr($pkg_info['config_file'], '/'), 1);
	if(file_exists("/usr/local/pkg/" . $configfile)) {
		$static_output .= "Loading package configuration... ";
		update_output_window($static_output);
		$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $configfile, "packagegui");
		$static_output .= "done.\n";
		update_output_window($static_output);
		$static_output .= "Configuring package components...\n";
		if (!empty($pkg_config['filter_rules_needed']))
			$config['installedpackages']['package'][$pkgid]['filter_rule_function'] = $pkg_config['filter_rules_needed'];
		update_output_window($static_output);
		/* modify system files */
		if(is_array($pkg_config['modify_system']) && is_array($pkg_config['modify_system']['item'])) {
			$static_output .= "System files... ";
			update_output_window($static_output);
			foreach($pkg_config['modify_system']['item'] as $ms) {
				if($ms['textneeded']) {
					add_text_to_file($ms['modifyfilename'], $ms['textneeded']);
				}
			}
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* download additional files */
		if(is_array($pkg_config['additional_files_needed'])) {
			$static_output .= "Additional files... ";
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
				if (download_file_with_progress_bar($afn['item'][0], $prefix . $filename) !== true) {
					$static_output .= "failed.\n";
					@unlink($prefix . $filename);
					update_output_window($static_output);
					return false;
				}
				if(stristr($filename, ".tgz") <> "") {
					pkg_debug("Extracting tarball to -C for " . $filename . "...\n");
					$tarout = "";
					exec("/usr/bin/tar xvzf " . $prefix . $filename . " -C / 2>&1", $tarout);
					pkg_debug(print_r($tarout, true) . "\n");
				}
				if($pkg_chmod <> "") {
					pkg_debug("Changing file mode to {$pkg_chmod} for {$prefix}{$filename}\n");
					@chmod($prefix . $filename, $pkg_chmod);
					system("/bin/chmod {$pkg_chmod} {$prefix}{$filename}");
				}
				$static_output = $static_orig;
                                update_output_window($static_output);
			}
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/*   if a require exists, include it.  this will
		 *   show us where an error exists in a package
		 *   instead of making us blindly guess
		 */
		$missing_include = false;
		if($pkg_config['include_file'] <> "") {
			$static_output .= "Loading package instructions...\n";
			update_output_window($static_output);
			pkg_debug("require_once('{$pkg_config['include_file']}')\n");
			if (file_exists($pkg_config['include_file']))
				require_once($pkg_config['include_file']);
			else {
				$missing_include = true;
				$static_output .= "Include " . basename($pkg_config['include_file']) . " is missing!\n";
				update_output_window($static_output);
				/* XXX: Should undo the steps before this?! */
				return false;
			}
		}

		/* custom commands */
		$static_output .= gettext("Custom commands...") . "\n";
		update_output_window($static_output);
		if ($missing_include == false) {
			if($pkg_config['custom_php_global_functions'] <> "") {
				$static_output .= gettext("Executing custom_php_global_functions()...");
				update_output_window($static_output);
				eval_once($pkg_config['custom_php_global_functions']);
				$static_output .= gettext("done.") . "\n";
				update_output_window($static_output);
			}
			if($pkg_config['custom_php_install_command']) {
				$static_output .= gettext("Executing custom_php_install_command()...");
				update_output_window($static_output);
				eval_once($pkg_config['custom_php_install_command']);
				$static_output .= gettext("done.") . "\n";
				update_output_window($static_output);
			}
			if($pkg_config['custom_php_resync_config_command'] <> "") {
				$static_output .= gettext("Executing custom_php_resync_config_command()...");
				update_output_window($static_output);
				eval_once($pkg_config['custom_php_resync_config_command']);
				$static_output .= gettext("done.") . "\n";
				update_output_window($static_output);
			}
		}

		/* custom commands */
		$static_output .= "Custom commands...\n";
		update_output_window($static_output);
		if ($missing_include == false) {
			if($pkg_config['custom_php_global_functions'] <> "") {
				$static_output .= "Executing custom_php_global_functions()...";
				update_output_window($static_output);
				eval_once($pkg_config['custom_php_global_functions']);
				$static_output .= "done.\n";
				update_output_window($static_output);
			}
			if($pkg_config['custom_php_install_command']) {
				$static_output .= "Executing custom_php_install_command()...";
				update_output_window($static_output);
				eval_once($pkg_config['custom_php_install_command']);
				$static_output .= "done.\n";
				update_output_window($static_output);
			}
			if($pkg_config['custom_php_resync_config_command'] <> "") {
				$static_output .= "Executing custom_php_resync_config_command()...";
				update_output_window($static_output);
				eval_once($pkg_config['custom_php_resync_config_command']);
				$static_output .= "done.\n";
				update_output_window($static_output);
			}
		}
		/* sidebar items */
		if(is_array($pkg_config['menu'])) {
			$static_output .= "Menu items... ";
			update_output_window($static_output);
			foreach($pkg_config['menu'] as $menu) {
				if(is_array($config['installedpackages']['menu'])) {
					foreach($config['installedpackages']['menu'] as $amenu)
						if($amenu['name'] == $menu['name'])
							continue 2;
				} else
					$config['installedpackages']['menu'] = array();
				$config['installedpackages']['menu'][] = $menu;
			}
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* integrated tab items */
		if(is_array($pkg_config['tabs']['tab'])) {
			$static_output .= "Integrated Tab items... ";
			update_output_window($static_output);
			foreach($pkg_config['tabs']['tab'] as $tab) {
				if(is_array($config['installedpackages']['tab'])) {
					foreach($config['installedpackages']['tab'] as $atab)
						if($atab['name'] == $tab['name'])
							continue 2;
				} else
					$config['installedpackages']['tab'] = array();
				$config['installedpackages']['tab'][] = $tab;
			}
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* services */
		if(is_array($pkg_config['service'])) {
			$static_output .= "Services... ";
			update_output_window($static_output);
			foreach($pkg_config['service'] as $service) {
				if(is_array($config['installedpackages']['service'])) {
					foreach($config['installedpackages']['service'] as $aservice)
						if($aservice['name'] == $service['name'])
							continue 2;
				} else
					$config['installedpackages']['service'] = array();
				$config['installedpackages']['service'][] = $service;
			}
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
	} else {
		$static_output .= "Loading package configuration... failed!\n\nInstallation aborted.";
		update_output_window($static_output);
		pkg_debug("Unable to load package configuration. Installation aborted.\n");
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
		add_text_to_file("/etc/syslog.conf", $pkg_info['logging']['facilityname'] . "\t\t\t\t" . $pkg_info['logging']['logfilename']);
		pkg_debug("Adding text to file /etc/syslog.conf\n");
		system_syslogd_start();
	}

	return true;
}

function does_package_depend($pkg) {
	// Should not happen, but just in case.
	if(!$pkg)
		return;
	$pkg_var_db_dir = glob("/var/db/pkg/{$pkg}*");
	// If this package has dependency then return true
	foreach($pkg_var_db_dir as $pvdd) {
		if (file_exists("{$vardb}/{$pvdd}/+REQUIRED_BY") && count(file("{$vardb}/{$pvdd}/+REQUIRED_BY")) > 0) 
			return true;
	}	
	// Did not find a record of dependencies, so return false.
	return false;
}

function delete_package($pkg) {
	global $config, $g, $static_output, $vardb;

	if(!$pkg) 
		return;

	$pkg = substr(reverse_strrchr($pkg, "."), 0, -1);

	// If package has dependencies then skip it
	if(does_package_depend($pkg)) {
		$static_output .= "Skipping package deletion for {$pkg} because it is a dependency.\n";
		update_output_window($static_output);
		return;		
	} else {
		if($pkg)
			$static_output .= "Starting package deletion for {$pkg}...";
		update_output_window($static_output);		
	}

	$info = "";
	exec("/usr/sbin/pkg_info -qrx {$pkg}", $info);
	remove_freebsd_package($pkg);
	$static_output .= "done.\n";
	update_output_window($static_output);
	foreach($info as $line) {
		$depend = trim(str_replace("@pkgdep ", "", $line), " \n");
		// If package has dependencies then skip it
		if(!does_package_depend($depend)) 			
			delete_package($depend);
	}

	/* Rescan directories for what has been left and avoid fooling other programs. */
	mwexec("/sbin/ldconfig");

	return;
}

function delete_package_xml($pkg) {
	global $g, $config, $static_output, $pkg_interface, $rcfileprefix;

	conf_mount_rw();

	$pkgid = get_pkg_id($pkg);
	if ($pkgid == -1) {
		$static_output .= "The {$pkg} package is not installed.\n\nDeletion aborted.";
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
	pkg_debug("Removing {$pkg} package... ");
	$static_output .= "Removing {$pkg} components...\n";
	update_output_window($static_output);
	/* parse package configuration */
	$packages = &$config['installedpackages']['package'];
	$tabs =& $config['installedpackages']['tab'];
	$menus =& $config['installedpackages']['menu'];
	$services = &$config['installedpackages']['service'];
	$pkg_info =& $packages[$pkgid];
	if(file_exists("/usr/local/pkg/" . $pkg_info['configurationfile'])) {
		$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $packages[$pkgid]['configurationfile'], "packagegui");
		/* remove tab items */
		if(is_array($pkg_config['tabs'])) {
			$static_output .= "Tabs items... ";
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
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* remove menu items */
		if(is_array($pkg_config['menu'])) {
			$static_output .= "Menu items... ";
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
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* remove services */
		if(is_array($pkg_config['service'])) {
			$static_output .= "Services... ";
			update_output_window($static_output);
			if (is_array($pkg_config['service']) && is_array($services)) {
				foreach($pkg_config['service'] as $service) {
					foreach($services as $key => $instservice) {
						if($instservice['name'] == $service['name']) {
							if($g['booting'] != true)
								stop_service($service['name']);
							if($service['rcfile']) {
								$prefix = $rcfileprefix;
								if (!empty($service['prefix']))
									$prefix = $service['prefix'];
								if (file_exists("{$prefix}{$service['rcfile']}"))
									@unlink("{$prefix}{$service['rcfile']}");
							}
							unset($services[$key]);
						}
					}
				}
			}
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/*
		 * XXX: Otherwise inclusion of config.inc again invalidates actions taken.
		 * 	Same is done during installation.
		 */
		write_config("Intermediate config write during package removal for {$pkg}.");
		conf_mount_rw(); //Compensate for write_config() sending to _ro

		/*
		 * If a require exists, include it.  this will
		 * show us where an error exists in a package
		 * instead of making us blindly guess
		 */
		$missing_include = false;
		if($pkg_config['include_file'] <> "") {
			$static_output .= "Loading package instructions...\n";
			update_output_window($static_output);
			pkg_debug("require_once(\"{$pkg_config['include_file']}\")\n");
			if (file_exists($pkg_config['include_file']))
				require_once($pkg_config['include_file']);
			else {
				$missing_include = true;
				update_output_window($static_output);
				$static_output .= "Include file " . basename($pkg_config['include_file']) . " could not be found for inclusion.\n";
			}
		}
		/* ermal
		 * NOTE: It is not possible to handle parse errors on eval.
		 * So we prevent it from being run at all to not interrupt all the other code.
		 */
		if ($missing_include == false) {
			/* evalate this package's global functions and pre deinstall commands */
			if($pkg_config['custom_php_global_functions'] <> "")
				eval_once($pkg_config['custom_php_global_functions']);
			if($pkg_config['custom_php_pre_deinstall_command'] <> "")
				eval_once($pkg_config['custom_php_pre_deinstall_command']);
		}
		/* system files */
		if(is_array($pkg_config['modify_system']) && is_array($pkg_config['modify_system']['item'])) {
			$static_output .= "System files... ";
			update_output_window($static_output);
			foreach($pkg_config['modify_system']['item'] as $ms)
				if($ms['textneeded']) remove_text_from_file($ms['modifyfilename'], $ms['textneeded']);

			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* deinstall commands */
		if($pkg_config['custom_php_deinstall_command'] <> "") {
			$static_output .= "Deinstall commands... ";
			update_output_window($static_output);
			if ($missing_include == false) {
				eval_once($pkg_config['custom_php_deinstall_command']);
				$static_output .= "done.\n";
			} else
				$static_output .= "\nNot executing custom deinstall hook because an include is missing.\n";
			update_output_window($static_output);
		}
		if($pkg_config['include_file'] <> "") {
			$static_output .= "Removing package instructions...";
			update_output_window($static_output);
			pkg_debug("Remove '{$pkg_config['include_file']}'\n");
			unlink_if_exists("/usr/local/pkg/" . $pkg_config['include_file']);
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* remove all additional files */
		if(is_array($pkg_config['additional_files_needed'])) {
			$static_output .= "Auxiliary files... ";
			update_output_window($static_output);
			foreach($pkg_config['additional_files_needed'] as $afn) {
				$filename = get_filename_from_url($afn['item'][0]);
				if($afn['prefix'] <> "")
					$prefix = $afn['prefix'];
				else
					$prefix = "/usr/local/pkg/";
				unlink_if_exists($prefix . $filename);
			}
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* package XML file */
		$static_output .= "Package XML... ";
		update_output_window($static_output);
		unlink_if_exists("/usr/local/pkg/" . $packages[$pkgid]['configurationfile']);
		$static_output .= "done.\n";
		update_output_window($static_output);
	}
	/* syslog */
	if(is_array($pkg_info['logging']) && $pkg_info['logging']['logfile_name'] <> "") {
		$static_output .= "Syslog entries... ";
		update_output_window($static_output);
		remove_text_from_file("/etc/syslog.conf", $pkg_info['logging']['facilityname'] . "\t\t\t\t" . $pkg_info['logging']['logfilename']);
		system_syslogd_start();
		@unlink("{$g['varlog_path']}/{$pkg_info['logging']['logfilename']}");
		$static_output .= "done.\n";
		update_output_window($static_output);
	}
	
	conf_mount_ro();
	/* remove config.xml entries */
	$static_output .= "Configuration... ";
	update_output_window($static_output);
	unset($config['installedpackages']['package'][$pkgid]);
	$static_output .= "done.\n";
	update_output_window($static_output);
	write_config("Removed {$pkg} package.\n");
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
		if(!$pkg_info[$apkg])
			continue;
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

function pkg_reinstall_all() {
	global $g, $config;

	@unlink('/conf/needs_package_sync');
	$pkg_id = 0;
	$todo = array();
	if (is_array($config['installedpackages']['package'])) {
		foreach($config['installedpackages']['package'] as $package)
			$todo[] = array('name' => $package['name'], 'version' => $package['version']);

		echo "One moment please, reinstalling packages...\n";
		echo " >>> Trying to fetch package info...";
		$pkg_info = get_pkg_info();
		if ($pkg_info) {
			echo " Done.\n";
		} else {
			$xmlrpc_base_url = isset($config['system']['altpkgrepo']['enable']) ? $config['system']['altpkgrepo']['xmlrpcbaseurl'] : $g['xmlrpcbaseurl'];
			echo "\n" . sprintf(gettext(' >>> Unable to communicate with %1$s. Please verify DNS and interface configuration, and that %2$s has functional Internet connectivity.'), $xmlrpc_base_url, $g['product_name']) . "\n";
			return;
		}
		foreach($todo as $pkgtodo) {
			$static_output = "";
			if($pkgtodo['name']) {
				uninstall_package($pkgtodo['name']);
				install_package($pkgtodo['name']);
				$pkg_id++;
			}
		}
	}
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

	global $config, $g, $rcfileprefix;

	log_error("Stopping all packages.");

	$rcfiles = glob("{$rcfileprefix}*.sh");
	if (!$rcfiles)
		$rcfiles = array();
	else {
		$rcfiles = array_flip($rcfiles);
		if (!$rcfiles)
			$rcfiles = array();
	}

	if (is_array($config['installedpackages']['package'])) {
		foreach($config['installedpackages']['package'] as $package) {
			echo " Stopping package {$package['name']}...";
			stop_service($package['name']);
			unset($rcfiles["{$rcfileprefix}{$package['name']}.sh"]);
			echo "done.\n";
		}
	}

	$shell = @popen("/bin/sh", "w");
	if ($shell) {
		foreach ($rcfiles as $rcfile => $number) {
			echo " Stopping {$rcfile}...";
			fwrite($shell, "{$rcfile} stop >>/tmp/bootup_messages 2>&1");
			echo "done.\n";
		}

		pclose($shell);
	}
}

?>
