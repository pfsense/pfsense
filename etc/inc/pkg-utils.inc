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
 * Copyright (C) 2005 Colin Smith (ethethlay@gmail.com)
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
require_once("xmlrpc.inc");
require_once("xmlparse.inc");

/* add package specific listtags to XML parser */
$listtags = array_merge($listtags, array("onetoone", "queue", "rule", "servernat", "alias", "additional_files_needed", "tab", "template", "menu", "rowhelperfield", "service", "step", "package", "columnitem", "option", "item", "field", "package"));

require_once("pfsense-utils.inc");
require_once("globals.inc");

/*
safe_mkdir("/var/db/pkg");
safe_mkdir("/usr/local/pkg");
safe_mkdir("/usr/local/pkg/pf");
*/

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
	if($pkg == -1) return false;
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
            if($pkg['name'] == $pkg_name) return $i;
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
        $params = array("pkg" => $pkgs, "info" => $info);
        $msg = new XML_RPC_Message('pfsense.get_pkgs', array(php_value_to_xmlrpc($params)));
        $cli = new XML_RPC_Client($g['xmlrpcpath'], $g['xmlrpcbaseurl']);
        $resp = $cli->send($msg, 10);
	if($resp and !$resp->faultCode()) {
        	$raw_versions = $resp->value();
		return xmlrpc_value_to_php($raw_versions);
	} else {
		return array();
	}
}

function get_pkg_sizes($pkgs = 'all') {
	global $g;
        $params = array("pkg" => $pkgs);
        $msg = new XML_RPC_Message('pfsense.get_pkg_sizes', array(php_value_to_xmlrpc($params)));
        $cli = new XML_RPC_Client($g['xmlrpcpath'], $g['xmlrpcbaseurl']);
        $resp = $cli->send($msg, 10);  
        if($resp and !$resp->faultCode()) {
                $raw_versions = $resp->value();
                return xmlrpc_value_to_php($raw_versions);
        } else {
                return array();
        } 
}

/*
 * resync_all_package_configs() Force packages to setup their configuration and rc.d files.
 * This function may also print output to the terminal indicating progress.
 */
function resync_all_package_configs($show_message = false) {
    global $config;
    $i = 0;
    log_error("Resyncing configuration for all packages.");
    if(!$config['installedpackages']['package']) return;
    if($show_message == true) print "Syncing packages:";
    foreach($config['installedpackages']['package'] as $package) {
        if($show_message == true) print " " . $package['name'];
        sync_package($i, true, true);
        $i++;
    }
    if($show_message == true) print ".\n";
}

/*
 * is_freebsd_pkg_installed() - Check /var/db/pkg to determine whether or not a FreeBSD
 *				package is installed.
 */
function is_freebsd_pkg_installed($pkg) {
	global $g;
	if(in_array($pkg, return_dir_as_array("{$g['vardb_path']}/pkg"))) return true;
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
    if(!is_numeric($pkg_name)) {
        $pkg_id = get_pkg_id($pkg_name);
        if($pkg_id == -1) return -1; // This package doesn't really exist - exit the function.
    } else {                                                                                  
        if(!isset($config['installedpackages']['package'][$pkg_id])) return; // No package belongs to the pkg_id passed to this function.
    }                                                                                                                                    
    $package = $config['installedpackages']['package'][$pkg_id];
    if(!file_exists("/usr/local/pkg/" . $package['configurationfile'])) {
        file_notice($package['name'], "The {$package['name']} package is missing its configuration file and must be reinstalled.", "Packages", "/pkg_mgr_install.php?mode=reinstallpkg&pkg={$package['name']}", 1);
		return;
	}
	$pkg_xml = parse_xml_config("/usr/local/pkg/" . $package['configurationfile'], "packagegui");
    if($pkg_xml['additional_files_needed'] != "") {                                                  
        foreach($pkg_xml['additional_files_needed'] as $item) {
            if (($return_nosync == 0) && (isset($item['nosync']))) continue; // Do not return depends with nosync set if not required.
            $depend_file = substr(strrchr($item['item']['0'], '/'),1); // Strip URLs down to filenames.
            $depend_name = substr(substr($depend_file,0,strpos($depend_file,".")+1),0,-1); // Strip filename down to dependency name.
            if (($filetype != "all") && (!preg_match("/${filetype}/i", $depend_file))) continue;
            if ($item['prefix'] != "") {
                $prefix = $item['prefix'];
            } else {
                $prefix = "/usr/local/pkg/";
            }
            if(!file_exists($prefix . $pkg_name)) {
				file_notice($package['name'], "The {$package['name']} package is missing required dependencies and must be reinstalled.", "Packages", "/pkg_mgr_install.php?mode=reinstallpkg&pkg={$package['name']}", 1);
            }
            switch ($format) {
            case "files":
                $depends[] = $depend_file;
                break;
            case "names":
                switch ($filetype) {
                case "all":
                    if(preg_match("/\.xml/i", $depend_file)) {
                        $depend_xml = parse_xml_config("/usr/local/pkg/" . $depend_file, "packagegui");
                        $depends[] = $depend_xml['name'];
                        break;
                    } else {
                        $depends[] = $depend_name; // If this dependency isn't package XML, use the stripped filename.
                        break;
                    }
                case ".xml":
                    $depend_xml = parse_xml_config("/usr/local/pkg/" . $depend_file, "packagegui");
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

/*
 * sync_package($pkg_name, $sync_depends = true, $show_message = false) Force a package to setup its configuration and rc.d files.
 */
function sync_package($pkg_name, $sync_depends = true, $show_message = false) {
    global $config;

    if(!file_exists("/usr/local/pkg")) mwexec("/bin/mkdir -p /usr/local/pkg/pf");
    if(!$config['installedpackages']['package']) return;                         
    if(!is_numeric($pkg_name)) {                        
        $pkg_id = get_pkg_id($pkg_name);
        if($pkg_id == -1) return -1; // This package doesn't really exist - exit the function.
    } else {                                                                                  
        $pkg_id = $pkg_name;
        if(!isset($config['installedpackages']['package'][$pkg_id]))
            return;  // No package belongs to the pkg_id passed to this function.
    }                                                                            
    $package = $config['installedpackages']['package'][$pkg_id];
    if(!file_exists("/usr/local/pkg/" . $package['configurationfile'])) {
    	file_notice($package['name'], "The {$package['name']} package is missing its configuration file and must be reinstalled.", "Packages", "/pkg_mgr_install.php?mode=reinstallpkg&pkg={$package['name']}", 1);
    } else {
        $pkg_config = parse_xml_config("/usr/local/pkg/" . $package['configurationfile'], "packagegui");
		if(isset($pkg_config['nosync'])) continue;                                                          
		if($pkg['custom_php_global_functions'] <> "")            
	        eval($pkg['custom_php_global_functions']);
	    if($pkg_config['custom_php_command_before_form'] <> "")
	        eval($pkg_config['custom_php_command_before_form']);
	    if($pkg_config['custom_php_resync_config_command'] <> "")
	        eval($pkg_config['custom_php_resync_config_command']);
	    if($sync_depends == true) {
	        $depends = get_pkg_depends($pkg_name, ".xml", "files", 1); // Call dependency handler and do a little more error checking.
	        if(is_array($depends)) {
	            foreach($depends as $item) {
					if(!file_exists("/usr/local/pkg" . $item)) {
						file_notice($package['name'], "The {$package['name']} package is missing required dependencies and must be reinstalled.", "Packages", "/pkg_mgr_install.php?mode=reinstallpkg&pkg={$package['name']}", 1);
					} else {
		                $item_config = parse_xml_config("/usr/local/pkg/" . $item, "packagegui");
		                if(isset($item_config['nosync'])) continue;
		                if($item_config['custom_php_command_before_form'] <> "") {
		                    eval($item_config['custom_php_command_before_form']);
		                }
		                if($item_config['custom_php_resync_config_command'] <> "") {
		                    eval($item_config['custom_php_resync_config_command']);
		                }
		                if($show_message == true) print " " . $item_config['name'];
                  }
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
function pkg_fetch_recursive($pkgname, $filename, $dependlevel = 0, $base_url = 'http://ftp2.freebsd.org/pub/FreeBSD/ports/i386/packages-5.4-release/Latest') {
        global $pkgent, $static_status, $static_output, $g, $pkg_interface, $fd_log;
        $pkg_extension = strrchr($filename, '.');
        $static_output .= "\n" . str_repeat(" ", $dependlevel * 2) . $pkgname . " ";
        $fetchto = "/tmp/apkg_" . $pkgname . $pkg_extension;
	download_file_with_progress_bar($base_url . '/' . $filename, $fetchto);
//      update_output_window($static_output . "\n\n" . $pkg_progress);
        exec("/usr/bin/tar -O -f {$fetchto} -x +CONTENTS", $slaveout);
        $workingdir = preg_grep("/instmp/", $slaveout);
        $workingdir = $workingdir[0];
        $raw_depends_list = array_values(preg_grep("/\@pkgdep/", $slaveout));
        if($raw_depends_list != "") {
                if($pkgent['exclude_dependency'] != "")
                        $raw_depends_list = array_values(preg_grep($pkent['exclude_dependency'], PREG_GREP_INVERT));
                foreach($raw_depends_list as $adepend) {
                        $working_depend = explode(" ", $adepend);
                        //$working_depend = explode("-", $working_depend[1]);
                        $depend_filename = $working_depend[1] . $pkg_extension;
//                      $is_installed = array_values(preg_grep("/\b{$working_depend[0]}\b/i", $is_installed));
                        if(is_freebsd_pkg_installed($working_depend[1]) === false) {
                                pkg_fetch_recursive($working_depend[1], $depend_filename, $dependlevel + 1, $base_url);
                        } else {
                                $dependlevel++;
                                $static_output .= "\n" . str_repeat(" ", $dependlevel * 2) . $working_depend[1] . " ";
                                @fwrite($fd_log, $working_depend[1] . "\n");
                        }
                }
        }
        exec("cat {$g['tmp_path']}/y | /usr/sbin/pkg_add -fv {$fetchto} 2>&1", $pkgaddout);
        @fwrite($fd_log, $pkgname . " " . print_r($pkgaddout, true) . "\n");
        return true;
}

function download_file_with_progress_bar($url_file, $destination_file) {
        global $ch, $fout, $file_size, $downloaded, $counter, $pkg_interface;
        $file_size  = 1;
        $downloaded = 1;
        /* open destination file */
        $fout = fopen($destination_file, "wb");

        /*
                Originally by Author: Keyvan Minoukadeh
                Modified by Scott Ullrich to return Content-Length size
        */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_file);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'read_header');
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, 'read_body');
        curl_setopt($ch, CURLOPT_NOPROGRESS, '1');

        curl_exec($ch);
        fclose($fout);
        curl_close($ch);

        return 1;
}

function read_header($ch, $string) {
        global $file_size, $ch, $fout;
        $length = strlen($string);
        ereg("(Content-Length:) (.*)", $string, $regs);
        if($regs[2] <> "") {
                $file_size = intval($regs[2]);
        }
        return $length;
}

function read_body($ch, $string) {
        global $fout, $file_size, $downloaded, $counter, $sendto, $static_output, $lastseen, $pkg_interface;
        $length = strlen($string);
        $downloaded += intval($length);
        $downloadProgress = round(100 * (1 - $downloaded / $file_size), 0);
        $downloadProgress = 100 - $downloadProgress;
	if($lastseen <> $downloadProgress and $downloadProgress < 101) {
                if($sendto == "status") {
                        $tostatus = $static_status . $downloadProgress . "%";
                        update_status($tostatus);
                } else {
                        $tooutput = $static_output . $downloadProgress . "%";
                        update_output_window($tooutput);
                }
                update_progress_bar($downloadProgress);
                $lastseen = $downloadProgress;
        }
	fwrite($fout, $string);
	return $length;
}

function install_package($package, $pkg_info = "") {
	global $g, $config, $pkg_interface, $fd_log, $static_output;
	/* open logfiles and begin installation */
	if(!$fd_log) {
		if(!$fd_log = fopen("{$g['tmp_path']}/pkg_mgr_{$pkg}.log", "w")) {
			update_output_window("Warning, could not open log for writing.");
		}
	}
	@fwrite($fd_log, "Beginning package installation.\n");
	log_error('Beginning package installation for ' . $package . '.');
	update_status("Beginning package installation for " . $package . "...");
	/* fetch package information if needed */
	if(!$pkg_info or !is_array($pkg_info[$package])) {
		$pkg_info = get_pkg_info(array($package));
		$pkg_info = $pkg_info[$package]; // We're only dealing with one package, so we can strip away the extra array.
	}
	/* set up package logging streams */
	if($pkg_info['logging']) {
		mwexec("/usr/sbin/clog -i -s 32768 {$g['varlog_path']}" . $pkg_info['logging']['logfile_name']);
		chmod($g['varlog_path'] . $pkg_info['logging']['logfile_name'], 0600);
		@fwrite($fd_log, "Adding text to file /etc/syslog.conf\n");
		add_text_to_file("/etc/syslog.conf", $pkg_info['logging']['facilityname'] . "\t\t\t" . $pkg_info['logging']['logfilename']);
		mwexec("/usr/bin/killall -HUP syslogd");
	}
	/* fetch the package's configuration file */
	if($pkg_info['config_file'] != "") {
		$static_output .= "Downloading package configuration file... ";
		update_output_window($static_output);
		@fwrite($fd_log, "Downloading package configuration file...\n");
		$fetchto = substr(strrchr($pkg_info['config_file'], '/'), 1);
		download_file_with_progress_bar($pkg_info['config_file'], '/usr/local/pkg/' . $fetchto);
		if(!file_exists('/usr/local/pkg/' . $fetchto)) {
			@fwrite($fd_log, "ERROR! Unable to fetch package configuration file. Aborting installation.\n");
			if($pkg_interface == "console") {
				print "\nERROR! Unable to fetch package configuration file. Aborting package installation.\n";
				return;
			} else {
				$static_output .= "failed!\n\nInstallation aborted.";
				update_output_window($static_output);
				echo "<br>Show <a href=\"pkg_mgr_install.php?showlog=true\">install log</a></center>";
			 	exit;
			}
		}
		$static_output .= "done.\n";
		update_output_window($static_output);
	}
	/* make 'y' file */
	$fd = fopen("{$g['tmp_path']}/y", "w");
	for($line = 0; $line < 10; $line++) {
		fwrite($fd, "y\n");
	}
	fclose($fd);

	/* pkg_add the package and its dependencies */
	if($pkg_info['depends_on_package_base_url'] != "") {
		update_status("Installing " . $pkg_info['name'] . " and its dependencies.");
		$static_output .= "Downloading " . $pkg_info['name'] . " and its dependencies... ";
		$static_orig = $static_output;
		$static_output .= "\n";
		update_output_window($static_output);
		$pkg_name = substr(reverse_strrchr($pkg_info['depends_on_package'], "."), 0, -1);
		if(isset($pkg_info['skip_install_checks'])) {
			$pkg_installed = true;
		} else {
			$pkg_installed = is_freebsd_pkg_installed($pkg_name);
		}
		if($pkg_installed == false) pkg_fetch_recursive($pkg_name, $pkg_info['depends_on_package'], 0, $pkg_info['depends_on_package_base_url']);
		$static_output = $static_orig . "done.\nChecking for successful package installation... ";
		update_output_window($static_output);
		/* make sure our package was successfully installed */
		if($pkg_installed == false) $pkg_installed = is_freebsd_pkg_installed($pkg_name);
		if($pkg_installed == true) {
			$static_output .= "done.\n";
			update_output_window($static_output);
			fwrite($fd_log, "pkg_add successfully completed.\n");
		} else {
			$static_output .= "failed!\n\nInstallation aborted.";
			update_output_window($static_output);
			fwrite($fd_log, "Package WAS NOT installed properly.\n");
			fclose($fd_log);
			echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";
			echo "\n<script language=\"JavaScript\">document.progholder.style.visibility='hidden';</script>";
			sleep(1);
			die;
		}
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
	$static_output .= $to_output;
	update_output_window($static_output);
	/* install other package components */
	install_package_xml($package);
	$static_output .= "Writing configuration... ";
	update_output_window($static_output);
	write_config($changedesc);
	$static_output .= "done.";
	update_output_window($static_output);
}

function eval_once($toeval) {
	global $evaled;
	if(!$evaled) $evaled = array();
	$evalmd5 = md5($toeval);
	if(!in_array($evalmd5, $evaled)) {
		eval($toeval);
		$evaled[] = $evalmd5;
	}
	return;
}
	

function install_package_xml($pkg) {
	global $g, $config, $fd_log, $static_output;
	if(($pkgid = get_pkg_id($pkg)) == -1) {
		$static_output .= "The {$pkg} package is not installed.\n\nInstallation aborted.";
		update_output_window($static_output);
		echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";
		echo "\n<script language=\"JavaScript\">document.progholder.style.visibility='hidden';</script>";
		sleep(1);
		return;
	} else {
		$pkg_info = $config['installedpackages']['package'][$pkgid];
	}
	/* set up logging if needed */
	if(!$fd_log) {
		if(!$fd_log = fopen("{$g['tmp_path']}/pkg_mgr_{$pkg}.log", "w")) {
			update_output_window("Warning, could not open log for writing.");
		}
	}

	$configfile = substr(strrchr($pkg_info['config_file'], '/'), 1);
	if(file_exists("/usr/local/pkg/" . $configfile)) {
		$static_output .= "Loading package configuration... ";
		update_output_window($static_output);
		$pkg_config = parse_xml_config("/usr/local/pkg/" . $configfile, "packagegui");
		$static_output .= "done.\n";
		update_output_window($static_output);
		$static_output .= "Configuring package components...\n";
		update_output_window($static_output);
		/* modify system files */
		if($pkg_config['modify_system']['item'] <> "") {
			$static_output .= "\tSystem files... ";
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
		if($pkg_config['additional_files_needed'] <> "") {
			$static_output .= "\tAdditional files... ";
			$static_orig = $static_output;
			update_output_window($static_output);
			foreach($pkg_config['additional_files_needed'] as $afn) {
				$filename = get_filename_from_url($afn['item'][0]);
				if($afn['chmod'] <> "") {
					$pkg_chmod = $afn['chmod'];
				} else {
					$pkg_chmod = "";
				}
				if($afn['prefix'] <> "") {
					$prefix = $afn['prefix'];
				} else {
					$prefix = "/usr/local/pkg/";
				}
				$static_output .= $filename . " ";
                                update_output_window($static_output);
				download_file_with_progress_bar($afn['item'][0], $prefix . $filename);
				if(stristr($filename, ".tgz") <> "") {
					fwrite($fd_log, "Extracting tarball to -C for " . $filename . "...\n");
					exec("/usr/bin/tar xvzf " . $prefix . $filename . " -C / 2>&1", $tarout);
					fwrite($fd_log, print_r($tarout, true) . "\n");
				}
				if($pkg_chmod <> "") {
					fwrite($fd_log, "Changing file mode to {$pkg_chmod} for {$prefix}{$filename}\n");
					chmod($prefix . $filename, $pkg_chmod);
					system("/bin/chmod {$pkg_chmod} {$prefix}{$filename}");
				}
				$static_output = $static_orig;
                                update_output_window($static_output);
			}
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* sidebar items */
		if($pkg_config['menu'] != "") {
			$static_output .= "\tMenu items... ";
			update_output_window($static_output);
			foreach($pkg_config['menu'] as $menu) {
				$config['installedpackages']['menu'][] = $menu;
			}
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* custom commands */
		if($pkg_config['custom_php_install_command'] <> "") {
			$static_output .= "\tCustom commands... ";
			update_output_window($static_output);
			if($pkg_config['custom_php_global_functions'] <> "") {
				eval_once($pkg_config['custom_php_global_functions']);
			}
			fwrite($fd_log, "Executing post install commands...\n");
			eval_once($pkg_config['custom_php_install_command']);
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* call our before form property since we may be declaring functions
		   in it that are called later */
		if($pkg_config['custom_php_command_before_form'] <> "") {
			eval_once($pkg_config['custom_php_command_before_form']);
		}
		/* call our resync function */
		if($pkg_config['custom_php_resync_config_command'] <> "") {
			eval_once($pkg_config['custom_php_resync_config_command']);
		}
	} else {
		$static_output .= "Loading package configuration... failed!\n\nInstallation aborted.";
		update_output_window($static_output);
		fwrite($fd_log, "Unable to load package configuration. Installation aborted.\n");
                fclose($fd_log);
                echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";
                echo "\n<script language=\"JavaScript\">document.progholder.style.visibility='hidden';</script>";
                sleep(1);
		return;
	}
}

function delete_package($pkg) {
	global $g, $config, $fd_log, $static_output;
	update_status("Removing package...");
	$static_output .= "Removing package... ";
	update_output_window($static_output);
	delete_package_recursive($pkg);
	$static_output .= "done.\n";
	update_output_window($static_output);
	return;
}

function delete_package_recursive($pkg) {
	exec("/usr/sbin/pkg_info -r " . $pkg . " 2>&1", $info);
	exec("cat {$g['tmp_path']}/y | /usr/sbin/pkg_delete " . $pkg ." > /dev/null 2>&1");
	exec("/bin/ls /var/db/pkg", $pkgdb);
	if(stristr($info[0], "can't find package") != false) return;
	foreach($info as $line) {
		$depend = trim(array_pop(explode(":", $line)));
		if(in_array($depend, $pkgdb)) delete_package_recursive($depend);
	}
	$fd = fopen("{$g['tmp_path']}/y", "w");
	for($line = 0; $line < 10; $line++) {
		fwrite($fd, "y\n");
	}
	fclose($fd);
	return;
}

function delete_package_xml($pkg) {
	global $g, $config, $fd_log, $static_output;
	if(($pkgid = get_pkg_id($pkg)) == -1) {
                $static_output .= "The {$pkg} package is not installed.\n\nDeletion aborted.";
                update_output_window($static_output);
                echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";
                echo "\n<script language=\"JavaScript\">document.progholder.style.visibility='hidden';</script>";
                sleep(1);
                return;
        }
	/* set up logging if needed */
        if(!$fd_log) {
                if(!$fd_log = fopen("{$g['tmp_path']}/pkg_mgr_{$pkg}.log", "w")) {
                        update_output_window("Warning, could not open log for writing.");
                }
        }
	update_status("Removing {$pkg} components...");
	fwrite($fd_log, "Removing {$pkg} package... ");
	$static_output .= "Removing {$pkg} components...\n";
	update_output_window($static_output);
	/* parse package configuration */
	$packages = &$config['installedpackages']['package'];
	$menus = &$config['installedpackages']['menu'];
	if(file_exists("/usr/local/pkg/" . $packages[$pkgid]['configurationfile'])) {
		$pkg_config = parse_xml_config("/usr/local/pkg/" . $packages[$pkgid]['configurationfile'], "packagegui");
		/* remove menu items */
		if(is_array($pkg_config['menu'])) {
			$static_output .= "\tMenu items... ";
			update_output_window($static_output);
			foreach($menus as $menu) $instmenus[] = $menu['name'];
			foreach($pkg_config['menu'] as $menu) {
				foreach($instmenus as $key => $instmenu) {
					if($instmenu == $menu['name']) unset($menus[$key]);
				}
			}
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* evalate this package's global functions and pre deinstall commands */
		if($pkg_config['custom_php_global_functions'] <> "")
			eval_once($pkg_config['custom_php_global_functions']);
		if($pkg_config['custom_php_pre_deinstall_command'] <> "") 
			eval_once($pkg_config['custom_php_pre_deinstall_command']);
		/* remove all additional files */
		if($pkg_config['additional_files_needed'] <> "") {
			$static_output .= "\tAuxiliary files... ";
			update_output_window($static_output);
			foreach($pkg_config['additional_files_needed'] as $afn) {
				$filename = get_filename_from_url($afn['item'][0]);
				if($afn['prefix'] <> "") {
					$prefix = $afn['prefix'];
				} else {
					$prefix = "/usr/local/pkg/";
				}
				unlink_if_exists($prefix . $filename);
				if(file_exists($prefix . $filename))
				    mwexec("rm -rf {$prefix}{$filename}");
			}
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* system files */
		if($pkg_config['modify_system']['item'] <> "") {
			$static_output .= "\tSystem files... ";
			update_output_window($static_output);
			foreach($pkg_config['modify_system']['item'] as $ms) {
				if($ms['textneeded']) remove_text_from_file($ms['modifyfilename'], $ms['textneeded']);
			}
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* syslog */
		if($pkg_config['logging']['logfile_name'] <> "") {
			$static_output .= "\tSyslog entries... ";
			update_output_window($static_output);
			remove_text_from_file("/etc/syslog.conf", $pkg_config['logging']['facilityname'] . "\t\t\t\t" . $pkg_config['logging']['logfilename']);
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* deinstall commands */
		if($pkg_config['custom_php_deinstall_command'] <> "") {
			$static_output .= "\tDeinstall commands... ";
			update_output_window($static_output);
			eval_once($pkg_config['custom_php_deinstall_command']);
			$static_output .= "done.\n";
			update_output_window($static_output);
		}
		/* package XML file */
		$static_output .= "\tPackage XML... ";
		update_output_window($static_output);
		unlink_if_exists("/usr/local/pkg/" . $packages[$pkgid]['configurationfile']);
		$static_output .= "done.\n";
		update_output_window($static_output);
	}
	/* remove config.xml entries */
	$static_output .= "\tConfiguration... ";
	update_output_window($static_output);
	unset($config['installedpackages']['package'][$pkgid]);
	$static_output .= "done.\n";
	update_output_window($static_output);
	write_config("Removed {$pkg} package.");
	/* file cleanup */
	$ctag = file("/etc/crontab");
	foreach($ctag as $line) {
		if(trim($line) != "") $towrite[] = $line;
	}
	$tmptab = fopen("/tmp/crontab", "w");
	foreach($towrite as $line) {
		fwrite($tmptab, $line);
	}
	fclose($tmptab);
	rename("/tmp/crontab", "/etc/crontab");
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
	if(!$pkgdb) $pkgdb = get_pkg_db();
	if(!$alreadyseen) $alreadyseen = array();
	foreach($depend as $adepend) {
		$pkgname = reverse_strrchr($adepend['name'], '.');
		if(in_array($pkgname, $alreadyseen)) {
			continue;
		} elseif(!in_array($pkgname, $pkgdb)) {
			$size += expand_to_bytes($adepend['size']);
			$alreadyseen[] = $pkgname;
			if(is_array($adepend['depend'])) $size += walk_depend($adepend['depend'], $pkgdb, $alreadyseen);
		} else {
			continue;
		}
	}
	return $size;
}

function get_package_install_size($pkg = 'all', $pkg_info = "") {
	global $config, $g;
	if((!is_array($pkg)) and ($pkg != 'all')) $pkg = array($pkg);
	$pkgdb = get_pkg_db();
	if(!$pkg_info) $pkg_info = get_pkg_sizes($pkg);
	foreach($pkg as $apkg) {
		$size = 0;
		if(!$pkg_info[$apkg]) continue;
		$toreturn[$apkg] = expand_to_bytes(walk_depend(array($pkg_info[$apkg]), $pkgdb));
	}
	return $toreturn;
}

function squash_from_bytes($size) {
	$conv = array(1 => "B", "K", "M", "G");
	print_r($conv);
	foreach($conv as $div => $suffix) {
		$sizeorig = $size;
		if(($size /= 1024) < 1) {
			return $sizeorig . $suffix;
		}
	}
	return;
}
?>
