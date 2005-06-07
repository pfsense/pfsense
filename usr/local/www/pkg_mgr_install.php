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

require_once("guiconfig.inc");
require_once("xmlparse_pkg.inc");
require_once("pkg-utils.inc");

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
		   <center>
			<table height='15' width='420' border='0' colspacing='0' cellpadding='0' cellspacing='0'>
				<tr>
					<td background="bar_left.gif" height='15' width='5'>
					</td>
					<td>
						<table id="progholder" name="progholder" height='15' width='410' border='0' colspacing='0' cellpadding='0' cellspacing='0'><td background="bar_gray.gif" valign="top" align="left"><img src='bar_blue.gif' width='0' height='15' name='progressbar' id='progressbar'></td></table>
					</td>
					<td background="bar_right.gif" height='15' width='5'>
					</td>
				</tr>
			</table>
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
switch($_GET['mode']) {
	case "delete":
		delete_package($_GET['pkg'] . '-' . $_GET['version']);
		delete_package_xml($_GET['pkg']);
		update_status("Package deleted.");
		$static_output .= "\nPackage deleted.";
		update_output_window($static_output);
		break;
	case "reinstallpkg":
		delete_package($_GET['pkg'] . '-' . $_GET['version']);
	        delete_package_xml($_GET['pkg']);
		install_package($_GET['pkg']);
		update_status("Package reinstalled.");
		$static_output .= "\n\nPackage reinstalled.";
		update_output_window($static_output);
		break;
	case "reinstallxml":
		delete_package_xml($_GET['pkg']);
		install_package($_GET['pkg']);
		$static_output .= "\n\nPackage reinstalled.";
		update_output_window($static_output);
		break;
	case "reinstallall":
		foreach($config['installedpackages']['package'] as $package) {
                	$todo[] = array('name' => $package['name'], 'version' => $package['version']);
        	}
        	foreach($todo as $pkgtodo) {
                	$static_output = "";
                	update_output_window($static_output);
                	delete_package($pkgtodo['name'] . '-' . $pkgtodo['version']);
                	delete_package_xml($pkgtodo['name']);
                	install_package($pkgtodo['name']);
        	}
        	update_status("All packages reinstalled.");
        	$static_output .= "\n\nAll packages reinstalled.";
        	update_output_window($static_output);
        	break;
	default:
		install_package($_GET['id']);
        	update_status("Installation of {$_GET['id']} completed.");
        	$static_output .= "\n\nInstallation completed.";
        	update_output_window($static_output);
}
// Delete all temporary package tarballs and staging areas.
unlink_if_exists("/tmp/apkg_*");
rmdir_recursive("/var/tmp/instmp*");

// close log
fclose($fd_log);

?>
