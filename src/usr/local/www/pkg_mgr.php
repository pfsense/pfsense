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

$timezone = $config['system']['timezone'];
if (!$timezone)
	$timezone = "Etc/UTC";

date_default_timezone_set($timezone);

/* if upgrade in progress, alert user */
if(is_subsystem_dirty('packagelock')) {
	$pgtitle = array(gettext("System"),gettext("Package Manager"));
	include("head.inc");
	print_info_box_np("Please wait while packages are reinstalled in the background.");
	include("foot.inc");
	exit;
}

//get_pkg_info only if cache file has more then $g[min_pkg_cache_file_time] seconds
$pkg_cache_file_time=($g['min_pkg_cache_file_time'] ? $g['min_pkg_cache_file_time'] : 120);

$xmlrpc_base_url = get_active_xml_rpc_base_url();
if (!file_exists("{$g['tmp_path']}/pkg_info.cache") || (time() - filemtime("{$g['tmp_path']}/pkg_info.cache")) > $pkg_cache_file_time) {
	$pkg_info = get_pkg_info('all', array("noembedded", "name", "category", "website", "version", "status", "descr", "maintainer", "required_version", "maximum_version", "pkginfolink", "config_file"));
	//create cache file after get_pkg_info
	if($pkg_info) {
		$fout = fopen("{$g['tmp_path']}/pkg_info.cache", "w");
		fwrite($fout, serialize($pkg_info));
		fclose($fout);
		//$pkg_sizes = get_pkg_sizes();
	} else {
		$using_cache = true;
		if(file_exists("{$g['tmp_path']}/pkg_info.cache")) {
			$savemsg = sprintf(gettext("Unable to retrieve package info from %s. Cached data will be used."), $xmlrpc_base_url);
			$pkg_info = unserialize(@file_get_contents("{$g['tmp_path']}/pkg_info.cache"));
		} else {
			$savemsg = sprintf(gettext('Unable to communicate with %1$s. Please verify DNS and interface configuration, and that %2$s has functional Internet connectivity.'), $xmlrpc_base_url, $g['product_name']);
		}
	}
} else {
	$pkg_info = unserialize(@file_get_contents("{$g['tmp_path']}/pkg_info.cache"));
}

if (! empty($_GET))
	if (isset($_GET['ver']))
		$requested_version = htmlspecialchars($_GET['ver']);

$pgtitle = array(gettext("System"),gettext("Package Manager"));
include("head.inc");

/* Print package server mismatch warning. See https://redmine.pfsense.org/issues/484 */
if (!verify_all_package_servers())
	print_info_box(package_server_mismatch_message());

/* Print package server SSL warning. See https://redmine.pfsense.org/issues/484 */
if (check_package_server_ssl() === false)
	print_info_box(package_server_ssl_failure_message());

if ($savemsg)
	print_info_box($savemsg);

$version = rtrim(file_get_contents("/etc/version"));

$tab_array = array();
$tab_array[] = array(gettext("Available Packages"), $requested_version <> "" ? false : true, "pkg_mgr.php");
$tab_array[] = array(gettext("Installed Packages"), false, "pkg_mgr_installed.php");
display_top_tabs($tab_array);

$version = rtrim(file_get_contents("/etc/version"));
if($pkg_info) {
	$pkg_keys = array_keys($pkg_info);
	natcasesort($pkg_keys);

	//Check categories
	$categories=array();
	if(is_array($pkg_keys)) {
		foreach($pkg_keys as $key) {
				$categories[$pkg_info[$key]['category']]++;
			}
		}
	ksort($categories);
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

if(!$pkg_info || !is_array($pkg_keys)):?>
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
	foreach($pkg_keys as $key):
		$index = &$pkg_info[$key];

		if(get_pkg_id($index['name']) >= 0 )
			continue;
		continue;

		/* get history/changelog git dir */
		$commit_dir=explode("/",$index['config_file']);
		$changeloglink = "https://github.com/pfsense/pfsense-packages/commits/master/config/";
		if ($commit_dir[(count($commit_dir)-2)] == "config")
			$changeloglink .= $commit_dir[(count($commit_dir)-1)];
		else
			$changeloglink .= $commit_dir[(count($commit_dir)-2)];

		if ($menu_category != "All" && $index['category'] != $menu_category && !($menu_category == "Other" && !in_array($index['category'], $visible_categories)))
			continue;
?>
		<tr>
			<td>
<?php if ($index['www']):?>
				<a title="<?=gettext("Visit official website")?>" target="_blank" href="<?=htmlspecialchars($index['www'])?>">
<?php endif; ?>
					<?=htmlspecialchars($index['name'])?>
				</a>
			</td>

<?php if (!$g['disablepackagehistory']):?>
			<td>
				<a target="_blank" title="<?=gettext("View changelog")?>" href="<?=htmlspecialchars($changeloglink)?>">
					<?=htmlspecialchars($index['version'])?>
				</a>
			</td>
<?php endif;?>
			<td>
				<?=$index['desc']?>
			</td>
			<td>
				<a title="<?=gettext("Click to install")?>" href="pkg_mgr_install.php?id=<?=$index['name']?>" class="btn btn-success">install</a>
<?php if(!$g['disablepackageinfo'] && $index['pkginfolink'] && $index['pkginfolink'] != $index['website']):?>
				<a target="_blank" title="<?=gettext("View more inforation")?>" href="<?=htmlspecialchars($index['pkginfolink'])?>" class="btn btn-default">info</a>
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