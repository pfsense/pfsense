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
 * Copyright (C) 2010 Ermal Luci
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

if(!is_dir("/usr/local/pkg") or !is_dir("/usr/local/pkg/pf")) {
	conf_mount_rw();
	safe_mkdir("/usr/local/pkg");
	safe_mkdir("/usr/local/pkg/pf");	
	conf_mount_ro();
}

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
	// The packagestring passed in must be the full PBI package name, 
	// as displayed by the pbi_info utility. e.g. "package-1.2.3_4-i386" 
	// It must NOT have ".pbi" on the end.
	exec("/usr/local/sbin/pbi_info " . escapeshellarg($packagestring) . " | /usr/bin/awk '/Prefix/ {print $2}'",$pbidir);
	$pbidir = $pbidir[0];
	if ($pbidir == "") {
		log_error("PBI dir for {$packagestring} was not found - cannot cleanup PBI files");
	}
	else {
		$linkdirs = array('bin','sbin');
		foreach($linkdirs as $dir) {
			$target_dir = $pbidir . "/" . $dir;
			if(is_dir($target_dir)) {
				$files = scandir($target_dir);
				foreach($files as $f) {
					if($f != '.' && $f != '..') {
						// Only try to unlink the file if it is a link to the expected pbi dir.
						$local_name = "/usr/local/{$dir}/{$f}";
						if(is_link($local_name)) {
							if(substr(readlink($local_name),0,strlen($target_dir)) == $target_dir) {
								unlink($local_name);
							}
						}
					}
				}
			}
		}

		exec("/usr/local/sbin/pbi_delete " . escapeshellarg($packagestring) . " 2>>/tmp/pbi_delete_errors.txt");
	}
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

/****f* pkg-utils/get_pkg_internal_name
 * NAME
 *   get_pkg_internal_name - Find a package's internal name (e.g. squid3 internal name is squid)
 * INPUTS
 *   $package - array of package data from config
 * RESULT
 *   string - internal name (if defined) or default to package name
 ******/
function get_pkg_internal_name($package) {
	if (isset($package['internal_name']) && ($package['internal_name'] != "")) {
		/* e.g. name is Ipguard-dev, internal name is ipguard */
		$pkg_internal_name = $package['internal_name'];
	} else {
		$pkg_internal_name = $package['name'];
	}
	return $pkg_internal_name;
}

/****f* pkg-utils/get_pkg_info
 * NAME
 *   get_pkg_info - Retrieve package information from package server.
 * INPUTS
 *   $pkgs - 'all' to retrieve all packages, an array containing package names otherwise
 *   $info - 'all' to retrieve all information, an array containing keys otherwise
 * RESULT
 *   $raw_versions - Array containing retrieved information, indexed by package name.
 ******/
function get_pkg_info($pkgs = 'all', $info = 'all') {
	global $g;

	$freebsd_machine = php_uname("m");
	$params = array(
		"pkg" => $pkgs, 
		"info" => $info, 
		"freebsd_version" => get_freebsd_version(),
		"freebsd_machine" => $freebsd_machine
	);
	$resp = call_pfsense_method('pfsense.get_pkgs', $params, 10);
	return $resp ? $resp : array();
}

function get_pkg_sizes($pkgs = 'all') {
	global $config, $g;

	$freebsd_machine = php_uname("m");
	$params = array(
		"pkg" => $pkgs, 
		"freebsd_version" => get_freebsd_version(),
		"freebsd_machine" => $freebsd_machine
	);
	$msg = new XML_RPC_Message('pfsense.get_pkg_sizes', array(php_value_to_xmlrpc($params)));
	$xmlrpc_base_url = isset($config['system']['altpkgrepo']['enable']) ? $config['system']['altpkgrepo']['xmlrpcbaseurl'] : $g['xmlrpcbaseurl'];
	$cli = new XML_RPC_Client($g['xmlrpcpath'], $xmlrpc_base_url);
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

	log_error(gettext("Resyncing configuration for all packages."));

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
			stop_service(get_pkg_internal_name($package));
		sync_package($idx, true, true);
		if($pkg_interface == "console") 
			echo "\n" . gettext("Syncing packages:");
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
	exec("/usr/local/sbin/pbi_info " . escapeshellarg($pkg), $output, $retval);

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
		log_error(sprintf(gettext('The %1$s package is missing required dependencies and must be reinstalled. %2$s'), $package['name'], $package['configurationfile']));
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
	global $config, $static_output;
	global $builder_package_install;

	$id = get_pkg_id($pkg_name);
	if ($id >= 0) {
		stop_service(get_pkg_internal_name($config['installedpackages']['package'][$id]));
		$pkg_depends =& $config['installedpackages']['package'][$id]['depends_on_package_pbi'];
		$static_output .= "Removing package...\n";
		update_output_window($static_output);
		if (is_array($pkg_depends)) {
			foreach ($pkg_depends as $pkg_depend)
				delete_package($pkg_depend);
		} else {
			// The packages (1 or more) are all in one long string.
			// We need to pass them 1 at a time to delete_package.
			// Compress any multiple whitespace (sp, tab, cr, lf...) into a single space char.
			$pkg_dep_str = preg_replace("'\s+'", ' ', $pkg_depends);
			// Get rid of any leading or trailing space.
			$pkg_dep_str = trim($pkg_dep_str);
			// Now we have a space-separated string. Make it into an array and process it.
			$pkg_dep_array = explode(" ", $pkg_dep_str);
			foreach ($pkg_dep_array as $pkg_depend) {
				delete_package($pkg_depend);
			}
		}
	}
	delete_package_xml($pkg_name);

	$static_output .= gettext("done.") . "\n";
	update_output_window($static_output);
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
		log_error(sprintf(gettext("The %s package is missing its configuration file and must be reinstalled."), $package['name']));
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
					file_notice($package['name'], sprintf(gettext("The %s package is missing required dependencies and must be reinstalled."), $package['name']), "Packages", "/pkg_mgr_install.php?mode=reinstallpkg&pkg={$package['name']}", 1);
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
 * pkg_fetch_recursive: Download and install a FreeBSD PBI package. This function provides output to
 * 			a progress bar and output window.
 */
function pkg_fetch_recursive($pkgname, $filename, $dependlevel = 0, $base_url = "") {
	global $static_output, $g, $config;

	// Clean up incoming filenames
	$filename = str_replace("  ", " ", $filename);
	$filename = str_replace("\n", " ", $filename);
	$filename = str_replace("  ", " ", $filename);

	$pkgs = explode(" ", $filename);
	foreach($pkgs as $filename) {
		$filename = trim($filename);
		if ($g['platform'] == "nanobsd") {
			$pkgtmpdir = "/usr/bin/env PKG_TMPDIR=/root/ ";
			$pkgstagingdir = "/root/tmp";
			if (!is_dir($pkgstagingdir))
				mkdir($pkgstagingdir);
			$pkgstaging = "-o {$pkgstagingdir}/instmp.XXXXXX";
			$fetchdir = $pkgstagingdir;
		} else {
			$fetchdir = $g['tmp_path'];
		}

		/* FreeBSD has no PBI's hosted, so fall back to our own URL for now. (Maybe fail to PC-BSD?) */
		$rel = get_freebsd_version();
		$priv_url = "http://files.pfsense.org/packages/{$rel}/All/";
		if (empty($base_url))
			$base_url = $priv_url;
		if (substr($base_url, -1) == "/")
			$base_url = substr($base_url, 0, -1);
		$fetchto = "{$fetchdir}/apkg_{$filename}";
		$static_output .= "\n" . str_repeat(" ", $dependlevel * 2 + 1) . "Downloading {$base_url}/{$filename} ... ";
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

		$pkgaddout = "";

		$no_checksig = "";
		if (isset($config['system']['pkg_nochecksig']))
			$no_checksig = "--no-checksig";

		$result = exec("/usr/local/sbin/pbi_add " . $pkgstaging . " -f -v {$no_checksig} " . escapeshellarg($fetchto) . " 2>&1", $pkgaddout, $rc);
		pkg_debug($pkgname . " " . print_r($pkgaddout, true) . "\n");
		if ($rc == 0) {
			setup_library_paths();
			$result = exec("/usr/local/sbin/pbi_info " . escapeshellarg(preg_replace('/\.pbi$/','',$filename)) . " | /usr/bin/awk '/Prefix/ {print $2}'",$pbidir);
			$pbidir = $pbidir[0];
			foreach(array('bin', 'sbin') as $dir) {
				if(!is_dir("{$pbidir}/{$dir}"))
					continue;

				$files = scandir("{$pbidir}/{$dir}");
				foreach($files as $f)
					if(!file_exists("/usr/local/{$dir}/{$f}"))
						@symlink("{$pbidir}/{$dir}/{$f}","/usr/local/{$dir}/{$f}");
			}
			pkg_debug("pbi_add successfully completed.\n");
		} else {
			if (is_array($pkgaddout))
				foreach ($pkgaddout as $line)
					$static_output .= " " . $line .= "\n";

			update_output_window($static_output);
			pkg_debug("pbi_add failed.\n");
			return false;
		}
	}
	return true;
}

function install_package($package, $pkg_info = "", $force_install = false) {
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
	if (!$force_install) {
		$compatible = true;
		$version = rtrim(file_get_contents("/etc/version"));
		
		if (isset($pkg_info['required_version']))
			$compatible = (pfs_version_compare("", $version, $pkg_info['required_version']) >= 0);
		if (isset($pkg_info['maximum_version']))
			$compatible = $compatible && (pfs_version_compare("", $version, $pkg_info['maximum_version']) <= 0);
		
		if (!$compatible) {
			log_error(sprintf(gettext('Package %s is not supported on this version.'), $pkg_info['name']));
			$static_output .= sprintf(gettext("Package %s is not supported on this version."), $pkg_info['name']);
			update_status($static_output);
			
			conf_mount_ro();
			return -1;
		}
	}
	pkg_debug(gettext("Beginning package installation.") . "\n");
	log_error(sprintf(gettext('Beginning package installation for %s .'), $pkg_info['name']));
	$static_output .= sprintf(gettext("Beginning package installation for %s ."), $pkg_info['name']);
	update_status($static_output);
	/* fetch the package's configuration file */
	if($pkg_info['config_file'] != "") {
		$static_output .= "\n" . gettext("Downloading package configuration file... ");
		update_output_window($static_output);
		pkg_debug(gettext("Downloading package configuration file...") . "\n");
		$fetchto = substr(strrchr($pkg_info['config_file'], '/'), 1);
		download_file_with_progress_bar($pkg_info['config_file'], '/usr/local/pkg/' . $fetchto);
		if(!file_exists('/usr/local/pkg/' . $fetchto)) {
			pkg_debug(gettext("ERROR! Unable to fetch package configuration file. Aborting installation.") . "\n");
			if($pkg_interface == "console")
				print "\n" . gettext("ERROR! Unable to fetch package configuration file. Aborting package installation.") . "\n";
			else {
				$static_output .= gettext("failed!\n\nInstallation aborted.\n");
				update_output_window($static_output);
				echo "<br />Show <a href=\"pkg_mgr_install.php?showlog=true\">install log</a></center>";
			}
			conf_mount_ro();
			return -1;
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
		$changedesc = sprintf(gettext("Installed %s package."),$pkg_info['name']);
		$to_output = gettext("done.") . "\n";
	} else {
		$config['installedpackages']['package'][$pkgid] = $pkg_info;
		$changedesc = sprintf(gettext("Overwrote previous installation of %s."), $pkg_info['name']);
		$to_output = gettext("overwrite!") . "\n";
	}
	if(file_exists('/conf/needs_package_sync'))
		@unlink('/conf/needs_package_sync');
	conf_mount_ro();
	write_config("Intermediate config write during package install for {$pkg_info['name']}.");
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
		if($pkg_info['after_install_info']) 
			update_output_window($pkg_info['after_install_info']);	
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
	global $g, $config, $static_output, $pkg_interface, $config_parsed;

	if(($pkgid = get_pkg_id($pkg)) == -1) {
		$static_output .= sprintf(gettext("The %s package is not installed.%sInstallation aborted."), $pkg, "\n\n");
		update_output_window($static_output);
		if($pkg_interface <> "console") {
			echo "\n<script type=\"text/javascript\">document.progressbar.style.visibility='hidden';</script>";
			echo "\n<script type=\"text/javascript\">document.progholder.style.visibility='hidden';</script>";
		}
		sleep(1);
		return false;
	} else
		$pkg_info = $config['installedpackages']['package'][$pkgid];

	/* pkg_add the package and its dependencies */
	if($pkg_info['depends_on_package_base_url'] != "") {
		if($pkg_interface == "console") 
			echo "\n";
		update_status(gettext("Installing") . " " . $pkg_info['name'] . " " . gettext("and its dependencies."));
		$static_output .= gettext("Downloading") . " " . $pkg_info['name'] . " " . gettext("and its dependencies... ");
		$static_orig = $static_output;
		$static_output .= "\n";
		update_output_window($static_output);
		foreach((array) $pkg_info['depends_on_package_pbi'] as $pkgdep) {
			$pkg_name = substr(reverse_strrchr($pkgdep, "."), 0, -1);
			$static_output = $static_orig . "\nChecking for package installation... ";
			update_output_window($static_output);
			if (!is_freebsd_pkg_installed($pkg_name)) {
				if (!pkg_fetch_recursive($pkg_name, $pkgdep, 0, $pkg_info['depends_on_package_base_url'])) {
					$static_output .= "of {$pkg_name} failed!\n\nInstallation aborted.";
					update_output_window($static_output);
					pkg_debug(gettext("Package WAS NOT installed properly.") . "\n");
					if($pkg_interface <> "console") {
						echo "\n<script type=\"text/javascript\">document.progressbar.style.visibility='hidden';</script>";
						echo "\n<script type=\"text/javascript\">document.progholder.style.visibility='hidden';</script>";
					}
					sleep(1);
					return false;
				}
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
		$static_output .= gettext("Configuring package components...\n");
		if (!empty($pkg_config['filter_rules_needed']))
			$config['installedpackages']['package'][$pkgid]['filter_rule_function'] = $pkg_config['filter_rules_needed'];
		update_output_window($static_output);
		/* modify system files */
		if(is_array($pkg_config['modify_system']) && is_array($pkg_config['modify_system']['item'])) {
			$static_output .= gettext("System files... ");
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
			$static_output .= gettext("Additional files... ");
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
					pkg_debug(gettext("Extracting tarball to -C for ") . $filename . "...\n");
					$tarout = "";
					exec("/usr/bin/tar xvzf " . escapeshellarg($prefix . $filename) . " -C / 2>&1", $tarout);
					pkg_debug(print_r($tarout, true) . "\n");
				}
				if($pkg_chmod <> "") {
					pkg_debug(sprintf(gettext('Changing file mode to %1$s for %2$s%3$s%4$s'), $pkg_chmod, $prefix, $filename, "\n"));
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
		$missing_include = false;
		if($pkg_config['include_file'] <> "") {
			$static_output .= gettext("Loading package instructions...") . "\n";
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
				/* XXX: create symlinks for conf files into the PBI directories.
				 *	change packages to store configs at /usr/pbi/pkg/etc and remove this
				 */
				eval_once($pkg_config['custom_php_install_command']);
				// Note: pkg may be mixed-case, e.g. "squidGuard" but the PBI names are lowercase.
				// e.g. "squidguard-1.4_4-i386" so feed lowercase to pbi_info below.
				// Also add the "-" so that examples like "squid-" do not match "squidguard-".
				$pkg_name_for_pbi_match = strtolower($pkg) . "-";
				exec("/usr/local/sbin/pbi_info | grep '^{$pkg_name_for_pbi_match}' | xargs /usr/local/sbin/pbi_info | awk '/Prefix/ {print $2}'",$pbidirarray);
				$pbidir0 = $pbidirarray[0];
				exec("find /usr/local/etc/ -name *.conf | grep " . escapeshellarg($pkg),$files);
				foreach($files as $f) {
					$pbiconf = str_replace('/usr/local',$pbidir0,$f);
					if(is_file($pbiconf) || is_link($pbiconf)) {
						unlink($pbiconf);
					}
					if(is_dir(dirname($pbiconf))) {
						symlink($f,$pbiconf);
					} else {
						log_error("The dir for {$pbiconf} does not exist. Cannot add symlink to {$f}.");
					}
				}
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
		/* sidebar items */
		if(is_array($pkg_config['menu'])) {
			$static_output .= gettext("Menu items... ");
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
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* integrated tab items */
		if(is_array($pkg_config['tabs']['tab'])) {
			$static_output .= gettext("Integrated Tab items... ");
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
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* services */
		if(is_array($pkg_config['service'])) {
			$static_output .= gettext("Services... ");
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
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
	} else {
		$static_output .= gettext("Loading package configuration... failed!") . "\n\n" . gettext("Installation aborted.");
		update_output_window($static_output);
		pkg_debug(gettext("Unable to load package configuration. Installation aborted.") ."\n");
		if($pkg_interface <> "console") {
			echo "\n<script type=\"text/javascript\">document.progressbar.style.visibility='hidden';</script>";
			echo "\n<script type=\"text/javascript\">document.progholder.style.visibility='hidden';</script>";
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

	// Note: $pkg has the full PBI package name followed by ".pbi". Strip off ".pbi".
	$pkg = substr(reverse_strrchr($pkg, "."), 0, -1);

	if($pkg)
		$static_output .= sprintf(gettext("Starting package deletion for %s..."),$pkg);
	update_output_window($static_output);

	remove_freebsd_package($pkg);
	$static_output .= "done.\n";
	update_output_window($static_output);

	/* Rescan directories for what has been left and avoid fooling other programs. */
	mwexec("/sbin/ldconfig");

	return;
}

function delete_package_xml($pkg) {
	global $g, $config, $static_output, $pkg_interface;

	conf_mount_rw();

	$pkgid = get_pkg_id($pkg);
	if ($pkgid == -1) {
		$static_output .= sprintf(gettext("The %s package is not installed.%sDeletion aborted."), $pkg, "\n\n");
		update_output_window($static_output);
		if($pkg_interface <> "console") {
			echo "\n<script type=\"text/javascript\">document.progressbar.style.visibility='hidden';</script>";
			echo "\n<script type=\"text/javascript\">document.progholder.style.visibility='hidden';</script>";
		}
		ob_flush();
		sleep(1);
		conf_mount_ro();
		return;
	}
	pkg_debug(sprintf(gettext("Removing %s package... "),$pkg));
	$static_output .= sprintf(gettext("Removing %s components..."),$pkg) . "\n";
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
			$static_output .= gettext("Tabs items... ");
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
			$static_output .= gettext("Menu items... ");
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
			$static_output .= gettext("Services... ");
			update_output_window($static_output);
			if (is_array($pkg_config['service']) && is_array($services)) {
				foreach($pkg_config['service'] as $service) {
					foreach($services as $key => $instservice) {
						if($instservice['name'] == $service['name']) {
							if($g['booting'] != true)
								stop_service($service['name']);
							if($service['rcfile']) {
								$prefix = RCFILEPREFIX;
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
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/*
		 * XXX: Otherwise inclusion of config.inc again invalidates actions taken.
		 * 	Same is done during installation.
		 */
		write_config("Intermediate config write during package removal for {$pkg}.");

		/*
		 * If a require exists, include it.  this will
		 * show us where an error exists in a package
		 * instead of making us blindly guess
		 */
		$missing_include = false;
		if($pkg_config['include_file'] <> "") {
			$static_output .= gettext("Loading package instructions...") . "\n";
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
			$static_output .= gettext("System files... ");
			update_output_window($static_output);
			foreach($pkg_config['modify_system']['item'] as $ms)
				if($ms['textneeded']) remove_text_from_file($ms['modifyfilename'], $ms['textneeded']);

			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* deinstall commands */
		if($pkg_config['custom_php_deinstall_command'] <> "") {
			$static_output .= gettext("Deinstall commands... ");
			update_output_window($static_output);
			if ($missing_include == false) {
				eval_once($pkg_config['custom_php_deinstall_command']);
				$static_output .= gettext("done.") . "\n";
			} else
				$static_output .= "\nNot executing custom deinstall hook because an include is missing.\n";
			update_output_window($static_output);
		}
		if($pkg_config['include_file'] <> "") {
			$static_output .= gettext("Removing package instructions...");
			update_output_window($static_output);
			pkg_debug(sprintf(gettext("Remove '%s'"), $pkg_config['include_file']) . "\n");
			unlink_if_exists("/usr/local/pkg/" . $pkg_config['include_file']);
			$static_output .= gettext("done.") . "\n";
			update_output_window($static_output);
		}
		/* remove all additional files */
		if(is_array($pkg_config['additional_files_needed'])) {
			$static_output .= gettext("Auxiliary files... ");
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
		$static_output .= gettext("Package XML... ");
		update_output_window($static_output);
		unlink_if_exists("/usr/local/pkg/" . $packages[$pkgid]['configurationfile']);
		$static_output .= gettext("done.") . "\n";
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
	$static_output .= gettext("Configuration... ");
	update_output_window($static_output);
	unset($config['installedpackages']['package'][$pkgid]);
	$static_output .= gettext("done.") . "\n";
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
	if (is_array($config['installedpackages']['package'])) {
		echo gettext("One moment please, reinstalling packages...\n");
		echo gettext(" >>> Trying to fetch package info...");
		log_error(gettext("Attempting to reinstall all packages"));
		$pkg_info = get_pkg_info();
		if ($pkg_info) {
			echo " Done.\n";
		} else {
			$xmlrpc_base_url = isset($config['system']['altpkgrepo']['enable']) ? $config['system']['altpkgrepo']['xmlrpcbaseurl'] : $g['xmlrpcbaseurl'];
			$error = sprintf(gettext(' >>> Unable to communicate with %1$s. Please verify DNS and interface configuration, and that %2$s has functional Internet connectivity.'), $xmlrpc_base_url, $g['product_name']);
			echo "\n{$error}\n";
			log_error(gettext("Cannot reinstall packages: ") . $error);
			return;
		}
		$todo = array();
		$all_names = array();
		foreach($config['installedpackages']['package'] as $package) {
			$todo[] = array('name' => $package['name'], 'version' => $package['version']);
			$all_names[] = $package['name'];
		}
		$package_name_list = gettext("List of packages to reinstall: ") . implode(", ", $all_names);
		echo " >>> {$package_name_list}\n";
		log_error($package_name_list);

		foreach($todo as $pkgtodo) {
			$static_output = "";
			if($pkgtodo['name']) {
				log_error(gettext("Uninstalling package") . " {$pkgtodo['name']}");
				uninstall_package($pkgtodo['name']);
				log_error(gettext("Finished uninstalling package") . " {$pkgtodo['name']}");
				log_error(gettext("Reinstalling package") . " {$pkgtodo['name']}");
				install_package($pkgtodo['name']);
				log_error(gettext("Finished installing package") . " {$pkgtodo['name']}");
			}
		}
		log_error(gettext("Finished reinstalling all packages."));
	} else
		echo "No packages are installed.";
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
			$internal_name = get_pkg_internal_name($package);
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
				if ($shell)
					pclose($shell);
				$shell = @popen("/bin/sh", "w");
			}
			echo "done.\n";
			pclose($shell);
		}
	}
}

function package_skip_tests($index,$requested_version){
	global $config, $g;

	/* Get pfsense version*/
	$version = rtrim(file_get_contents("/etc/version"));
	
	if($g['platform'] == "nanobsd")
		if($index['noembedded']) 
			return true;
						
	/* If we are on not on HEAD, and the package wants it, skip */
	if ($version <> "HEAD" && $index['required_version'] == "HEAD" && $requested_version <> "other")
		return true;
							
	/* If there is no required version, and the requested package version is not 'none', then skip */
	if (empty($index['required_version']) && $requested_version <> "none")
		return true;
							
	/* If the requested version is not 'other', and the required version is newer than what we have, skip. */
	if($requested_version <> "other" && (pfs_version_compare("", $version, $index['required_version']) < 0))
		return true;
							
	/* If the requestion version is 'other' and we are on the version requested, skip. */
	if($requested_version == "other" && (pfs_version_compare("", $version, $index['required_version']) == 0))
		return true;
							
	/* Package is only for an older version, lets skip */
	if($index['maximum_version'] && (pfs_version_compare("", $version, $index['maximum_version']) > 0))
		return true;
		
	/* Do not skip package list */	
	return false;
}

function get_pkg_interfaces_select_source($include_localhost=false) {
	$interfaces = get_configured_interface_with_descr();
	$ssifs = array();
	foreach ($interfaces as $iface => $ifacename) {
		$tmp["name"]  = $ifacename;
		$tmp["value"] = $iface;
		$ssifs[] = $tmp;
	}
	if ($include_localhost) {
		$tmp["name"]  = "Localhost";
		$tmp["value"] = "lo0";
		$ssifs[] = $tmp;
	}
	return $ssifs;
}
?>
