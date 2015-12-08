<?php
/*
	pkg_mgr.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*NAME=System: Package Manager
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

$pkg_info = get_pkg_info();

$pgtitle = array(gettext("System"),gettext("Package Manager"),gettext("Available Packages"));

include("head.inc");

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

//	if (count($categories) > 1)
//		display_top_tabs($tab_array);
}

if(!$pkg_info || !is_array($pkg_info)):?>
<div class="alert alert-warning">
	<?=gettext("There are currently no packages available for installation.")?>
</div>
<?php else: ?>

<div class="panel panel-default" id="search-panel">
	<div class="panel-heading"><?=gettext('Search')?>
		<span class="widget-heading-icon pull-right">
			<a data-toggle="collapse" href="#search-panel .panel-body" name="search-panel">
				<i class="fa fa-plus-circle"></i>
			</a>
		</span>
	</div>
	<div class="panel-body collapse in">
		<div class="form-group">
			<label class="col-sm-2 control-label">
				Search term
			</label>
			<div class="col-sm-5"><input class="form-control" name="searchstr" id="searchstr" type="text"/></div>
			<div class="col-sm-2">
				<select id="where" class="form-control">
					<option value="0"><?=gettext("Name")?></option>
					<option value="1"><?=gettext("Description")?></option>
					<option value="2" selected><?=gettext("Both")?></option>
				</select>
			</div>
			<div class="col-sm-3">
				<a id="btnsearch" type="button" title="<?=gettext("Search")?>" class="btn btn-primary btn-sm"><?=gettext("Search")?></a>
				<a id="btnclear" type="button" title="<?=gettext("Clear")?>" class="btn btn-default btn-sm"><?=gettext("Clear")?></a>
			</div>
			<div class="col-sm-10 col-sm-offset-2">
				<span class="help-block">Enter a search string or *nix regular expression to search package names and descriptions.</span>
			</div>
		</div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Packages')?></h2></div>
	<div class="panel-body table-responsive">
		<table id="pkgtable" class="table table-striped table-hover">
			<thead>
				<tr>
					<th><?=gettext("Name")?></th>
<?php if (!$g['disablepackagehistory']):?>
					<th><?=gettext("Version")?></th>
<?php endif;?>

					<th><?=gettext("Description")?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
<?php

	foreach($pkg_info as $index):
		if (isset($index['installed'])) {
			continue;
		}

		if ($menu_category != "All" && $index['categories'][0] != $menu_category && !($menu_category == "Other" && !in_array($index['categories'][0], $visible_categories))) {
			continue;
		}

?>
				<tr>
					<td>
<?php if ($index['www']):?>
						<a title="<?=gettext("Visit official website")?>" target="_blank" href="<?=htmlspecialchars($index['www'])?>">
<?php endif; ?>
							<?=htmlspecialchars($index['shortname'])?>
						</a>
					</td>

<?php
	 if (!$g['disablepackagehistory']):?>
					<td>
						<?=htmlspecialchars($index['version'])?>
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
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

	// Initial state & toggle icons of collapsed panel
	$('.panel-heading a[data-toggle="collapse"]').each(function (idx, el){
		var body = $(el).parents('.panel').children('.panel-body')
		var isOpen = body.hasClass('in');

		$(el).children('i').toggleClass('fa-plus-circle', !isOpen);
		$(el).children('i').toggleClass('fa-minus-circle', isOpen);

		body.on('shown.bs.collapse', function(){
			$(el).children('i').toggleClass('fa-minus-circle', true);
			$(el).children('i').toggleClass('fa-plus-circle', false);
		});
	});

	// Make these controls plain buttons
	$("#btnsearch").prop('type' ,'button');
	$("#btnclear").prop('type' ,'button');

	// Search for a term in the package name and/or description
	$("#btnsearch").click(function() {
		var searchstr = $('#searchstr').val().toLowerCase();
		var table = $("table tbody");
		var where = $('#where').val();

		table.find('tr').each(function (i) {
			var $tds = $(this).find('td'),
				shortname = $tds.eq(0).text().trim().toLowerCase(),
				descr = $tds.eq(2).text().trim().toLowerCase();

			regexp = new RegExp(searchstr);
			if(searchstr.length > 0) {
				if( !(regexp.test(shortname) && (where != 1)) && !(regexp.test(descr) && (where != 0))) {
					$(this).hide();
				} else {
					$(this).show();
				}
			} else {
				 $(this).show();	// A blank search string shows all
			}
		});
	});

	// Clear the search term and unhide all rows (that were hidden during a previous search)
	$("#btnclear").click(function() {
		var table = $("table tbody");

		$('#searchstr').val("");

		table.find('tr').each(function (i) {
			$(this).show();
		});
	});

	// Hitting the enter key will do the same as clicking the search button
	$("#searchstr").on("keyup", function (event) {
	    if (event.keyCode==13) {
	        $("#btnsearch").get(0).click();
	    }
	});
});
//]]>
</script>

<?php include("foot.inc");
