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

$pkg_info = get_pkg_info('all', array('noembedded', 'name', 'category', 'website', 'version', 'status', 'descr', 'maintainer', 'required_version', 'maximum_version', 'pkginfolink'));
if($pkg_info) {
	$fout = fopen("{$g['tmp_path']}/pkg_info.cache", "w");
	fwrite($fout, serialize($pkg_info));
	fclose($fout);
	//$pkg_sizes = get_pkg_sizes();
} else {
	$using_cache = true;
	$xmlrpc_base_url = isset($config['system']['altpkgrepo']['enable']) ? $config['system']['altpkgrepo']['xmlrpcbaseurl'] : $g['xmlrpcbaseurl'];
	if(file_exists("{$g['tmp_path']}/pkg_info.cache")) {
		$savemsg = "Unable to retrieve package info from {$xmlrpc_base_url}. Cached data will be used.";
		$pkg_info = unserialize(@file_get_contents("{$g['tmp_path']}/pkg_info.cache"));
	} else {
		$savemsg = "Unable to communicate with {$xmlrpc_base_url}. Please verify DNS and interface configuration, and that {$g['product_name']} has functional Internet connectivity.";
	}
}

if (! empty($_GET))
	if (isset($_GET['ver']))
		$requested_version = htmlspecialchars($_GET['ver']);

$pgtitle = array("System","Package Manager");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
	include("fbegin.inc");
	if ($savemsg)
		print_info_box($savemsg);
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
		<?php
			$version = file_get_contents("/etc/version");
			$dash = strpos($version, ".");
			$hyphen = strpos($version, "-");
			$major = substr($version, 0, $dash);
			$minor = substr($version, $dash + 1, $hyphen - $dash - 1);
			$testing_version = substr($version, $hyphen + 1, strlen($version) - $hyphen);

			$tab_array = array();
			$tab_array[] = array("{$version} packages", $requested_version <> "" ? false : true, "pkg_mgr.php");
//			$tab_array[] = array("Packages for any platform", $requested_version == "none" ? true : false, "pkg_mgr.php?ver=none");
//			$tab_array[] = array("Packages with a different version", $requested_version == "other" ? true : false, "pkg_mgr.php?ver=other");
			$tab_array[] = array("Installed Packages", false, "pkg_mgr_installed.php");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
	<tr>
		<td>
			<div id="mainarea">
				<table class="tabcont sortable" width="100%" border="0" cellpadding="6" cellspacing="0">
					<tr>
						<td width="10%" class="listhdrr">Package Name</td>
						<td width="25%" class="listhdrr">Category</td>
<!--					<td width="10%" class="listhdrr">Size</td>	-->
						<td width="5%" class="listhdrr">Status</td>
						<td width="5%" class="listhdrr">Package Info</td>
						<td width="50%" class="listhdr">Description</td>
					</tr>
					<?php
						if(!$pkg_info) {
							echo "<tr><td colspan=\"5\"><center>There are currently no packages available for installation.</td></tr>";
						} else {
							$installed_pfsense_version = rtrim(file_get_contents("/etc/version"));
							$dash = strpos($installed_pfsense_version, "-");
							$installed_pfsense_version = substr($installed_pfsense_version, 0, $dash);
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
									$dash = strpos($index['required_version'], "-");
									$index['major_version'] = substr($index['required_version'], 0, $dash);
									if ($version <> "HEAD" &&
										$index['required_version'] == "HEAD" &&
										$requested_version <> "other")
										continue;
									if (empty($index['required_version']) &&
										$requested_version <> "none")
										continue;
									if($index['major_version'] > $major &&
										$requested_version <> "other")
										continue;
									if(isset($index['major_version']) &&
										$requested_version == "none")
										continue;
									if($index['major_version'] == $major &&
										$requested_version == "other")
										continue;
									/* Package is for a newer version, lets skip */
									if($installed_pfsense_version < $index['required_version'])
										continue;
									if($index['maximum_version'])
										if($installed_pfsense_version > $index['maximum_version'])
											continue;
					?>
					<tr valign="top">
						<td class="listlr">
							<A target="_blank" href="<?= $index['website'] ?>"><?= $index['name'] ?></a>
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
						<td class="listr">
							<?= $index['status'] ?>
							<br/>
							<?= $index['version'] ?>
							<br/>
							platform: <?= $index['required_version'] ?>
							<br/>
							<?= $index['maximum_version']; ?>
						</td>
						<td class="listr">
						<?php
						if($index['pkginfolink']) {
							$pkginfolink = $index['pkginfolink'];
							echo "<a target='_new' href='$pkginfolink'>Package Info</a>";
						} else {
							echo "No info, check the <a href='http://forum.pfsense.org/index.php/board,15.0.html'>forum</a>";
						}
						?>
						</td>
						<td class="listbg" class="listbg" style="overflow: hidden;">
							<?= $index['descr'] ?>
						</td>
						<td valign="middle" class="list" nowrap>
							<a onclick="return confirm('Do you really want to install this package?')" href="pkg_mgr_install.php?id=<?=$index['name'];?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a>
						</td>
					</tr>
					<?php
								}
							} else {
								echo '<tr><td colspan="5"><center>There are currently no packages available for installation.</center></td></tr>';
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
