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

function update_status($status) {
            echo "\n<script language=\"JavaScript\">document.forms[0].status.value=\"" . $status . "\";</script>";
}

function execute_command_return_output($command) {
    $fd = popen($command . " 2>&1 ", "r");
    echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = \"\";</script>";
    $counter = 0;
    $counter2 = 0;
    while(!feof($fd)) {
	$tmp = fread($fd,49);
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
    <li class="tabact">Available Packages</a></li>
    <li class="tabinact"><a href="pkg_mgr_installed.php">Installed Packages</a></li>
  </ul>
  </td></tr>
  <tr>
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
               <tr>
                 <td>
	             <textarea cols="55" rows="1" name="status" id="status" wrap="hard">One moment please... This will take a while!</textarea>
	             <textarea cols="55" rows="25" name="output" id="output" wrap="hard"></textarea>
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

$a_out = &$pkg_config['packages']['package'];
$pkgent = array();
$pkgent['name'] = $pkg_config['packages']['package'][$id]['name'];
$pkgent['descr'] = $pkg_config['packages']['package'][$id]['descr'];
$pkgent['category'] = $pkg_config['packages']['package'][$id]['category'];
$pkgent['depends_on_package'] = $a_out[$id]['depends_on_package'];
$pkgent['depends_on_package_base'] = $a_out[$id]['depends_on_package_base'];
$pkgent['pfsense_package'] = $a_out[$id]['pfsense_package'];
$pkgent['pfsense_package_base'] = $a_out[$id]['pfsense_package_base'];
$a_out = &$config['packages']['package'];

update_status("Downloading and installing " . $pkgent['name'] . " ... ");

execute_command_return_output("cd /tmp/ && /usr/sbin/pkg_add -r " . $pkgent['pfsense_package_base'] . "/" . $pkgent['pfsense_package']);

if ($pkgent['pfsense_package_base'])
            execute_command_return_output("cd /tmp/ && /usr/sbin/pkg_add -r " . $pkgent['depends_on_package_base'] . "/" . $pkgent['depends_on_package']);

// XXX: ensure package is REALLY installed before doing below...

$config = parse_xml_config("{$g['conf_path']}/config.xml", $g['xml_rootobj']);

$config['installedpackages']['package'][] = $pkgent;

if (isset($id) && $a_out[$id])
        $a_out[$id] = $pkgent;
else
        $a_out[] = $pkgent;

write_config();

update_status("Package installation completed.");
?>









