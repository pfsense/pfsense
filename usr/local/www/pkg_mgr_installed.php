<?php
/* $Id$ */
/*
    pkg_mgr.php
    Copyright (C) 2004-2010 Scott Ullrich <sullrich@gmail.com>
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
	pfSense_MODULE:	pkgs
*/

##|+PRIV
##|*IDENT=page-system-packagemanager-installed
##|*NAME=System: Package Manager: Installed page
##|*DESCR=Allow access to the 'System: Package Manager: Installed' page.
##|*MATCH=pkg_mgr_installed.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pkg-utils.inc");

$timezone = $syscfg['timezone'];
if (!$timezone)
	$timezone = "Etc/UTC";

date_default_timezone_set($timezone);

/* if upgrade in progress, alert user */
if(is_subsystem_dirty('packagelock')) {
	$pgtitle = array(gettext("System"),gettext("Package Manager"));
	include("head.inc");
	echo "<body link=\"#0000CC\" vlink=\"#0000CC\" alink=\"#0000CC\">\n";
	include("fbegin.inc");
	echo "Please wait while packages are reinstalled in the background.";
	include("fend.inc");
	echo "</body>";
	echo "</html>";
	exit;
}

if(is_array($config['installedpackages']['package'])) {
	foreach($config['installedpackages']['package'] as $instpkg) {
		$tocheck[] = $instpkg['name'];
	}
	$currentvers = get_pkg_info($tocheck, array('version', 'xmlver', 'pkginfolink'));
}

$pgtitle = array(gettext("System"),gettext("Package Manager"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<?php include("fbegin.inc"); ?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td>
				<?php
					$version = file_get_contents("/etc/version");
					$tab_array = array();
					$tab_array[] = array(gettext("Available Packages"), false, "pkg_mgr.php");
//					$tab_array[] = array("{$version} " . gettext("packages"), false, "pkg_mgr.php");
//					$tab_array[] = array("Packages for any platform", false, "pkg_mgr.php?ver=none");
//					$tab_array[] = array("Packages for a different platform", $requested_version == "other" ? true : false, "pkg_mgr.php?ver=other");
					$tab_array[] = array(gettext("Installed Packages"), true, "pkg_mgr_installed.php");
					display_top_tabs($tab_array);
				?>
			</td>
		</tr>
		<tr>
			<td>
				<div id="mainarea">
					<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td width="10%" class="listhdrr"><?=gettext("Package Name"); ?></td>
							<td width="20%" class="listhdrr"><?=gettext("Category"); ?></td>
							<td width="10%" class="listhdrr"><?=gettext("Package Info"); ?></td>
							<td width="15%" class="listhdrr"><?=gettext("Package Version"); ?></td>
							<td width="45%" class="listhdr"><?=gettext("Description"); ?></td>
						</tr>
						<?php
							if(is_array($config['installedpackages']['package'])):

								$instpkgs = array();
								foreach($config['installedpackages']['package'] as $instpkg) {
									$instpkgs[] = $instpkg['name'];
								}
								natcasesort($instpkgs);

								foreach ($instpkgs as $index => $pkgname):

									$pkg = $config['installedpackages']['package'][$index];
									if(!$pkg['name'])
										continue;

									$latest_package = $currentvers[$pkg['name']]['version'];
									if ($latest_package) {
										// we're running a newer version of the package
										if(strcmp($pkg['version'], $latest_package) > 0) {
											$tdclass = "listbggrey";
											$pkgver  = gettext("Available") .": ". $latest_package . "<br/>";
											$pkgver .= gettext("Installed") .": ". $pkg['version'];
										}
										// we're running an older version of the package
										if(strcmp($pkg['version'], $latest_package) < 0) {
											$tdclass = "listbg";
											$pkgver  = "<font color='#ffffff'>" . gettext("Available") .": ". $latest_package . "<br/>";
											$pkgver .= gettext("Installed") .": ". $pkg['version'];
										}
										// we're running the current version
										if(!strcmp($pkg['version'], $latest_package)) {
											$tdclass = "listr";
											$pkgver  = $pkg['version'];
										}
									} else {
										// unknown available package version
										$pkgver = "";
										if(!strcmp($pkg['version'], $latest_package)) {
											$tdclass = "listr";
											$pkgver = $pkg['version'];
										}
									}
						?>
						<tr valign="top">
							<td class="listlr">
								<?=$pkg['name'];?>
							</td>
							<td class="listr">
								<?=$pkg['category'];?>
							</td>
							<td class="listr">
							<?php
							if($currentvers[$pkg['name']]['pkginfolink']) {
								$pkginfolink = $currentvers[$pkg['name']]['pkginfolink'];
								echo "<a target='_new' href='$pkginfolink'>" . gettext("Package Info") . "</a>";
							} else {
								echo gettext("No info, check the") . " <a href='http://forum.pfsense.org/index.php/board,15.0.html'>" . gettext("forum") . "</a>";
							}
							?>
							</td>
							<td class="<?=$tdclass;?>">
									<?=$pkgver;?>
							</td>
							<td class="listbg">
									<?=$pkg['descr'];?>
							</td>
							<td valign="middle" class="list" nowrap>
								<a onclick="return confirm('<?=gettext("Do you really want to remove this package?"); ?>')" href="pkg_mgr_install.php?mode=delete&pkg=<?= $pkg['name']; ?>">
									<img title="<?=gettext("Remove this package."); ?>" src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0">
								</a>
								<br>
								<a href="pkg_mgr_install.php?mode=reinstallpkg&pkg=<?= $pkg['name']; ?>">
									<img title="<?=gettext("Reinstall this package."); ?>" src="./themes/<?= $g['theme']; ?>/images/icons/icon_reinstall_pkg.gif" width="17" height="17" border="0">
								</a>
								<a href="pkg_mgr_install.php?mode=reinstallxml&pkg=<?= $pkg['name']; ?>">
									<img title="<?=gettext("Reinstall this package's GUI components."); ?>" src="./themes/<?= $g['theme']; ?>/images/icons/icon_reinstall_xml.gif" width="17" height="17" border="0">
								</a>
							</td>
						</tr>
						<?php
								endforeach;
							else:
						 ?>
						<tr>
							<td colspan="5" align="center">
								<?=gettext("There are no packages currently installed."); ?>
							</td>
						</tr>
						<?php endif; ?>
					</table>
				</div>
			</td>
		</tr>
	</table>
<?php include("fend.inc"); ?>
</body>
</html>
