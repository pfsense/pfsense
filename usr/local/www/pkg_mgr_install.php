#!/usr/local/bin/php
<?php
/*
    pkg_mgr_install.php
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

/*
 *   open logging facility
 */
$fd_log = fopen("/tmp/pkg_mgr.log", "w");
fwrite($fd_log, "Begin of Package Manager installation session.\n");

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
            while(!feof($fd)) {
                        $tmp .= fread($fd,49);
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
            while(!feof($fd)) {
                        $tmp .= fread($fd,49);
            }
            fclose($fd);
            return $tmp;
}

/*
 *   exec_command_and_return_text: execute command and update output window dynamically
 */
function execute_command_return_output($command) {
    global $fd_log;
    $fd = popen($command . " 2>&1 ", "r");
    echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = \"\";</script>";
    $counter = 0;
    $counter2 = 0;
    while(!feof($fd)) {
	$tmp = fread($fd, 50);
        fwrite($fd_log, $tmp);
	$tmp1 = ereg_replace("\n","\\n", $tmp);
	$text = ereg_replace("\"","'", $tmp1);
	if($lasttext == "..") {
	    $text = "";
	    $lasttext = "";
	    $counter=$counter-2;
	} else {
	    $lasttext .= $text;
	}
	if($counter > 51) {
	    $counter = 0;
	    $extrabreak = "\\n";
	} else {
	    $extrabreak = "";
	    $counter++;
	}
	if($counter2 > 600) {
	    echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = \"\";</script>";
	    $counter2 = 0;
	} else
	    $counter2++;
	echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = this.document.forms[0].output.value + \"" . $text . $extrabreak .  "\"; f('output'); </script>";
    }
    fclose($fd);
}

$a_out = &$pkg_config['packages'];

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("System: Package Manager: Install Package");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">System: Package Manager: Install Package</p>
<form action="firewall_nat_out_load_balancing.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_natconfdirty_path)): ?><p>
<?php print_info_box_np("The Package Manager configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>
<?php
if(!file_exists("/tmp/pkg_config.xml")) {
            mwexec("cd {$g['tmp_path']} && /usr/bin/fetch \"http://www.pfsense.com/packages/pkg_config.xml\" >/dev/null 2>&1 ");
            if(!file_exists("{$g['tmp_path']}/pkg_config.xml")) {
                        print_info_box_np("Could not download pkg_config.xml from pfSense.com.  Check your DNS settings.");
                        die;
            }
}

$pkg_config = parse_xml_config("{$g['tmp_path']}/pkg_config.xml", "pfsensepkgs");

$id = $_GET['id'];

if(!$pkg_config['packages']) {
            print_info_box_np("Could not find any packages in pkg_config.xml");
}
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
	             <textarea cols="100%" rows="1" name="status" id="status" wrap="hard">One moment please... This will take a while!</textarea>
	             <textarea cols="100%" rows="25" name="output" id="output" wrap="hard"></textarea>
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

<?

/* install the package */

// Ensure directories are in place for pkg_add.
mwexec("mkdir /usr/local/www/ext/Services >/dev/null 2>&1");
mwexec("mkdir /usr/local/www/ext/System >/dev/null 2>&1");
mwexec("mkdir /usr/local/www/ext/Interfaces >/dev/null 2>&1");
mwexec("mkdir /usr/local/www/ext/Firewall >/dev/null 2>&1");
mwexec("mkdir /usr/local/www/ext/VPN >/dev/null 2>&1");
mwexec("mkdir /usr/local/www/ext/Status >/dev/null 2>&1");

$a_out = &$pkg_config['packages']['package'];
$pkgent = array();
$pkgent['name'] = $pkg_config['packages']['package'][$id]['name'];
$pkgent['descr'] = $pkg_config['packages']['package'][$id]['descr'];
$pkgent['category'] = $pkg_config['packages']['package'][$id]['category'];
$pkgent['depends_on_package'] = $a_out[$id]['depends_on_package'];
$pkgent['depends_on_package_base'] = $a_out[$id]['depends_on_package_base'];
$pkgent['pfsense_package'] = $a_out[$id]['pfsense_package'];
$pkgent['pfsense_package_base'] = $a_out[$id]['pfsense_package_base'];
$pkgent['configurationfile'] = $a_out[$id]['configurationfile'];
$a_out = &$config['packages']['package'];

fwrite($fd_log, "ls /var/db/pkg | grep " . $pkgent['name'] . "\n" . $status);
if($status <> "") {
            // package is already installed!?
            print_info_box_np("NOTICE! " . $pkgent['name'] . " is already installed!  Installation will be registered.");
}

update_status("Downloading and installing " . $pkgent['name'] . " - " . $pkgent['pfsense_package'] . " and its dependencies ... This could take a moment ...");
fwrite($fd_log, "Downloading and installing " . $pkgent['name'] . " ... \n");

$text = exec_command_and_return_text("cd /tmp/ && /usr/sbin/pkg_add -r " . $pkgent['pfsense_package_base'] . "/" . $pkgent['pfsense_package']);
update_output_window($text);
fwrite($fd_log, "Executing: cd /tmp/ && /usr/sbin/pkg_add -r " . $pkgent['pfsense_package_base'] . "/" . $pkgent['pfsense_package'] . "\n" . $text);

if ($pkgent['pfsense_package_base']) {
            update_status("Downloading and installing " . $pkgent['name'] . " - " . $pkgent['depends_on_package_base'] . " and its dependencies ... This could take a moment ...");
            $text = exec_command_and_return_text("cd /tmp/ && /usr/sbin/pkg_add -r " . $pkgent['depends_on_package_base'] . "/" . $pkgent['depends_on_package']);
            update_output_window($text);
            fwrite($fd_log, "cd /tmp/ && /usr/sbin/pkg_add -r " . $pkgent['depends_on_package_base'] . "/" . $pkgent['depends_on_package'] . "\n" . $text);;
}

$config = parse_xml_config("{$g['conf_path']}/config.xml", $g['xml_rootobj']);

$config['installedpackages']['package'][] = $pkgent;

if (isset($id) && $a_out[$id])
        $a_out[$id] = $pkgent;
else
        $a_out[] = $pkgent;

write_config();

$name = $pkgent['name'];

// parse the config file for this package and install neededtext items.
if(file_exists("/usr/local/pkg/" . $pkgent['name'] . ".xml")) {
            $config = parse_xml_config("/usr/local/pkg/" . $pkgent['name'] . ".xml", "packagegui");
            foreach ($config['modify_system']['item'] as $ms) {
                        if($ms['textneeded']) {
                                  fwrite($fd_log, "Adding needed text items:\n");
                                  $filecontents = exec_command_and_return_text("cat " . $ms['modifyfilename']);
                                  $text = ereg_replace($ms['textneeded'], "", $filecontents);
                                  $text .= $ms['textneeded'];
                                  fwrite($fd_log, $ms['textneeded'] . "\n");
                                  $fd = fopen($ms['modifyfilename'], "w");
                                  fwrite($fd, $text . "\n");
                                  fclose($fd);
                        }
            }
            // install menu item into the ext folder
            fwrite($fd_log, "Adding menu option to " . $config['menu']['section'] . "/" . $config['name'] . ":\n");
            $fd = fopen("/usr/local/www/ext/" . $config['menu']['section'] . "/" . $config['name'] , "w");
            fwrite($fd, "/usr/local/www/pkg.php?xml=" . $config['name'] . "\n");
            fclose($fd);
} else {
            update_output_window("WARNING! /usr/local/pkg/" . $pkgent['name'] . ".xml" . " does not exist!\n");
            fwrite($fd_log, "WARNING! /usr/local/pkg/" . $pkgent['name'] . ".xml" . " does not exist!\n");
}
fwrite($fd_log, "End of Package Manager installation session.\n");

// return dependency list to output later.
$command = "TODELETE=`ls /var/db/pkg | grep " . $name . "` && /usr/sbin/pkg_info -r \$TODELETE | grep Dependency: | cut -d\" \" -f2";
$dependencies = exec_command_and_return_text($command);
fwrite($fd_log, "Installed " . $name . " and the following dependencies:\n" . $dependencies);

$status = exec_command_and_return_text("ls /var/db/pkg | grep " . $pkgent['name']);
fwrite($fd_log, "ls /var/db/pkg | grep " . $pkgent['name'] . "\n" . $status);
if($status <> "") {
            update_status("Package installation completed.");
            fwrite($fd_log, "Package installation completed.\n");
} else {
            update_status("Package WAS NOT installed properly.");
            fwrite($fd_log, "Package WAS NOT installed properly.\n");
}

// close log
fclose($fd_log);

// reopen and read log in
$fd_log = fopen("/tmp/pkg_mgr.log", "r");
$tmp = "";
while(!feof($fd_log)) {
            $tmp .= fread($fd_log,49);
}
fclose($fd_log);
$log = ereg_replace("\n", "\\n", $tmp);
echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = \"" . $log . "\";</script>";

?>









