#!/usr/local/bin/php
<?php
/*
    pkg_mgr_install.php
    part of pfSense (http://www.pfSense.com)
    Copyright (C) 2004 Scott Ullrich
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

/* /usr/local/pkg/ is where xml package files are stored. */
if(!file_exists("/usr/local/pkg/")) mwexec("mkdir -p /usr/local/pkg/");
/* /usr/local/pkg/pf is where custom php hook packages live to alter the rules when needed */
if(!file_exists("/usr/local/pkg/pf")) mwexec("mkdir -p /usr/local/pkg/pf");
/* /usr/local/www/ext is where package links live for the left hand pane */
if(!file_exists("/usr/local/www/ext/")) mwexec("mkdir -p /usr/local/www/ext");

$pb_percent = 1;

/*
 *   update_output_window: update top textarea dynamically.
 */
function update_status($status) {
            echo "\n<script language=\"JavaScript\">document.forms[0].status.value=\"" . $status . "\";</script>";
}

/*
 *   update_output_window: update bottom textarea dynamically.
 */
function update_output_window($text) {
            $log = ereg_replace("\n", "\\n", $text);
            echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = \"" . $log . "\";</script>";
}

/*
 *   get_dir: return an array of $dir
 */
function get_dir($dir) {
            $dir_array = array();
            $d = dir($dir);
            while (false !== ($entry = $d->read())) {
                        array_push($dir_array, $entry);
            }
            $d->close();
            return $dir_array;
}

/*
 *   exec_command_and_return_text_array: execute command and return output
 */
function exec_command_and_return_text_array($command) {
            $counter = 0;
            $fd = popen($command . " 2>&1 ", "r");
            echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = '';</script>\n";
            while(!feof($fd)) {
                        $tmp .= fread($fd,49);
                        echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = this.document.forms[0].output.value + \"" . $tmp . $extrabreak .  "\"; </script>";
            }
            fclose($fd);
            $temp_array = split("\n", $tmp);
            return $tmp_array;
}

/*
 *   exec_command_and_return_text: execute command and return output
 */
function exec_command_and_return_text($command) {
            $counter = 0;
            $tmp = "";
            $fd = popen($command . " 2>&1 ", "r");
            echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = '';</script>\n";
            while(!feof($fd)) {
                        $tmp .= fread($fd,49);
                        echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = this.document.forms[0].output.value + \"" . $tmp . $extrabreak .  "\"; </script>";
            }
            fclose($fd);
            return $tmp;
}

/*
 *   exec_command_and_return_text: execute command and update output window dynamically
 */
function execute_command_return_output($command) {
    global $fd_log, $pb_percent;
    if($pb_percent > 100) $pb_percent = 1;
    update_progress_bar($pb_percent);
    $fd = popen($command . " 2>&1 ", "r");
    echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = \"\";</script>";
    $counter = 0;
    $counter2 = 0;
    while(!feof($fd)) {
	$tmp = fread($fd, 50);
        fwrite($fd_log, $tmp);
	$tmp1 = ereg_replace("\n","\\n", $tmp);
	$text = ereg_replace("\"","'", $tmp1);
	echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = this.document.forms[0].output.value + \"" . $tmp . $extrabreak .  "\"; f('output'); </script>";
    }
    $pb_percent++;
    fclose($fd);
}

function update_progress_bar($percent) {
            if($percent > 100) $percent = 1;
            echo "\n<script type=\"text/javascript\" language=\"javascript\">";
            echo "\ndocument.progressbar.style.width='" . $percent . "%';";
            echo "\n</script>";
}

$a_out = &$pkg_config['packages'];

$packages_to_install = Array();

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
<form action="firewall_nat_out_load_balancing.php" method="post">

<?php if ($savemsg) print_info_box($savemsg); ?>
<?php

if($_GET['showlog'] <> "") {
            echo "<table>";
            echo "<tr><td>";
            echo "<pre>";
            // reopen and read log in
            $fd = fopen("/tmp/pkg_mgr.log", "r");
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
$fd_log = fopen("/tmp/pkg_mgr.log", "w");
if(!$fd_log) log_error("Warning, could not open /tmp/pkg_mgr.log for writing");
fwrite($fd_log, "Begin of Package Manager installation session.\n");

if(!file_exists("/tmp/pkg_config.xml")) {
            mwexec("cd {$g['tmp_path']} && /usr/bin/fetch \"http://www.pfsense.com/packages/pkg_config.xml\" >/dev/null 2>&1 ");
            if(!file_exists("{$g['tmp_path']}/pkg_config.xml")) {
                        print_info_box_np("Could not download pkg_config.xml from pfSense.com.  Check your DNS settings.");
                        die;
            }
}

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
                     <textarea cols="60" rows="1" name="status" id="status" wrap="hard">One moment please... This will take a while!</textarea>
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
    $counter = 0;
    foreach($pkg_config['packages']['package'] as $available_package) {
        foreach($config['installedpackages']['package'] as $package) {
            if($package['name'] == $available_package['name']) {
                array_push($packages_to_install, $counter);
                update_status("Adding package " . $package['name']);
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

    $pkg_config = parse_xml_config_pkg("{$g['tmp_path']}/pkg_config.xml", "pfsensepkgs");

    update_progress_bar($pb_percent);
    $pb_percent += 10;

    /*
     * install the package
     */

    // Ensure directories are in place for pkg_add.
    mwexec("mkdir /usr/local/www/ext/Services >/dev/null 2>&1");
    mwexec("mkdir /usr/local/www/ext/System >/dev/null 2>&1");
    mwexec("mkdir /usr/local/www/ext/Interfaces >/dev/null 2>&1");
    mwexec("mkdir /usr/local/www/ext/Firewall >/dev/null 2>&1");
    mwexec("mkdir /usr/local/www/ext/VPN >/dev/null 2>&1");
    mwexec("mkdir /usr/local/www/ext/Status >/dev/null 2>&1");
    mwexec("mkdir /usr/local/www/ext/Diagnostics >/dev/null 2>&1");
    mwexec("mkdir /usr/local/pkg >/dev/null 2>&1");

    update_progress_bar($pb_percent);
    $pb_percent += 10;

    $a_out = &$pkg_config['packages']['package'];
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
        mwexec("/usr/sbin/clog -i -s 32768 /var/log/" . $pkgent['logging']['logfile_name']);
        mwexec("chmod 0600 /var/log/" . $pkgent['logging']['logfile_name']);
        fwrite($fd_log, "Adding text to file /etc/syslog.conf\n");
        add_text_to_file("/etc/syslog.conf", $pkgent['logging']['facilityname'] . "\t\t\t" . $pkgent['logging']['logfilename']);
        mwexec("/usr/bin/killall -HUP syslogd");
    }
    $a_out = &$config['packages']['package']; // save item to installedpkgs
    fwrite($fd_log, "Begining (" . $id. ") " . $pkgent['name'] . " package installation.\n" . $status);

    log_error("Begining (" . $id. ") " . $pkgent['name'] . " package installation.");

    update_progress_bar($pb_percent);
    $pb_percent += 10;

    fwrite($fd_log, "ls /var/db/pkg | grep " . $pkgent['name'] . "\n" . $status);
    if($status <> "") {
                // package is already installed!?
                if(!$_GET['mode'] == "reinstallall")
                    print_info_box_np("NOTICE! " . $pkgent['name'] . " is already installed!  Installation will be registered.");
    }

    if($pkg_config['packages']['package'][$id]['config_file'] <> "") {
        update_status("Downloading configuration file.");
        fwrite($fd_log, "Downloading configuration file " . $pkg_config['packages']['package'][$id]['config_file'] . " ... \n");
        update_progress_bar($pb_percent);
        $pb_percent += 10;
        mwexec("cd /usr/local/pkg/ && fetch " . $pkg_config['packages']['package'][$id]['config_file']);
        if(!file_exists("/usr/local/pkg/" . $pkgent['name'] . ".xml")) {
            fwrite($fd_log, "ERROR!  Could not fetch " . $pkg_config['packages']['package'][$id]['config_file']);
            update_output_window("ERROR!  Could not fetch " . $pkg_config['packages']['package'][$id]['config_file'] . "\n");
            exit;
        }
    }

    update_status("Downloading and installing " . $pkgent['name'] . " - " . $pkgent['pfsense_package'] . " and its dependencies ... This could take a moment ...");
    fwrite($fd_log, "Downloading and installing " . $pkgent['name'] . " ... \n");

    update_progress_bar($pb_percent);
    $pb_percent += 10;

    if ($pkgent['pfsense_package_base_url'] <> "") {
        $text = exec_command_and_return_text("cd /tmp/ && /usr/sbin/pkg_add -r " . $pkgent['pfsense_package_base_url'] . "/" . $pkgent['pfsense_package']);
        update_output_window($text);
        fwrite($fd_log, "Executing: cd /tmp/ && /usr/sbin/pkg_add -r " . $pkgent['pfsense_package_base_url'] . "/" . $pkgent['pfsense_package'] . "\n" . $text);
    }

    update_progress_bar($pb_percent);
    $pb_percent += 10;

    if ($pkgent['depends_on_package_base_url'] <> "") {
                update_status("Downloading and installing " . $pkgent['name'] . " and its dependencies ... This could take a moment ...");
                $text = exec_command_and_return_text("cd /tmp/ && /usr/sbin/pkg_add -r " . $pkgent['depends_on_package_base_url'] . "/" . $pkgent['depends_on_package']);
                update_output_window($text);
                fwrite($fd_log, "cd /tmp/ && /usr/sbin/pkg_add -r " . $pkgent['depends_on_package_base_url'] . "/" . $pkgent['depends_on_package'] . "\n" . $text);;
    }

    if ($pkgent['depends_on_package_base_url'] <> "" or $pkgent['pfsense_package_base_url'] <> "") {
        $status = exec_command_and_return_text("ls /var/db/pkg | grep " . $pkgent['name']);
        fwrite($fd_log, "ls /var/db/pkg | grep " . $pkgent['name'] . "\n" . $status);
        if($status <> "") {
                    update_status("Package installed.  Lets finish up.");
                    fwrite($fd_log, "Package installed.  Lets finish up.\n");
        } else {
                    fwrite($fd_log, "Package WAS NOT installed properly.\n");
                    fclose($fd_log);
                    $filecontents = exec_command_and_return_text("cat " . $file);
                    update_progress_bar(100);
                    echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";
                    echo "\n<script language=\"JavaScript\">document.progholder.style.visibility='hidden';</script>";
                    update_status("Package WAS NOT installed properly...Something went wrong..\n" . $filecontents);
                    update_output_window("Error during package installation.");
                    sleep(1);
                    die;
        }
    }

    update_progress_bar($pb_percent);
    $pb_percent += 10;

    $config = parse_xml_config("{$g['conf_path']}/config.xml", $g['xml_rootobj']);

    update_progress_bar($pb_percent);
    $pb_percent += 10;

    $config['installedpackages']['package'][] = $pkgent;

    if (isset($id) && $a_out[$id])
            $a_out[$id] = $pkgent;
    else
            $a_out[] = $pkgent;

    update_progress_bar($pb_percent);
    $pb_percent += 10;

    if(!$_GET['mode'] == "reinstallall")
        write_config();

    update_progress_bar($pb_percent);
    $pb_percent += 10;

    $name = $pkgent['name'];

    update_progress_bar($pb_percent);
    $pb_percent++;

    /*
     * parse the config file for this package and install neededtext items.
     *
    */
    if(file_exists("/usr/local/pkg/" . $pkgent['name'] . ".xml")) {
                $package_conf = parse_xml_config_pkg("/usr/local/pkg/" . $pkgent['name'] . ".xml", "packagegui");
                if($package_conf['modify_system']['item'] <> "") {
                    foreach ($package_conf['modify_system']['item'] as $ms) {
                        update_progress_bar($pb_percent);
                        $pb_percent += 10;
                        if($ms['textneeded']) {
                            add_text_to_file($ms['modifyfilename'],$ms['textneeded']);
                        }
                    }
                }

                /*
                 * fetch additional files needed for package if defined
                 * and uncompress if needed.
                 */
                if ($package_conf['additional_files_needed'] <> "") {
                            foreach($package_conf['additional_files_needed']['item'] as $afn) {
                                        update_progress_bar($pb_percent);
                                        $pb_percent += 10;
                                        $filename = get_filename_from_url($afn);
                                        fwrite($fd_log, "Downloading additional files needed for package " . $filename . " ...");
                                        update_status("Downloading additional files needed for package " . $filename . " ...");
                                        system("cd /usr/local/pkg && /usr/bin/fetch " .  $afn . " 2>/dev/null");
                                        if(stristr($filename, '.tgz') <> "") {
                                                    update_status("Extracting tgz archive to -C for " . $filename);
                                                    system("cd /usr/local/pkg && tar xzvf " . $filename . " -C / >/dev/null 2>&1");
                                        }
                            }
                }

                /*
                 * loop through menu installation items
                 * installing multiple items if need be.
                */
                if(is_array($package_conf['menu']))
                    foreach ($package_conf['menu'] as $menu) {
                                // install menu item into the ext folder
                                fwrite($fd_log, "Adding menu option to " . $menu['section'] . "/" . $menu['name'] . "\n");
                                $fd = fopen("/usr/local/www/ext/" . $menu['section'] . "/" . $menu['name'] , "w");
                                if($menu['url'] <> "") {
                                            // override $myurl for script.
                                            $toeval = "\$myurl = \"" . getenv("HTTP_HOST") . "\"; \n";
                                            eval($toeval);
                                            // eval url so that above $myurl item can be processed if need be.
                                            $urltmp = $menu['url'];
                                            $toeval = "\$url = \"" . $urltmp . "\"; \n";
                                            eval($toeval);
                                            fwrite($fd, $url . "\n");
                                } else {
                                            $xml = "";
                                            if(stristr($menu['configfile'],".xml") == "") $xml = ".xml";
                                            fwrite($fd, "/pkg.php?xml=" . $menu['configfile'] . $xml . "\n");
                                }
                                fclose($fd);
                }
    } else {
                update_output_window("WARNING! /usr/local/pkg/" . $pkgent['name'] . ".xml" . " does not exist!\n");
                fwrite($fd_log, "WARNING! /usr/local/pkg/" . $pkgent['name'] . ".xml" . " does not exist!\n");
    }
    fwrite($fd_log, "End of Package Manager installation session.\n");

    update_progress_bar($pb_percent);
    $pb_percent += 10;

    // return dependency list to output later.
    $command = "TODELETE=`ls /var/db/pkg | grep " . $name . "` && /usr/sbin/pkg_info -r \$TODELETE | grep Dependency: | cut -d\" \" -f2";
    $dependencies = exec_command_and_return_text($command);
    fwrite($fd_log, "Installed " . $name . " and the following dependencies:\n" . $dependencies);

    update_progress_bar($pb_percent);
    $pb_percent += 10;

    if($package_conf['custom_php_install_command']) {
        update_status("Executing post install commands...");
        $pb_percent += 50;
        update_progress_bar(50);
        eval($package_conf['custom_php_install_command']);
    }

    $pb_percent += 10;
    update_progress_bar($pb_percent);

    if ($pkgent['depends_on_package_base_url'] <> "" or $pkgent['pfsense_package_base_url'] <> "") {
        $status = exec_command_and_return_text("ls /var/db/pkg | grep " . $pkgent['name']);
        fwrite($fd_log, "ls /var/db/pkg | grep " . $pkgent['name'] . "\n" . $status);
        if($status <> "") {
                    update_status("Package installation completed.");
                    fwrite($fd_log, "Package installation completed.\n");
                    log_error("Package " . $pkgent['name'] . " installation completed okay.");
        } else {
                    update_status("Package WAS NOT installed properly.");
                    fwrite($fd_log, "Package WAS NOT installed properly.\n");
                    log_error("Package " . $pkgent['name'] . " did not install correctly.");
        }
    } else {
        update_status("Package installation completed.");
        fwrite($fd_log, "Package installation completed.\n");
    }

    update_progress_bar(100);

}

// close log
fclose($fd_log);

echo "<p><center>Installation completed.  Show <a href=\"pkg_mgr_install.php?showlog=true\">install log</a></center>";

echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';</script>";
echo "\n<script language=\"JavaScript\">document.progholder.style.visibility='hidden';</script>";

function add_text_to_file($file, $text) {
    global $fd_log;
    fwrite($fd_log, "Adding needed text items:\n");
    $filecontents = exec_command_and_return_text("cat " . $file);
    $filecontents = str_replace($text, "", $filecontents);
    $text = $filecontents . $text;
    fwrite($fd_log, $text . "\n");
    $fd = fopen($file, "w");
    fwrite($fd, $text . "\n");
    fclose($fd);
}

function get_filename_from_url($url) {
            $filenamesplit = split("/", $url);
            foreach($filenamesplit as $fn) $filename = $fn;
            return $filename;
}

?>









