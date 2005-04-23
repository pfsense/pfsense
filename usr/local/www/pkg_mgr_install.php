#!/usr/local/bin/php
<?php
/* $Id$ */
/*
    pkg_mgr_install.php
    part of pfSense (http://www.pfSense.com)
    Copyright (C) 2005 Scott Ullrich and Colin Smith
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

require("guiconfig.inc");
require("xmlparse_pkg.inc");

conf_mount_rw();

/* /usr/local/pkg/ is where xml package files are stored. */
make_dirs("/usr/local/pkg");
/* /usr/local/pkg/pf is where custom php hook packages live to alter the rules when needed */
make_dirs("/usr/local/pkg/pf");
/* /usr/local/www/ext is where package links live for the left hand pane */
make_dirs("/usr/local/www/ext");

$a_out = &$pkg_config['packages'];
$packages_to_install = Array();
$static_output = "";
$static_status = "";
$sendto = "output";

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("System: Package Manager: Install Package");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>
<p class="pgtitle">System: Package Manager: Install Package</p>
<form action="pkg_mgr_install.php" method="post">

<?php if ($savemsg) print_info_box($savemsg); ?>
<?php

if($_GET['showlog'] <> "") {
            echo "<table>";
            echo "<tr><td>";
            echo "<pre>";
            // reopen and read log in
            $fd = fopen("{$g['tmp_path']}/pkg_mgr.log", "r");
            $tmp = "";
            while(!feof($fd)) {
                        $tmp .= fread($fd,49);
            }
            fclose($fd);
            echo $tmp;
            echo "</pre>";
            echo "</td></tr>";
            echo "</table>";
            exit;
}

/*
 *   open logging facility
 */
$fd_log = fopen("{$g['tmp_path']}/pkg_mgr.log", "w");
if(!$fd_log) log_error("Warning, could not open {$g['tmp_path']}/pkg_mgr.log for writing");
fwrite($fd_log, "Begin of Package Manager installation session.\n");

fetch_latest_pkg_config();

$pkg_config = parse_xml_config_pkg("{$g['tmp_path']}/pkg_config.xml", "pfsensepkgs");

if(!$pkg_config['packages'])
    print_info_box_np("Could not find any packages in pkg_config.xml");

?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">  <tr><td>
  <ul id="tabnav">
    <li class="tabinact"><a href="pkg_mgr.php">Available Packages</a></li>
    <li class="tabact">Installed Packages</a></li>
  </ul>
  </td></tr>
  <tr>
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
               <tr>
                 <td>
                     <!-- progress bar -->
                     <center>
                     <table id="progholder" name="progholder" height='20' border='1' bordercolor='black' width='420' bordercolordark='#000000' bordercolorlight='#000000' style='border-collapse: collapse' colspacing='2' cellpadding='2' cellspacing='2'><tr><td><img border='0' src='progress_bar.gif' width='280' height='23' name='progressbar' id='progressbar'></td></tr></table>
                     <br>
	             <!-- status box -->
                     <textarea cols="60" rows="1" name="status" id="status" wrap="hard">Beginning package installation.</textarea>
                     <!-- command output box -->
	             <textarea cols="60" rows="25" name="output" id="output" wrap="hard"></textarea>
                     </center>
                 </td>
               </tr>
        </table>
    </td>
  </tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>

<?php



if($_GET['mode'] == "reinstallall") {
    /*
     *  Loop through installed packages and if name matches
     *  push the package id onto array to reinstall
     */
    $output_static = "";
    $counter = 0;
    if(is_array($pkg_config['packages']['package']))
                foreach($pkg_config['packages']['package'] as $available_package) {
                        if(is_array($config['installedpackages']['package']))
                                    foreach($config['installedpackages']['package'] as $package) {
                                        if($package['name'] == $available_package['name']) {
                                            array_push($packages_to_install, $counter);
                                        $output_static .= "Adding " . $package['name'] . " to installation array.\n";
                                        update_output_window($output_static);
                                            fwrite($fd_log, "Adding (" . $counter . ") " . $package['name'] . " to package installation array.\n" . $status);
                                        }
                                    }
                        $counter++;
                }
} else {
            /*
             * Push the desired package id onto the install packages array
             */
            fwrite($fd_log, "Single package installation started.\n");
            array_push($packages_to_install, $_GET['id']);
}

/*
 *  Loop through packages_to_install, installing needed packages
 */
foreach ($packages_to_install as $id) {

    fwrite($fd_log, "Begin package ${id}\n");

    $pkg_config = parse_xml_config_pkg("{$g['tmp_path']}/pkg_config.xml", "pfsensepkgs");

    /*
     * install the package
     */

    // Ensure directories are in place for pkg_add.
    safe_mkdir("{$g['www_path']}/ext/Services", 0755);
    safe_mkdir("{$g['www_path']}/ext/System", 0755);
    safe_mkdir("{$g['www_path']}/ext/Interfaces", 0755);
    safe_mkdir("{$g['www_path']}/ext/Firewall", 0755);
    safe_mkdir("{$g['www_path']}/ext/VPN", 0755);
    safe_mkdir("{$g['www_path']}/ext/Status", 0755);
    safe_mkdir("{$g['www_path']}/ext/Diagnostics", 0755);
    safe_mkdir("/usr/local/pkg", 0755);

    $a_out = &$pkg_config['packages']['package'];

    if($pkg_config['packages']['package'][$id]['verifyinstalledpkg'] <> "")
        $package_to_verify = $pkg_config['packages']['package'][$id]['verifyinstalledpkg'];
    else
        $package_to_verify = $pkg_config['packages']['package'][$id]['name'];

    $pkgent = array();
    $pkgent['name'] = $pkg_config['packages']['package'][$id]['name'];
    $pkgent['descr'] = $pkg_config['packages']['package'][$id]['descr'];
    $pkgent['category'] = $pkg_config['packages']['package'][$id]['category'];

    $pkgent['version'] = $pkg_config['packages']['package'][$id]['version'];

    $pkgent['depends_on_package'] = $a_out[$id]['depends_on_package'];
    $pkgent['depends_on_package_base_url'] = $a_out[$id]['depends_on_package_base_url'];
    $pkgent['pfsense_package'] = $a_out[$id]['pfsense_package'];
    $pkgent['pfsense_package_base_url'] = $a_out[$id]['pfsense_package_base_url'];
    $pkgent['configurationfile'] = $a_out[$id]['configurationfile'];
    if($pkg_config['packages']['package'][$id]['logging']) {
        // logging facilities.
        $pkgent['logging']['facility'] = $pkg_config['packages']['package'][$id]['logging']['facility'];
        $pkgent['logging']['logfile_name'] = $pkg_config['packages']['package'][$id]['logging']['logfile_name'];
        mwexec("/usr/sbin/clog -i -s 32768 {$g['varlog_path']}" . $pkgent['logging']['logfile_name']);
        chmod($g['varlog_path'] . $pkgent['logging']['logfile_name'], 0600);
        fwrite($fd_log, "Adding text to file /etc/syslog.conf\n");
        add_text_to_file("/etc/syslog.conf", $pkgent['logging']['facilityname'] . "\t\t\t" . $pkgent['logging']['logfilename']);
        mwexec("/usr/bin/killall -HUP syslogd");
    }
    $a_out = &$config['packages']['package']; // save item to installedpkgs
    fwrite($fd_log, "Begining (" . $id. ") " . $pkgent['name'] . " package installation.\n" . $status);

    log_error("Begining (" . $id. ") " . $pkgent['name'] . " package installation.");
    $static_status = "Beginning package installation for " . $pkgent['name'] . "...";
    update_status($static_status);

    fwrite($fd_log, "ls {$g['vardb_path']}/pkg | grep " . $package_to_verify . "\n" . $status);
    if($status <> "") {
                // package is already installed!?
                if(!$_GET['mode'] == "reinstallall")
                    print_info_box_np("NOTICE! " . $pkgent['name'] . " is already installed!  Installation will be registered.");
    }

    if($pkg_config['packages']['package'][$id]['config_file'] <> "") {
        $static_output .= "Downloading package configuration file... ";
	download_file_with_progress_bar($pkg_config['packages']['package'][$id]['config_file'], "/usr/local/pkg/" . substr(strrchr($pkg_config['packages']['package'][$id]['config_file'], '/'), 1));
        fwrite($fd_log, "Downloading configuration file " . $pkg_config['packages']['package'][$id]['config_file'] . "...\n");
        if(!file_exists("/usr/local/pkg/" . substr(strrchr($pkg_config['packages']['package'][$id]['config_file'], '/'), 1))) {
            fwrite($fd_log, "ERROR!  Could not fetch " . $pkg_config['packages']['package'][$id]['config_file']);
            $static_output .= "failed!\n\nInstallation aborted.";
            update_output_window($static_output);
            echo "<br>Show <a href=\"pkg_mgr_install.php?showlog=true\">install log</a></center>";
            exit;
        }
	$static_output .= "done.\n";
	update_output_window($static_output);
    }

    update_status("Downloading and installing " . $pkgent['name'] . " and its dependencies.");
    fwrite($fd_log, "Downloading and installing " . $pkgent['name'] . " - " . $pkgent['pfsense_package'] . "...\n");

    /*
     * Open a /tmp/y file which will basically tell the
     * pkg_delete script to delete users and such if it asks.
     */
    $fd = fopen("{$g['tmp_path']}/y", "w");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fwrite($fd, "y\n");
    fclose($fd);

    /* This directive is not yet used.
    if ($pkgent['pfsense_package_base_url'] <> "") {
        fwrite($fd_log, "Executing: cd {$g['tmp_path']}/ && /usr/sbin/pkg_add -r " . $pkgent['pfsense_package_base_url'] . "/" . $pkgent['pfsense_package'] . "\n" . $text);
        $text = exec_command_and_return_text("cd {$g['tmp_path']}/ && cat {$g['tmp_path']}/y | /usr/sbin/pkg_add -r " . $pkgent['pfsense_package_base_url'] . "/" . $pkgent['pfsense_package']);
        update_output_window($text);
    }
    */

    if ($pkgent['depends_on_package_base_url'] <> "") {
                update_status("Installing " . $pkgent['name'] . " and its dependencies.");
                $static_output .= "Downloading " . $pkgent['name'] . " and its dependencies... ";
		$static_orig = $static_output;
		$static_output .= "\n";
		update_output_window($static_output);
		pkg_fetch_recursive($pkgent['name'] . "-" . $pkgent['version'], $pkgent['depends_on_package'], 0, $pkgent['depends_on_package_base_url']);
		$static_output = $static_orig . "done.\n";
		update_output_window($static_output);
    }

    if ($pkgent['depends_on_package_base_url'] <> "" or $pkgent['pfsense_package_base_url'] <> "") {
        $static_output .= "Checking for successful package installation... ";
	update_output_window($static_output);
        exec("ls {$g['vardb_path']}/pkg | grep " . $package_to_verify, $status);
        fwrite($fd_log, $status[0] . "\n");
        if($status[0] <> "") {
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

    $config = parse_xml_config("{$g['conf_path']}/config.xml", $g['xml_rootobj']);

    $pkgid = get_pkg_id($pkgent['name']);

    if(!$_GET['mode'] == "reinstallall") {
	$static_output .= "Saving updated package information... ";
	update_output_window($static_output);
	if($pkgid == -1) {
		$config['installedpackages']['package'][] = $pkgent;
		$changedesc = "Installed {$pkgent['name']} package.";
		$to_output = "done.\n";
	} else {
		$config['installedpackages']['package'][$pkgid] = $pkgent;
		$changedesc = "Overwrote previous installation of {$pkgent['name']}.";
		$to_output = "overwrite!\n";
	}
        write_config($changedesc);
        conf_mount_rw();
	$static_output .= $to_output;
	update_output_window($static_output);
    }

    $name = $pkgent['name'];

    update_status("Finishing package installation...");

    /*
     * parse the config file for this package and install neededtext items.
     *
     */
    if(file_exists("/usr/local/pkg/" . $pkgent['name'] . ".xml")) {
                $package_conf = parse_xml_config_pkg("/usr/local/pkg/" . $pkgent['name'] . ".xml", "packagegui");
                if($package_conf['modify_system']['item'] <> "") {
		    $static_output .= "Modifying system files... ";
		    update_output_window($static_output);
                    foreach ($package_conf['modify_system']['item'] as $ms) {
                        if($ms['textneeded']) {
                            add_text_to_file($ms['modifyfilename'],$ms['textneeded']);
                        }
                    }
		    $static_output .= "done.\n";
		    update_output_window($static_output);
                }

                /*
                 * fetch additional files needed for package if defined
                 * and uncompress if needed.
                 */
                if ($package_conf['additional_files_needed'] <> "") {
		    $static_output .= "Downloading additional files needed for " . $pkgent['name'] . "... ";
                    update_output_window($static_output);
		    $i = 0;
		    $afn_count = count($package_conf['additional_files_needed']) -1;
                    foreach($package_conf['additional_files_needed'] as $afn) {
                        $filename = get_filename_from_url($afn['item'][0]);
                        fwrite($fd_log, "Downloading additional files needed for package " . $filename . "...\n");
			if ($i == $afn_count) {
				$static_orig = $static_output . $filename . ".\n";
			} else {
				$static_orig = $static_output . $filename . ", ";
			}
			$static_output .= $filename . " ";
			update_output_window($static_output);
                        $prefix = "/usr/local/pkg/";
                        $pkg_chmod = "";
                        if($afn['chmod'] <> "")
                            $pkg_chmod = $afn['chmod'];
                        if($afn['prefix'] <> "")
                            $prefix = $afn['prefix'];
                        download_file_with_progress_bar($afn['item'][0], $prefix . $filename);
			$static_output = $static_orig;
			update_output_window($static_output);
                        if(stristr($filename, ".tgz") <> "") {
                            update_status("Extracting tgz archive to -C for " . $filename);
                            fwrite($fd_log, "Extracting tgz archive to -C for " . $filename . " ...\n");
                            system("/usr/bin/tar xzvf " . $prefix . $filename . " -C / >/dev/null 2>&1");
                        }
                        if($pkg_chmod <> "") {
                            fwrite($fd_log, "Changing file mode for {$pkg_chmod} {$prefix}{$filename}\n");
                            chmod($prefix . $filename, $pkg_chmod);
                            system("/bin/chmod {$pkg_chmod} {$prefix}{$filename}");
                        }
		        $i++;
                    }
                }

                /*
                 * loop through menu installation items
                 * installing multiple items if need be.
                */
                if(is_array($package_conf['menu'])) {
		    $static_output .= "Installing menu items... ";
		    update_output_window($static_output);
                    foreach ($package_conf['menu'] as $menu) {
                        // install menu item into the ext folder
                        fwrite($fd_log, "Adding menu option to " . $menu['section'] . "/" . $menu['name'] . "\n");
                        $fd = fopen("{$g['www_path']}/ext/" . $menu['section'] . "/" . $menu['name'] , "w");
                        if($menu['url'] <> "") {
                                    // override $myurl for script.
                                    $toeval = "\$myurl = \"" . getenv("HTTP_HOST") . "\"; \n";
                                    $error_message = "";
                                    if(php_check_syntax($toeval, $error_message) == false)
                                        eval($toeval);
                                    // eval url so that above $myurl item can be processed if need be.
                                    $urltmp = $menu['url'];
                                    $toeval = "\$url = \"" . $urltmp . "\"; \n";
                                    if(php_check_syntax($toeval, $error_message) == false)
                                        eval($toeval);
                                    fwrite($fd, $url . "\n");
                        } else {
                                    $xml = "";
                                    if(stristr($menu['configfile'],".xml") == "") $xml = ".xml";
                                    fwrite($fd, "/pkg.php?xml=" . $menu['configfile'] . $xml . "\n");
                        }
                        fclose($fd);
                    }
		    $static_output .= "done.\n";
		    update_output_window($static_output);
		}
    } else {
                update_output_window("WARNING! /usr/local/pkg/" . $pkgent['name'] . ".xml" . " does not exist!\n");
                fwrite($fd_log, "WARNING! /usr/local/pkg/" . $pkgent['name'] . ".xml" . " does not exist!\n");
    }
    fwrite($fd_log, "End of Package Manager installation session.\n");

    /* return dependency list to output later.
    $command = "TODELETE=`ls /var/db/pkg | grep " . $name . "` && /usr/sbin/pkg_info -r \$TODELETE | grep Dependency: | cut -d\" \" -f2";
    $dependencies = exec_command_and_return_text($command);
    if($dependencies == "")
        fwrite($fd_log, "Installed package " . $name);
    else
        fwrite($fd_log, "Installed package " . $name . " and the following dependencies:\n" . $dependencies);
    */

    if($package_conf['custom_php_install_command'] <> "") {
	$static_output .= "Executing post install commands... ";
	update_output_window($static_output);
	if($package_conf['custom_php_global_functions'] <> "")
	    if(php_check_syntax($package_conf['custom_php_global_functions'], $error_message) == false)
		eval($package_conf['custom_php_global_functions']);
        fwrite($fd_log, "Executing post install commands...\n");
        $error_message = "";
        if($package_conf['custom_php_command_before_form'] <> "")
            if(php_check_syntax($package_conf['custom_php_command_before_form'], $error_message) == false)
                eval($package_conf['custom_php_command_before_form']);
        if(php_check_syntax($package_conf['custom_php_install_command'], $error_message) == false)
            eval($package_conf['custom_php_install_command']);
	$static_output .= "done.\n";
	update_output_window($static_output);
    }
    update_status("Package installation completed.");
    $static_output .= "\nPackage installation successful.";
    update_output_window($static_output);
    fwrite($fd_log, "Package installation completed.\n");
    log_error("Package " . $pkgent['name'] . " installation completed successfully.\n");
    fwrite($fd_log, "Status window output:\n" . $static_output);
}

// Delete all temporary package tarballs and staging areas.
unlink_if_exists("/tmp/apkg_*");
rmdir_recursive("/var/tmp/instmp*");


// close log
fclose($fd_log);

echo "<p><center>Installation completed.  Show <a href=\"pkg_mgr_install.php?showlog=true\">install log</a></center>";

echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";

conf_mount_ro();

?>
