<?php
/*
	pkg_mgr_installed.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */
/*
	pfSense_MODULE: pkgs
*/

##|+PRIV
##|*IDENT=page-system-packagemanager-installed
##|*NAME=System: Package Manager: Installed page
##|*DESCR=Allow access to the 'System: Package Manager: Installed' page.
##|*MATCH=pkg_mgr_installed.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pkg-utils.inc");

/* if upgrade in progress, alert user */
if (is_subsystem_dirty('packagelock')) {
	$pgtitle = array(gettext("System"), gettext("Package Manager"));
	include("head.inc");
	print_info_box_np("Please wait while packages are reinstalled in the background.");
	include("foot.inc");
	exit;
}

include("head.inc");

$closehead = false;
$pgtitle = array(gettext("System"), gettext("Package Manager"));

$tab_array = array();
$tab_array[] = array(gettext("Available Packages"), false, "pkg_mgr.php");
$tab_array[] = array(gettext("Installed Packages"), true, "pkg_mgr_installed.php");
display_top_tabs($tab_array);

$installed_packages = array();
$package_list = get_pkg_info();
foreach ($package_list as $pkg) {
	if (!isset($pkg['installed'])) {
		continue;
	}
	$installed_packages[] = $pkg;
}

if(empty($installed_packages)):?>
	<div class="alert alert-warning">
		<?=gettext("There are no packages currently installed.")?>
	</div>
<?php else: ?>
	<div class="panel panel-body">
	<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
	<thead>
		<tr>
			<th><!-- Status icon --></th>
			<th><?=gettext("Name")?></th>
			<th><?=gettext("Category")?></th>
			<th><?=gettext("Version")?></th>
			<th><?=gettext("Description")?></th>
			<th><?=gettext("Actions")?></th>
		</tr>
	</thead>
	<tbody>
<?php
	foreach ($installed_packages as $pkg):
		if(!$pkg['name']) {
			continue;
		}

		$shortname = $pkg['name'];
		pkg_remove_prefix($shortname);

		// XXX: Add it to globals.inc?
		$changeloglink ="https://github.com/pfsense/FreeBSD-ports/commits/devel/{$pkg['categories'][0]}/{$pkg['name']}";
		#check package version
		$txtcolor = "black";
		$upgradeavail = false;
		$vergetstr = "";

		if (isset($pkg['installed_version']) && isset($pkg['version'])) {
			$version_compare = pkg_version_compare($pkg['installed_version'], $pkg['version']);
			if ($version_compare == '>') {
				// we're running a newer version of the package
				$status = 'Newer than available ('. $pkg['version'] .')';
				$statusicon = 'exclamation';
			} else if ($version_compare == '<') {
				// we're running an older version of the package
				$status = 'Upgrade available to '.$pkg['version'];
				$statusicon = 'refresh';
				$txtcolor = "blue";
				$upgradeavail = true;
				$vergetstr = '&amp;from=' . $pkg['installed_version'] . '&amp;to=' . $pkg['version'];
			} else if ($version_compare == '=') {
				// we're running the current version
				$status = 'Up-to-date';
				$statusicon = 'ok';
			} else {
				$status = 'Error comparing version';
				$statusicon = 'exclamation';
			}
		} else {
			// unknown available package version
			$status = 'Unknown';
			$statusicon = 'question';
			$pkgdescr = $pkg['descr'];
			$pkgwww = 'UNKNOWN';
		}
?>
	<tr>
		<td>
<?php if($upgradeavail) { ?>
			<a title="<?=$status?>" href="pkg_mgr_install.php?mode=reinstallpkg&amp;pkg=<?=$pkg['name']?><?=$vergetstr?>" class="icon-large icon-refresh"></a>
<?php } else { ?>
			<i title="<?=$status?>" class="icon-large icon-ok"></i>
<?php } ?>
		</td>
		<td>
			<font color="<?=$txtcolor?>"><?=$shortname?></font>
		</td>
		<td>
			<?=implode(" ", $pkg['categories'])?>
		</td>
		<td>
<?php if (!$g['disablepackagehistory']):?>
			<a target="_blank" title="<?=gettext("View changelog")?>" href="<?=htmlspecialchars($changeloglink)?>">
<?php endif;?>
				<?=htmlspecialchars($pkg['installed_version'])?>
<?php if (!$g['disablepackagehistory']):?>
			</a>
<?php endif;?>
		</td>
		<td>
			<?=$pkg['desc']?>
		</td>
		<td>
			<a title="<?=gettext("Remove")?>" href="pkg_mgr_install.php?mode=delete&amp;pkg=<?=$pkg['name']?>" class="icon-large icon-minus-sign"></a>
<?php if($upgradeavail) { ?>
			<a title="<?=gettext("Update")?>" href="pkg_mgr_install.php?mode=reinstallpkg&amp;pkg=<?=$pkg['name']?><?=$vergetstr?>" class="icon-large icon-refresh"></a>
<?php } else { ?>
			<a title="<?=gettext("Reinstall")?>" href="pkg_mgr_install.php?mode=reinstallpkg&amp;pkg=<?=$pkg['name']?>" class="icon-large icon-retweet"></a>
<?php } ?>

<?php if(!isset($g['disablepackageinfo']) && $pkg['www'] != 'UNKNOWN'):?>
			<a target="_blank" title="<?=gettext("View more information")?>" href="<?=htmlspecialchars($pkg['www'])?>" class="icon-large icon-info-sign"></a>
<?php endif; ?>
		</td>
	</tr>
<?php endforeach;?>
	</tbody>
</table>
</div>
</div>
<br />
<div style="text-align: center;">
	<span>
		<i class="icon-large icon-refresh"></i> = Update, &nbsp;
		<i class="icon-large icon-ok"></i> = Current, &nbsp;
		<i class="icon-large icon-minus-sign"></i> = Remove, &nbsp;
		<i class="icon-large icon-info-sign"></i> = Information, &nbsp;
		<i class="icon-large icon-retweet"></i> = Reinstall.
		<br />
		<font color="blue"><?=gettext("Blue package name")?></font> = <?=gettext("Newer version available")?>
	</span>
</div>

<?php endif; ?>
<?php include("foot.inc")?>
