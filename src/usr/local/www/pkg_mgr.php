<?php
/* $Id$ */
/*
	pkg_mgr.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2004, 2005 Scott Ullrich
 *	Copyright (c)  2013 Marcello Coutinho
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
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig
	pfSense_MODULE: pkgs
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

/* if upgrade in progress, alert user */
if(is_subsystem_dirty('packagelock')) {
	$pgtitle = array(gettext("System"),gettext("Package Manager"));
	include("head.inc");
	print_info_box_np("Please wait while packages are reinstalled in the background.");
	include("foot.inc");
	exit;
}

include("head.inc");

$pkg_info = get_pkg_info();

$pgtitle = array(gettext("System"),gettext("Package Manager"));

$tab_array = array();
$tab_array[] = array(gettext("Available Packages"), true, "pkg_mgr.php");
$tab_array[] = array(gettext("Installed Packages"), false, "pkg_mgr_installed.php");
display_top_tabs($tab_array);

if($pkg_info) {
	//Check categories
	$categories=array();
	foreach ($pkg_info as $pkg_data) {
		if (isset($pkg_data['categories'][0])) {
			$categories[$pkg_data['categories'][0]]++;
		}
	}

	ksort($categories, SORT_STRING|SORT_FLAG_CASE);
	$cm_count=0;
	$tab_array = array();
	$visible_categories=array();
	$categories_min_count=($g['pkg_categories_min_count'] ? $g['pkg_categories_min_count'] : 3);
	$categories_max_display=($g['pkg_categories_max_display'] ? $g['pkg_categories_max_display'] : 6);

	/* check selected category or define default category to show */
	if (isset($_REQUEST['category']))
		$menu_category = $_REQUEST['category'];
	else if (isset($g['pkg_default_category']))
		$menu_category = $g['pkg_default_category'];
	else
		$menu_category = "All";

	$menu_category = (isset($_REQUEST['category']) ? $_REQUEST['category'] : "All");
	$show_category = ($menu_category == "Other" || $menu_category == "All");

	$tab_array[] = array(gettext("All"), $menu_category=="All" ? true : false, "pkg_mgr.php?category=All");
	foreach ($categories as $category => $c_count) {
		if ($c_count >= $categories_min_count && $cm_count <= $categories_max_display) {
			$tab_array[] = array(gettext($category) , $menu_category==$category ? true : false, "pkg_mgr.php?category={$category}");
			$visible_categories[]=$category;
			$cm_count++;
		}
	}

	$tab_array[] = array(gettext("Other Categories"), $menu_category=="Other" ? true : false, "pkg_mgr.php?category=Other");
	if (count($categories) > 1)
		display_top_tabs($tab_array);
}

function compareName($a, $b) {
    return(strcasecmp ($a['name'], $b['name']));
}

if(!$pkg_info || !is_array($pkg_info)):?>
	<div class="alert alert-warning">
		<?=gettext("There are currently no packages available for installation.")?>
	</div>
<?php else: ?>
	<div class="table-responsive">
	<table class="table table-striped table-hover">
	<thead>
	<tr>
		<th><?=gettext("Name")?></th>
<?php if (!$g['disablepackagehistory']):?>
		<th><?=gettext("Version")?></th>
<?php endif;?>

		<th><?=gettext("Description")?></th>
	</tr>
	</thead>
	<tbody>
<?php

	// Sort case insensitve (so we get AbCdEf not ACEcdf)
	usort($pkg_info, 'compareName');

	foreach($pkg_info as $index):

		if(get_package_id($index['name']) >= 0 ) {
			continue;
		}

		$shortname = $index['name'];
		pkg_remove_prefix($shortname);

		if ($menu_category != "All" && $index['categories'][0] != $menu_category && !($menu_category == "Other" && !in_array($index['categories'][0], $visible_categories))) {
			continue;
		}

		// Check to see if it is already installed
		if(isset($config['installedpackages']['package'])) {
			foreach($config['installedpackages']['package'] as $installedpkg) {
				if($installedpkg['name'] == $shortname) {
					continue(2);
				}
			}
		}

?>
		<tr>
			<td>
<?php if ($index['www']):?>
				<a title="<?=gettext("Visit official website")?>" target="_blank" href="<?=htmlspecialchars($index['www'])?>">
<?php endif; ?>
					<?=htmlspecialchars($shortname)?>
				</a>
			</td>

<?php
	 if (!$g['disablepackagehistory']):?>
			<td>
<!-- We no longer have a package revision history URL
	$changeloglink is undefined
				<a target="_blank" title="<?=gettext("View changelog")?>" href="<?=htmlspecialchars($changeloglink)?>">
-->
				<?=htmlspecialchars($index['version'])?>
<!--
				</a>
-->
			</td>
<?php
endif;
?>
			<td>
				<?=$index['desc']?>
			</td>
			<td>
				<a title="<?=gettext("Click to install")?>" href="pkg_mgr_install.php?id=<?=$index['name']?>" class="btn btn-success btn-sm">install</a>
<?php if(!$g['disablepackageinfo'] && $index['pkginfolink'] && $index['pkginfolink'] != $index['www']):?>
				<a target="_blank" title="<?=gettext("View more information")?>" href="<?=htmlspecialchars($index['pkginfolink'])?>" class="btn btn-default btn-sm">info</a>
<?php endif;?>
			</td>
		</tr>
<?php
	endforeach;
endif;?>
	</tbody>
	</table>
	</div>
<?php include("foot.inc")?>
