<?php
/* $Id$ */
/*
    pkg_mgr_install.php
    part of pfSense (http://www.pfSense.com)
    Copyright (C) 2004-2010 Scott Ullrich <sullrich@gmail.com>
 	Copyright (C) 2005 Colin Smith
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
/*
	pfSense_BUILDER_BINARIES:	/bin/rm
	pfSense_MODULE:	pkgs
*/

##|+PRIV
##|*IDENT=page-system-packagemanager-installpackage
##|*NAME=System: Package Manager: Install Package page
##|*DESCR=Allow access to the 'System: Package Manager: Install Package' page.
##|*MATCH=pkg_mgr_install.php*
##|-PRIV

ini_set('max_execution_time', '0');

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("pkg-utils.inc");

$static_output = "";
$static_status = "";
$sendto = "output";

$todo = array();

$pgtitle = array(gettext("System"),gettext("Package Manager"),gettext("Install Package"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
	<form action="pkg_mgr_install.php" method="post">
		<div id="mainareapkg">
			<table width="100%" border="0" cellpadding="0" cellspacing="0">
				<tr>
					<td>
						<?php
//							$version = file_get_contents("/etc/version");
							$tab_array = array();
							$tab_array[] = array(gettext("Available packages"), false, "pkg_mgr.php");
//							$tab_array[] = array("Packages for any platform", false, "pkg_mgr.php?ver=none");
//							$tab_array[] = array("Packages for a different platform", $requested_version == "other" ? true : false, "pkg_mgr.php?ver=other");
							$tab_array[] = array(gettext("Installed packages"), false, "pkg_mgr_installed.php");
							$tab_array[] = array(gettext("Package Installer"), true, "");
							display_top_tabs($tab_array);
						?>
					</td>
				</tr>
				<tr>
					<td class="tabcont">
						<center>
							<table height='15' width='420' border='0' colspacing='0' cellpadding='0' cellspacing='0'>
								<tr>
									<td background="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" height='15' width='5'>
									</td>
									<td>
										<table id="progholder" name="progholder" height='15' width='410' border='0' colspacing='0' cellpadding='0' cellspacing='0'>
											<td background="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif" valign="top" align="left">
												<img src='./themes/<?= $g['theme']; ?>/images/misc/bar_blue.gif' width='0' height='15' name='progressbar' id='progressbar'>
											</td>
										</table>
									</td>
									<td background="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" height='15' width='5'>
									</td>
								</tr>
							</table>
							<br>
							<!-- status box -->
							<textarea cols="80" rows="1" name="status" id="status" wrap="hard"><?=gettext("Beginning package installation.");?></textarea>
							<!-- command output box -->
							<textarea cols="80" rows="35" name="output" id="output" wrap="hard"></textarea>
						</center>
					</td>
				</tr>
			</table>
		</div>
	</form>
<?php include("fend.inc"); ?>
<script type="text/javascript">
NiftyCheck();
Rounded("div#mainareapkg","bl br","#FFF","#eeeeee","smooth");
</script>
</body>
</html>


<?php

ob_flush();

switch($_GET['mode']) {
	case "showlog":
	case "installedinfo":
		/* These cases do not make changes. */
		$fs_mounted_rw = false;
		break;
	default:
		/* All other cases make changes, so mount rw fs */
		conf_mount_rw();
		$fs_mounted_rw = true;
		/* Write out configuration to create a backup prior to pkg install. */
		write_config(gettext("Creating restore point before package installation."));
		break;
}

switch($_GET['mode']) {
	case "delete":
		uninstall_package($_GET['pkg']);
		update_status(gettext("Package deleted."));
		$static_output .= "\n" . gettext("Package deleted.");
		update_output_window($static_output);
		filter_configure();
		break;
	case "showlog":
		$id = htmlspecialchars($_GET['pkg']);
		if(strpos($id, "."))
			exit;
		update_output_window(file_get_contents("/tmp/pkg_mgr_{$id}.log"));
		break;
	case "reinstallxml":
	case "reinstallpkg":
		delete_package_xml(htmlspecialchars($_GET['pkg']));
		if (install_package(htmlspecialchars($_GET['pkg'])) < 0) {
			update_status(gettext("Package reinstallation failed."));
			$static_output .= "\n" . gettext("Package reinstallation failed.");
			update_output_window($static_output);
		} else {
			update_status(gettext("Package reinstalled."));
			$static_output .= "\n" . gettext("Package reinstalled.");
			update_output_window($static_output);
			filter_configure();
		}
		file_put_contents("/tmp/{$_GET['pkg']}.info", $static_output);
		echo "<script type='text/javascript'>document.location=\"pkg_mgr_install.php?mode=installedinfo&pkg={$_GET['pkg']}\";</script>";
		break;
	case "installedinfo":
		if(file_exists("/tmp/{$_GET['pkg']}.info")) {
			$filename = escapeshellcmd("/tmp/" . $_GET['pkg']  . ".info");
			$status = file_get_contents($filename);
			update_status($_GET['pkg']  . " " . gettext("installation completed."));
			update_output_window($status);
		} else
			update_output_window(sprintf(gettext("Could not find %s."), htmlspecialchars($_GET['pkg'])));
		break;
	case "reinstallall":
		if (is_array($config['installedpackages']['package']))
			foreach($config['installedpackages']['package'] as $package)
				$todo[] = array('name' => $package['name'], 'version' => $package['version']);
		$pkg_id = 0;
		foreach($todo as $pkgtodo) {
			$static_output = "";
			if($pkgtodo['name']) {
				update_output_window($static_output);
				uninstall_package($pkgtodo['name']);
				install_package($pkgtodo['name']);
				$pkg_id++;
			}
		}
		update_status(gettext("All packages reinstalled."));
		$static_output .= "\n" . gettext("All packages reinstalled.");
		update_output_window($static_output);
		filter_configure();
		break;
	default:
		$pkgid = htmlspecialchars($_GET['id']);
		$status = install_package($pkgid);
		if($status == -1) {
			update_status(gettext("Installation of") . " {$pkgid} " . gettext("FAILED!"));
			$static_output .= "\n" . gettext("Installation halted.");
			update_output_window($static_output);
		} else {
			$status_a = gettext("Installation of") . " {$pkgid} " . gettext("completed.");
			update_status($status_a);
			$status = get_after_install_info($pkgid);
			if($status) 
				$static_output .= "\n" . gettext("Installation completed.") . "\n{$pkgid} " . gettext("setup instructions") . ":\n{$status}";
			else
				$static_output .= "\n" . gettext("Installation completed.   Please check to make sure that the package is configured from the respective menu then start the package.");
		file_put_contents("/tmp/{$pkgid}.info", $static_output);
		echo "<script type='text/javascript'>document.location=\"pkg_mgr_install.php?mode=installedinfo&pkg={$pkgid}\";</script>";
		}
		filter_configure();
		break;
}

// Delete all temporary package tarballs and staging areas.
unlink_if_exists("/tmp/apkg_*");
rmdir_recursive("/var/tmp/instmp*");

// close log
if($fd_log)
        fclose($fd_log);

if($fs_mounted_rw) {
	/* Restore to read only fs */
	conf_mount_ro();
}
?>
