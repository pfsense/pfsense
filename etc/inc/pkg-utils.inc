/****h* pfSense/pkg-utils
 * NAME
 *   pkg-utils.inc - Package subsystem
 * DESCRIPTION
 *   This include contains various functions used by the pfSense package system.
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

/*     
 *  is_package_installed($packagename): returns 1 if a package is installed, 0 otherwise.
 */ 
function is_package_installed($packagename) {
    global $config;
    if($config['installedpackages']['package'] <> "")
        foreach ($config['installedpackages']['package'] as $pkg) {                     
            if($pkg['name'] == $packagename) return 1;                    
        }
    return 0;
}
            
/*     
 * lookup pkg array id#
 */ 
function get_pkg_id($pkg_name) {
    global $config;
    global $pkg_config;
    if(is_array($config['installedpackages']['package'])) {
        $i = 0;
        foreach ($config['installedpackages']['package'] as $pkg) {
            if($pkg['name'] == $pkg_name) return $i;
            $i++;
        }
        return $i;
    }
    return -1;
}

/*
 *  get_latest_package_version($pkgname): Get current version of a package. Returns latest package version or false
 *                                        if package isn't defined in the currently used pkg_config.xml.
 */
function get_latest_package_version($pkg_name) {
    global $g;
    fetch_latest_pkg_config();
    $pkg_config = parse_xml_config_pkg("{$g['tmp_path']}/pkg_config.xml", "pfsensepkgs");
    foreach($pkg_config['packages']['package'] as $pkg) {
        if($pkg['name'] == $pkg_name) {
            return $pkg['version'];
        }
    }
    return false;
}

/*
 * Lookup pkg_id in pkg_config.xml
 */
function get_available_pkg_id($pkg_name) {
    global $pkg_config, $g;
    if(!is_array($pkg_config)) {
        fetch_latest_pkg_config();
    }
    $pkg_config = parse_xml_config_pkg("{$g['tmp_path']}/pkg_config.xml", "pfsensepkgs");
    $id = 0;
    foreach($pkg_config['packages']['package'] as $pkg) {
        if($pkg['name'] == $pkg_name) {
            return $id;
        }
        $id++;
    }
    return;
}

/*
 * fetch_latest_pkg_config: download the latest pkg_config.xml to /tmp/ directory
 */
function fetch_latest_pkg_config() {
    global $g;
    global $config;
    if(!file_exists("{$g['tmp_path']}/pkg_config.xml")) {
        $pkg_config_location = $g['pkg_config_location'];
        $pkg_config_base_url = $g['pkg_config_base_url'];
        if(isset($config['system']['alt_pkgconfig_url']['enabled'])) {
            $pkg_config_location = $config['system']['alt_pkgconfig_url']['pkgconfig_base_url'] . $config['system']['alt_pkgconfig_url']['pkgconfig_filename'];
            $pkg_config_base_url = $config['system']['alt_pkgconfig_url']['pkgconfig_base_url'];
        }
        mwexec("/usr/bin/fetch -o {$g['tmp_path']}/pkg_config.xml {$pkg_config_location}");
        if(!file_exists("{$g['tmp_path']}/pkg_config.xml")) {
            print_info_box_np("Could not download pkg_config.xml from " . $pkg_config_base_url . ". Check your DNS settings.");
            die;
        }
    }    
    return;
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
 * sweep_package_processes(): Periodically kill a package's unnecessary processes
 *                            that may still be running (a server that does not automatically timeout, for example)
 */
function sweep_package_processes() {
    global $config;
    if(!$config['installedpackages']['package']) return;
    foreach($config['installedpackages']['package'] as $package) {
        $pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], "packagegui");
        if($pkg_config['swept_processes'] <> "") {
            mwexec("/usr/bin/killall " . $pkg_config['swept_processes']);
            log_error("Killed " . $package['name'] . "'s unnecessary processes.");
        }
    }
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
        $pkg_name = get_pkg_id($pkg_name);
        if($pkg_id == -1) return -1; // This package doesn't really exist - exit the function.
    } else {                                                                                  
        if(!isset($config['installedpackages']['package'][$pkg_id])) return; // No package belongs to the pkg_id passed to this function.
    }                                                                                                                                    
    $package = $config['installedpackages']['package'][$pkg_id];
    print '$package done.';                                     
    if(!file_exists("/usr/local/pkg/" . $package['configurationfile'])) { // If the package's config file doesn't exist, log an error and fetch it.
        log_error("Fetching missing configuration XML for " . $package['name']);
        mwexec("/usr/bin/fetch -o /usr/local/pkg/" . $package['configurationfile'] . " http://www.pfsense.com/packages/config/" . $package['configurationfile']);
    }                                                                                                                                                            
    $pkg_xml = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], "packagegui");
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
                log_error("Fetching missing dependency (" . $depend_name . ") for " . $pkg_name);
                mwexec("/usr/local/bin/fetch -o " . $prefix . $depend_file . " " . $item['name']['0']);
                if($item['chmod'] != "")
                    chmod($prefix . $depend_file, $item['chmod']); // Handle chmods.
            }
            switch ($format) {
            case "files":
                $depends[] = $depend_file;
                break;
            case "names":
                switch ($filetype) {
                case "all":
                    if(preg_match("/\.xml/i", $depend_file)) {
                        $depend_xml = parse_xml_config_pkg("/usr/local/pkg/" . $depend_file, "packagegui");
                        $depends[] = $depend_xml['name'];
                        break;
                    } else {
                        $depends[] = $depend_name; // If this dependency isn't package XML, use the stripped filename.
                        break;
                    }
                case ".xml":
                    $depend_xml = parse_xml_config_pkg("/usr/local/pkg/" . $depend_file, "packagegui");
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
        //if($show_message == true) print "(f)"; Don't mess with this until the package system has settled.
        log_error("Fetching missing configuration XML for " . $package['name']);
        mwexec("/usr/bin/fetch -o /usr/local/pkg/" . $package['configurationfile'] . " http://www.pfsense.com/packages/config/" . $package['configurationfile']);
    }                                                                                                                                                            
    $pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], "packagegui");
    if(isset($pkg_config['nosync'])) continue;                                                          
    //if($show_message == true) print "Syncing " . $pkg_name;
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
                $item_config = parse_xml_config_pkg("/usr/local/pkg/" . $item, "packagegui");
                if(isset($item_config['nosync'])) continue;
                if($item_config['custom_php_command_before_form'] <> "") {
                    eval($item_config['custom_php_command_before_form']);
                    print "Evaled dependency.";
                }
                if($item_config['custom_php_resync_config_command'] <> "") {
                    eval($item_config['custom_php_resync_config_command']);
                    print "Evaled dependency.";
                }
                if($show_message == true) print " " . $item_config['name'];
            }
        }
    }
    // if($show_message == true) print ".";
}

function pkg_fetch_recursive($pkgname, $filename, $dependlevel = 0, $base_url = 'http://ftp2.freebsd.org/pub/FreeBSD/ports/i386/packages-5.4-release/Latest') {
        global $pkgent, $static_status, $static_output, $g, $fd_log;
        $pkg_extension = strrchr($filename, '.');
        $static_output .= "\n" . str_repeat(" ", $dependlevel * 2) . $pkgname . " ";
        $fetchto = "/tmp/apkg_" . $pkgname . $pkg_extension;
        download_file_with_progress_bar($base_url . "/" . $filename, $fetchto);
//      update_output_window($static_output . "\n\n" . $pkg_progress);
        exec("/usr/bin/bzcat {$fetchto} | /usr/bin/tar -O -f - -x +CONTENTS", $slaveout);
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
                        exec("ls /var/db/pkg", $is_installed);
                        $pkg_installed = false;
                        foreach($is_installed as $is_inst) {
                                if($is_inst == $working_depend[1]) {
                                        $pkg_installed = true;
                                        break;
                                }
                        }
//                      $is_installed = array_values(preg_grep("/\b{$working_depend[0]}\b/i", $is_installed));
                        if($pkg_installed === false) {
                                pkg_fetch_recursive($working_depend[1], $depend_filename, $dependlevel + 1, $base_url);
                        } else {
                                $dependlevel++;
                                $static_output .= "\n" . str_repeat(" ", $dependlevel * 2) . $working_depend[1] . " ";
                                fwrite($fd_log, $working_depend[1] . "\n");
                        }
                }
        }
        exec("cat {$g['tmp_path']}/y | /usr/sbin/pkg_add -fv {$fetchto} 2>&1", $pkgaddout);
        fwrite($fd_log, $pkgname . " " . print_r($pkgaddout, true) . "\n");
        return true;
}

function download_file_with_progress_bar($url_file, $destination_file) {
        global $ch, $fout, $file_size, $downloaded, $counter;
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
        global $fout, $file_size, $downloaded, $counter, $sendto, $static_output, $lastseen;
        $length = strlen($string);
        $downloaded += intval($length);
        $downloadProgress = round(100 * (1 - $downloaded / $file_size), 0);
        $downloadProgress = 100 - $downloadProgress;
        /*
           lastseen is used to prevent from spamming firefox with hundreds of
           unnecessary javascript update messages which sends the clients
           firefox utilization to 100%
        */
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
