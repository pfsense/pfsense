<?php
/* $Id$ */
/*
	pkg_mgr.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2004-2012 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2013 Marcello Coutinho

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

/* if upgrade in progress, alert user */
if (is_subsystem_dirty('packagelock')) {
	$pgtitle = array(gettext("System"), gettext("Package Manager"));
	include("head.inc");
	echo "<body link=\"#0000CC\" vlink=\"#0000CC\" alink=\"#0000CC\">\n";
	include("fbegin.inc");
	echo "Please wait while packages are reinstalled in the background.";
	include("fend.inc");
	echo "</body>";
	echo "</html>";
	exit;
}
function domTT_title($title_msg) {
	if (!empty($title_msg)) {
		$title_msg = preg_replace("/\s+/", " ", $title_msg);
		$title_msg = preg_replace("/'/", "\'", $title_msg);
		echo "onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\" onmouseover=\"domTT_activate(this, event, 'content', '{$title_msg}', 'trail', true, 'delay', 0, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle');\"";
	}
}

$pkg_info = get_pkg_info();

$closehead = false;
$pgtitle = array(gettext("System"), gettext("Package Manager"));
include("head.inc");

?>
<script type="text/javascript" src="javascript/domTT/domLib.js"></script>
<script type="text/javascript" src="javascript/domTT/domTT.js"></script>
<script type="text/javascript" src="javascript/domTT/behaviour.js"></script>
<script type="text/javascript" src="javascript/domTT/fadomatic.js"></script>
<script type="text/javascript" src="/javascript/row_helper_dynamic.js"></script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
	include("fbegin.inc");
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="package manager">
	<tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Available Packages"), true, "pkg_mgr.php");
	$tab_array[] = array(gettext("Installed Packages"), false, "pkg_mgr_installed.php");
	display_top_tabs($tab_array);
?>
	</td></tr>
	<tr><td>
<?php
	if ($pkg_info) {
		$pkg_keys = array_keys($pkg_info);
		natcasesort($pkg_keys);

		//Check categories
		$categories = array();
		foreach ($pkg_info as $pkg_data) {
			if (isset($pkg_data['categories'][0])) {
				$categories[$pkg_data['categories'][0]]++;
			}
		}
		ksort($categories);
		$cm_count = 0;
		$tab_array = array();
		$visible_categories = array();
		$categories_min_count = ($g['pkg_categories_min_count'] ? $g['pkg_categories_min_count'] : 3);
		$categories_max_display = ($g['pkg_categories_max_display'] ? $g['pkg_categories_max_display'] : 6);

		/* check selected category or define default category to show */
		if (isset($_REQUEST['category'])) {
			$menu_category = $_REQUEST['category'];
		} else if (isset($g['pkg_default_category'])) {
			$menu_category = $g['pkg_default_category'];
		} else {
			$menu_category = "All";
		}

		$menu_category = (isset($_REQUEST['category']) ? $_REQUEST['category'] : "All");
		$show_category = ($menu_category == "Other" || $menu_category == "All");

		$tab_array[] = array(gettext("All"), $menu_category == "All" ? true : false, "pkg_mgr.php?category=All");
		foreach ($categories as $category => $c_count) {
			if ($c_count >= $categories_min_count && $cm_count <= $categories_max_display) {
				$tab_array[] = array(gettext($category) , $menu_category == $category ? true : false, "pkg_mgr.php?category={$category}");
				$visible_categories[]=$category;
				$cm_count++;
			}
		}
		$tab_array[] = array(gettext("Other Categories"), $menu_category == "Other" ? true : false, "pkg_mgr.php?category=Other");
		if (count($categories) > 1) {
			display_top_tabs($tab_array);
		}
	}
?>
	</td></tr>
	<tr>
		<td>
			<div id="mainarea">
				<table class="tabcont sortable" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
					<tr>
						<td width="10%" class="listhdrr"><?=gettext("Name"); ?></td>
<?php
	if ($show_category) {
		print '<td width="18%" class="listhdr">'.gettext("Category").'</td>'."\n";
	}
?>
						<td width="<?php print $show_category ? "15%" : "20%"; ?>" class="listhdr"><?=gettext("Version"); ?></td>
						<td width="<?php print $show_category ? "58%" : "70%"; ?>" class="listhdr"><?=gettext("Description"); ?></td>
						<td width="17">&nbsp;</td>
					</tr>
<?php
	if (!$pkg_info) {
		echo "<tr><td colspan=\"5\"><center>" . gettext("There are currently no packages available for installation.") . "</td></tr>";
	} else {
		if (is_array($pkg_keys)) {
			foreach ($pkg_info as $index):
				if (get_package_id($index['name']) >= 0) {
					continue;
				}

				$shortname = $index['name'];
				pkg_remove_prefix($shortname);

				/* Check package info link */
				if ($index['www'] && $index['www'] != 'UNKNOWN') {
					$pkginfolink = $index['www'];
					$pkginfo=gettext("Package info");
				} else {
					$pkginfolink = "https://forum.pfsense.org/index.php/board,15.0.html";
					$pkginfo=gettext("No package info, check the forum");
				}

				if ($menu_category == "All" || $index['categories'][0] == $menu_category || ($menu_category == "Other" && !in_array($index['categories'][0], $visible_categories))):
?>
					<tr valign="top" class="<?= $index['categories'][0] ?>">
						<td class="listlr" <?=domTT_title(gettext("Click on package name to access its website."))?>>
							<a target="_blank" href="<?= $index['www'] ?>"><?=$shortname;?></a>
						</td>
<?php
					if ($show_category) {
						print '<td class="listr">'.gettext($index['categories'][0]).'</td>'."\n";
					}

?>
						<td class="listr"><?=$index['version'];?></td>
						<td class="listbg" style="overflow:hidden; text-align:justify;" <?=domTT_title(gettext("Click package info for more details about ".ucfirst($shortname)." package."))?>>
							<?=str_replace("\n", '<br />', $index['desc']);?>
<?php
					if (!$g['disablepackageinfo']):
?>
							<br /><br />
							<a target='_blank' href='<?=$pkginfolink?>' style='align:center;color:#ffffff; filter:Glow(color=#ff0000, strength=12);'><?=$pkginfo?></a>
<?php
					endif;
?>
						</td>
						<td valign="middle" class="list nowrap" width="17">
							<a href="pkg_mgr_install.php?id=<?=$shortname;?>"><img <?=domTT_title(gettext("Install ".ucfirst($shortname)." package."))?> src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" /></a>
						</td>
					</tr>
<?php
				endif;
			endforeach;
		} else {
			echo "<tr><td colspan='5' align='center'>" . gettext("There are currently no packages available for installation.") . "</td></tr>";
		} /* if (is_array($pkg_keys)) */
	} /* if (!$pkg_info) */
?>
				</table>
			</div>
		</td>
	</tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
