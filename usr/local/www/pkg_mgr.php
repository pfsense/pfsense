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
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig
	pfSense_MODULE:	pkgs
*/

##|+PRIV
##|*IDENT=page-system-packagemanager
##|*NAME=System: Package Manager page
##|*DESCR=Allow access to the 'System: Package Manager' page.
##|*MATCH=pkg_mgr.php*
##|-PRIV

ini_set('max_execution_time', '0');

require_once("globals.inc");
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
function domTT_title($title_msg){
	if (!empty($title_msg)){
		$title_msg=preg_replace("/\s+/"," ",$title_msg);
        $title_msg=preg_replace("/'/","\'",$title_msg);
		echo "onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\" onmouseover=\"domTT_activate(this, event, 'content', '{$title_msg}', 'trail', true, 'delay', 0, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle');\"";
	}
}
$pkg_info = get_pkg_info('all', array("noembedded", "name", "category", "website", "version", "status", "descr", "maintainer", "required_version", "maximum_version", "pkginfolink", "config_file"));
if($pkg_info) {
	$fout = fopen("{$g['tmp_path']}/pkg_info.cache", "w");
	fwrite($fout, serialize($pkg_info));
	fclose($fout);
	//$pkg_sizes = get_pkg_sizes();
} else {
	$using_cache = true;
	$xmlrpc_base_url = isset($config['system']['altpkgrepo']['enable']) ? $config['system']['altpkgrepo']['xmlrpcbaseurl'] : $g['xmlrpcbaseurl'];
	if(file_exists("{$g['tmp_path']}/pkg_info.cache")) {
		$savemsg = sprintf(gettext("Unable to retrieve package info from %s. Cached data will be used."), $xmlrpc_base_url);
		$pkg_info = unserialize(@file_get_contents("{$g['tmp_path']}/pkg_info.cache"));
	} else {
		$savemsg = sprintf(gettext('Unable to communicate with %1$s. Please verify DNS and interface configuration, and that %2$s has functional Internet connectivity.'), $xmlrpc_base_url, $g['product_name']);
	}
}

if (! empty($_GET))
	if (isset($_GET['ver']))
		$requested_version = htmlspecialchars($_GET['ver']);

$closehead = false;
$pgtitle = array(gettext("System"),gettext("Package Manager"));
include("head.inc");

?>
<script type="text/javascript" src="javascript/domTT/domLib.js"></script>
<script type="text/javascript" src="javascript/domTT/domTT.js"></script>
<script type="text/javascript" src="javascript/domTT/behaviour.js"></script>
<script type="text/javascript" src="javascript/domTT/fadomatic.js"></script>
<script type="text/javascript" language="javascript" src="/javascript/row_helper_dynamic.js"></script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
	include("fbegin.inc");
	if ($savemsg)
		print_info_box($savemsg);
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="package manager">
	<tr>
		<td>
		<?php
			$version = rtrim(file_get_contents("/etc/version"));

			$tab_array = array();
			$tab_array[] = array(gettext("Available Packages"), $requested_version <> "" ? false : true, "pkg_mgr.php");
//			$tab_array[] = array($version . gettext("packages"), $requested_version <> "" ? false : true, "pkg_mgr.php");
//			$tab_array[] = array("Packages for any platform", $requested_version == "none" ? true : false, "pkg_mgr.php?ver=none");
//			$tab_array[] = array("Packages with a different version", $requested_version == "other" ? true : false, "pkg_mgr.php?ver=other");
			$tab_array[] = array(gettext("Installed Packages"), false, "pkg_mgr_installed.php");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
	<tr>
		<td>
			<div id="mainarea">
				<table class="tabcont sortable" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
					<tr>
						<td width="12%" class="listhdrr"><?=gettext("Name"); ?></td>
						<td width="18%" class="listhdrr"><?=gettext("Category"); ?></td>
						<td width="15%" class="listhdrr"><?=gettext("Status"); ?></td>
						<td width="53%" class="listhdr"><?=gettext("Description"); ?></td>
						<td width="17">&nbsp;</td>
					</tr>
					<?php
						if(!$pkg_info) {
							echo "<tr><td colspan=\"5\"><center>" . gettext("There are currently no packages available for installation.") . "</td></tr>";
						} else {
							$pkgs = array();
							$instpkgs = array();
							if($config['installedpackages']['package'] != "")
								foreach($config['installedpackages']['package'] as $instpkg) $instpkgs[] = $instpkg['name'];
									$pkg_names = array_keys($pkg_info);
							$pkg_keys = array();
							
							foreach($pkg_names as $name)
								if(!in_array($name, $instpkgs)) $pkg_keys[] = $name;
							$pkg_keys = msort($pkg_keys);
							if(count($pkg_keys) != 0) {
								foreach($pkg_keys as $key) {
									$index = &$pkg_info[$key];
									if(in_array($index['name'], $instpkgs)) 
										continue;
									if($g['platform'] == "nanobsd")
										if($index['noembedded']) 
											continue;
									/* If we are on not on HEAD, and the package wants it, skip */
									if ($version <> "HEAD" &&
										$index['required_version'] == "HEAD" &&
										$requested_version <> "other")
										continue;
									/* If there is no required version, and the requested package 
										version is not 'none', then skip */
									if (empty($index['required_version']) &&
										$requested_version <> "none")
										continue;
									/* If the requested version is not 'other', and the required version is newer than what we have, skip. */
									if($requested_version <> "other" &&
										(pfs_version_compare("", $version, $index['required_version']) < 0))
										continue;
									/* If the requestion version is 'other' and we are on the version requested, skip. */
									if($requested_version == "other" &&
										(pfs_version_compare("", $version, $index['required_version']) == 0))
										continue;
									/* Package is only for an older version, lets skip */
									if($index['maximum_version'] &&
										(pfs_version_compare("", $version, $index['maximum_version']) > 0))
										continue;
									/* get history/changelog git dir */
									$commit_dir=explode("/",$index['config_file']);
									$changeloglink ="https://github.com/pfsense/pfsense-packages/commits/master/config/".$commit_dir[(count($commit_dir)-2)];
									/* Check package info link */
									if($index['pkginfolink']){
										$pkginfolink = $index['pkginfolink'];
										$pkginfo=gettext("Package info");
										}
									else{
										$pkginfolink = "https://forum.pfsense.org/index.php/board,15.0.html";
										$pkginfo=gettext("No package info, check the forum");
										}
					?>
					<tr valign="top">
						<td class="listlr" <?=domTT_title(gettext("Click on package name to access its website."))?>>
							<a target="_blank" href="<?= $index['website'] ?>"><?= $index['name'] ?></a>
						</td>
						<td class="listr">
							<?= $index['category'] ?>
						</td>
						<?php
							/*
							if(!$using_cache) {
								$size = get_package_install_size($index['name'], $pkg_sizes);
								$size = squash_from_bytes($size[$index['name']], 1);
							}
							if(!$size)
								$size = "Unknown.";
							*/
						?>
<!--
						<td class="listr">
							<?= $size ?>
						</td>
-->
						<?php if ($g['disablepackagehistory']){?>
							<td	class="listr">
						<?php }else{?>
							<td class="listr" <?=domTT_title(gettext("Click ".ucfirst($index['name'])." version to check its change log."))?>>
						<?php }?>
						<?=$index['status'] ?>
						<br/>
						<?php
						if ($g['disablepackagehistory'])
							echo"<a>{$index['version']}</a>";
						else
							echo "<a target='_blank' href='{$changeloglink}'>{$index['version']}</a>";
						?>
						<br/>
						<?=gettext("platform") .": ". $index['required_version'] ?>
						<br/>
						<?=$index['maximum_version'] ?>
						</td>
						<td class="listbg" style="overflow:hidden; text-align:justify;" <?=domTT_title(gettext("Click package info for more details about ".ucfirst($index['name'])." package."))?>>
						<?= $index['descr'] ?>
						<?php if (! $g['disablepackageinfo']): ?>
						<br/><br/>
						<a target='_blank' href='<?=$pkginfolink?>' style='align:center;color:#ffffff; filter:Glow(color=#ff0000, strength=12);'><?=$pkginfo?></a>
						<?php endif; ?>
						</td>
						<td valign="middle" class="list nowrap" width="17">
							<a href="pkg_mgr_install.php?id=<?=$index['name'];?>"><img <?=domTT_title(gettext("Install ".ucfirst($index['name'])." package."))?> src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" /></a>
						</td>
					</tr>
					<?php
								}
							} else {
								echo "<tr><td colspan='5' align='center'>" . gettext("There are currently no packages available for installation.") . "</td></tr>";
							}
						}
					?>
				</table>
			</div>
		</td>
	</tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
