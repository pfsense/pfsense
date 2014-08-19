<?php
/* $Id$ */
/*
    pkg_mgr.php
    Copyright (C) 2004-2012 Scott Ullrich <sullrich@gmail.com>
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

$timezone = $config['system']['timezone'];
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

function domTT_title($title_msg, $return="echo"){
	if (!empty($title_msg)){
		$title_msg=preg_replace("/\s+/"," ",$title_msg);
        $title_msg=preg_replace("/'/","\'",$title_msg);
        $title= "onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\" onmouseover=\"domTT_activate(this, event, 'content', '{$title_msg}', 'trail', true, 'delay', 0, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle');\"";
        if ($return =="echo")
		 echo $title;
		else
		return $title;	
	}
}
if(is_array($config['installedpackages']['package'])) {
	foreach($config['installedpackages']['package'] as $instpkg) {
		$tocheck[] = $instpkg['name'];
	}
	$currentvers = get_pkg_info($tocheck, array('version', 'xmlver', 'pkginfolink','descr'));
}
$closehead = false;
$pgtitle = array(gettext("System"),gettext("Package Manager"));
include("head.inc");

?>
<script type="text/javascript" src="javascript/domTT/domLib.js"></script>
<script type="text/javascript" src="javascript/domTT/domTT.js"></script>
<script type="text/javascript" src="javascript/domTT/behaviour.js"></script>
<script type="text/javascript" src="javascript/domTT/fadomatic.js"></script>
<script type="text/javascript" src="/javascript/row_helper_dynamic.js"></script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<?php include("fbegin.inc"); 

	/* Print package server mismatch warning. See https://redmine.pfsense.org/issues/484 */
	if (!verify_all_package_servers())
		print_info_box(package_server_mismatch_message());

	/* Print package server SSL warning. See https://redmine.pfsense.org/issues/484 */
	if (check_package_server_ssl() === false)
		print_info_box(package_server_ssl_failure_message()); ?>

	<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="packages installed">
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
					<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
						<tr>
							<td width="12%" class="listhdrr"><?=gettext("Name"); ?></td>
							<td width="16%" class="listhdrr"><?=gettext("Category"); ?></td>
							<td width="15%" class="listhdrr"><?=gettext("Version"); ?></td>
							<td width="53%" class="listhdr"><?=gettext("Description"); ?></td>
							<td width="40">&nbsp;</td>
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

									// get history/changelog git dir
									$commit_dir=explode("/",$pkg['config_file']);
									$changeloglink ="https://github.com/pfsense/pfsense-packages/commits/master/config/".$commit_dir[(count($commit_dir)-2)]; 
									#check package version
									$latest_package = $currentvers[$pkg['name']]['version'];
									if ($latest_package) {
										// we're running a newer version of the package
										if(strcmp($pkg['version'], $latest_package) > 0) {
											$tdclass = "listbggrey";
											if ($g['disablepackagehistory'])
												$pkgver  = "<a>".gettext("Available") .": ". $latest_package . "<br />";
											else
												$pkgver  = "<a target='_blank' href='$changeloglink'>".gettext("Available") .": ". $latest_package . "<br />";
											$pkgver .= gettext("Installed") .": ". $pkg['version']. "</a>";
										}
										// we're running an older version of the package
										if(strcmp($pkg['version'], $latest_package) < 0) {
											$tdclass = "listbg";
											if ($g['disablepackagehistory'])
												$pkgver  = "<a><font color='#ffffff'>" . gettext("Available") .": ". $latest_package . "</font><br />";
											else
												$pkgver  = "<a target='_blank' href='$changeloglink'><font color='#ffffff'>" . gettext("Available") .": ". $latest_package . "<br />";
											$pkgver .= gettext("Installed") .": ". $pkg['version']."</font></a>";
										}
										// we're running the current version
										if(!strcmp($pkg['version'], $latest_package)) {
											$tdclass = "listr";
											if ($g['disablepackagehistory'])
												$pkgver = "<a>{$pkg['version']}</a>";
											else
												$pkgver = "<a target='_blank' href='$changeloglink'>{$pkg['version']}</a>";
										}
									} else {
										// unknown available package version
										$pkgver = "";
										if(!strcmp($pkg['version'], $latest_package)) {
											$tdclass = "listr";
											if ($g['disablepackagehistory'])
												$pkgver = "<a>{$pkg['version']}</a>";
											else
												$pkgver = "<a target='_blank' href='$changeloglink'>{$pkg['version']}</a>";
											}
									}
									/* Check package info link */
									if($pkg['pkginfolink']){
										$pkginfolink = $pkg['pkginfolink'];
										$pkginfo=gettext("Package info");
										}
									else{
										$pkginfolink = "https://forum.pfsense.org/index.php/board,15.0.html";
										$pkginfo=gettext("No package info, check the forum");
										}
									
						?>
						<tr valign="top">
							<td class="listlr">
								<?=$pkg['name'];?>
							</td>
							<td class="listr">
								<?=$pkg['category'];?>
							</td>
							<?php 
							if (isset($g['disablepackagehistory']))
									echo "<td class='{$tdclass}'>{$pkgver}</td>";
							else
									echo "<td class='{$tdclass}' ".domTT_title(gettext("Click on ".ucfirst($pkg['name'])." version to check its change log."),"return").">{$pkgver}</td>";
							?>
							<td class="listbg" style="overflow:hidden; text-align:justify;" <?=domTT_title(gettext("Click package info for more details about ".ucfirst($pkg['name'])." package."))?>>
									<?=$currentvers[$pkg['name']]['descr'];?>
							<?php if (! $g['disablepackageinfo']): ?>
							<br /><br />
							<a target='_blank' href='<?=$pkginfolink?>' style='align:center;color:#ffffff; filter:Glow(color=#ff0000, strength=12);'><?=$pkginfo?></a>
							<?php endif; ?>
							</td>
							<td valign="middle" class="list nowrap">
								<a href="pkg_mgr_install.php?mode=delete&amp;pkg=<?= $pkg['name']; ?>">
									<img <?=domTT_title(gettext("Remove ".ucfirst($pkg['name'])." package."))?> src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="delete" />
								</a>
								<br />
								<a href="pkg_mgr_install.php?mode=reinstallpkg&amp;pkg=<?= $pkg['name']; ?>">
									<img <?=domTT_title(gettext("Reinstall ".ucfirst($pkg['name'])." package."));?> src="./themes/<?= $g['theme']; ?>/images/icons/icon_reinstall_pkg.gif" width="17" height="17" border="0" alt="reinstall" />
								</a>
								<a href="pkg_mgr_install.php?mode=reinstallxml&amp;pkg=<?= $pkg['name']; ?>">
									<img <?=domTT_title(gettext("Reinstall ".ucfirst($pkg['name'])."'s GUI components."));?> src="./themes/<?= $g['theme']; ?>/images/icons/icon_reinstall_xml.gif" width="17" height="17" border="0" alt="reinstall" />
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
