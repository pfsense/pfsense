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

##|+PRIV
##|*IDENT=page-system-packagemanager-installed
##|*NAME=System: Package Manager: Installed
##|*DESCR=Allow access to the 'System: Package Manager: Installed' page.
##|*MATCH=pkg_mgr_installed.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pkg-utils.inc");

/* if upgrade in progress, alert user */
if (is_subsystem_dirty('packagelock')) {
	$pgtitle = array(gettext("System"), gettext("Package Manager"));
	include("head.inc");
	print_info_box("Please wait while packages are reinstalled in the background.");
	include("foot.inc");
	exit;
}

// We are being called only to get the pacakge data, not to display anything
if (($_REQUEST) && ($_REQUEST['ajax'])) {
	print(get_pkg_table());
	exit;
}

function get_pkg_table() {
	$installed_packages = array();
	$package_list = get_pkg_info();

	if (!$package_list) {
		print("error");
		exit;
	}

	foreach ($package_list as $pkg) {
		if (!isset($pkg['installed']) && !isset($pkg['broken'])) {
			continue;
		}
		$installed_packages[] = $pkg;
	}

	$pkgtbl = "";

	if (empty($installed_packages)) {
		print ("nopkg");
		exit;
	}

	$pkgtbl .='	<div class="panel panel-default">';
	$pkgtbl .='		<div class="panel-heading"><h2 class="panel-title">' . gettext('Installed Packages') . '</h2></div>';
	$pkgtbl .='		<div class="table-responsive">';
	$pkgtbl .='		<table class="table table-striped table-hover table-condensed">';
	$pkgtbl .='			<thead>';
	$pkgtbl .='				<tr>';
	$pkgtbl .='					<th><!-- Status icon --></th>';
	$pkgtbl .='					<th>' . gettext("Name") . '</th>';
	$pkgtbl .='					<th>' . gettext("Category") . '</th>';
	$pkgtbl .='					<th>' . gettext("Version") . '</th>';
	$pkgtbl .='					<th>' . gettext("Description") . '</th>';
	$pkgtbl .='					<th>' . gettext("Actions") . '</th>';
	$pkgtbl .='				</tr>';
	$pkgtbl .='			</thead>';
	$pkgtbl .='			<tbody>';

	foreach ($installed_packages as $pkg) {
		if (!$pkg['name']) {
			continue;
		}

		#check package version
		$txtcolor = "";
		$upgradeavail = false;
		$missing = false;
		$vergetstr = "";

		if (isset($pkg['broken'])) {
			// package is configured, but does not exist in the system
			$txtcolor = "text-danger";
			$missing = true;
			$status = gettext('Package is configured, but not installed!');
		} else if (isset($pkg['installed_version']) && isset($pkg['version'])) {
			$version_compare = pkg_version_compare($pkg['installed_version'], $pkg['version']);

			if ($version_compare == '>') {
				// we're running a newer version of the package
				$status = sprintf(gettext('Newer than available (%s)'), $pkg['version']);
			} else if ($version_compare == '<') {
				// we're running an older version of the package
				$status = sprintf(gettext('Upgrade available to %s'), $pkg['version']);
				$txtcolor = "text-warning";
				$upgradeavail = true;
				$vergetstr = '&amp;from=' . $pkg['installed_version'] . '&amp;to=' . $pkg['version'];
			} else if ($version_compare == '=') {
				// we're running the current version
				$status = gettext('Up-to-date');
			} else {
				$status = gettext('Error comparing version');
			}
		} else {
			// unknown available package version
			$status = gettext('Unknown');
			$statusicon = 'question';
		}

		$pkgtbl .='				<tr>';
		$pkgtbl .='					<td>';

		if ($upgradeavail) {
			$pkgtbl .='						<a title="' . $status . '" href="pkg_mgr_install.php?mode=reinstallpkg&amp;pkg=' . $pkg['name'] . $vergetstr . '" class="fa fa-refresh"></a>';
		} elseif ($missing) {
			$pkgtbl .='						<span class="text-danger"><i title="' . $status . '" class="fa fa-exclamation"></i></span>';
		} else {
			$pkgtbl .='						<i title="' . $status . '" class="fa fa-check"></i>';
		}
		$pkgtbl .='					</td>';
		$pkgtbl .='					<td>';
		$pkgtbl .='						<span class="' . $txtcolor . '">' . $pkg['shortname'] . '</span>';
		$pkgtbl .='					</td>';
		$pkgtbl .='					<td>';
		$pkgtbl .='						' . implode(" ", $pkg['categories']);
		$pkgtbl .='					</td>';
		$pkgtbl .='					<td>';

		if (!$g['disablepackagehistory']) {
			$pkgtbl .='						<a target="_blank" title="' . gettext("View changelog") . '" href="' . htmlspecialchars($pkg['changeloglink']) . '">' .
		    htmlspecialchars($pkg['installed_version']) . '</a>';
		} else {
			$pkgtbl .='						' . htmlspecialchars($pkg['installed_version']);
		}

		$pkgtbl .='					</td>';
		$pkgtbl .='					<td>';
		$pkgtbl .='						' . $pkg['desc'];

		if (is_array($pkg['deps']) && count($pkg['deps'])) {
			$pkgtbl .='						<br /><br />' . gettext("Package Dependencies") . ':<br/>';
			foreach ($pkg['deps'] as $pdep) {
				$pkgtbl .='						<a target="_blank" href="https://freshports.org/' . $pdep['origin'] . '">&nbsp;' .
				    '<i class="fa fa-paperclip"></i> ' . basename($pdep['origin']) . '-' . $pdep['version'] . '</a>&emsp;';
			}
		}
		$pkgtbl .='					</td>';
		$pkgtbl .='					<td>';
		$pkgtbl .='						<div class="row">';
		$pkgtbl .='							<a title="' . sprintf(gettext("Remove package %s"), $pkg['name']) .
		    '" href="pkg_mgr_install.php?mode=delete&amp;pkg=' . $pkg['name'] . '" class="fa fa-trash"></a>';

		if ($upgradeavail) {
			$pkgtbl .='						<a title="' . sprintf(gettext("Update package %s"), $pkg['name']) .
			    '" href="pkg_mgr_install.php?mode=reinstallpkg&amp;pkg=' . $pkg['name'] . $vergetstr . '" class="fa fa-refresh"></a>';
		} else {
			$pkgtbl .='						<a title="' . sprintf(gettext("Reinstall package %s"), $pkg['name']) .
			    '" href="pkg_mgr_install.php?mode=reinstallpkg&amp;pkg=' . $pkg['name'] . '" class="fa fa-retweet"></a>';
		}

		if (!isset($g['disablepackageinfo']) && $pkg['www'] != 'UNKNOWN') {
			$pkgtbl .='						<a target="_blank" title="' . gettext("View more information") . '" href="' .
			    htmlspecialchars($pkg['www']) . '" class="fa fa-info"></a>';
		}
		$pkgtbl .='						</div>';
		$pkgtbl .='					</td>';
		$pkgtbl .='				</tr>';
	}

	$pkgtbl .='			</tbody>';
	$pkgtbl .='		</table>';
	$pkgtbl .='		</div>';
	$pkgtbl .='	</div>';
	$pkgtbl .='	<br />';
	$pkgtbl .='	<div class="text-center">';
	$pkgtbl .='		<p>';
	$pkgtbl .='			<i class="fa fa-refresh"></i> = ' . gettext('Update') . ' &nbsp;';
	$pkgtbl .='			<i class="fa fa-check"></i> = ' . gettext('Current') . ' &nbsp;';
	$pkgtbl .='		</p>';
	$pkgtbl .='		<p>';
	$pkgtbl .='			<i class="fa fa-trash"></i> = ' . gettext('Remove') . ' &nbsp;';
	$pkgtbl .='			<i class="fa fa-info"></i> = ' . gettext('Information') . ' &nbsp;';
	$pkgtbl .='			<i class="fa fa-retweet"></i> = ' . gettext('Reinstall');
	$pkgtbl .='		</p>';
	$pkgtbl .='		<p><span class="text-warning">' . gettext("Newer version available") . '</span></p>';
	$pkgtbl .='		<p><span class="text-danger">' . gettext("Package is configured but not (fully) installed") . '</span></p>';
	$pkgtbl .='	</div>';

	return $pkgtbl;
}

$pgtitle = array(gettext("System"), gettext("Package Manager"), gettext("Installed Packages"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Installed Packages"), true, "pkg_mgr_installed.php");
$tab_array[] = array(gettext("Available Packages"), false, "pkg_mgr.php");
display_top_tabs($tab_array);

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Packages')?></h2></div>
	<div id="pkgtbl" class="panel-body table-responsive">
		<div id="waitmsg">
			<?php print_info_box(gettext("Please wait while the list of packages is retrieved and formatted.") . '&nbsp;<i class="fa fa-cog fa-spin"></i>'); ?>
		</div>

		<div id="errmsg" style="display: none;">
			<?php print_info_box("<ul><li>" . gettext("Unable to retrieve package information.") . "</li></ul>", 'danger'); ?>
		</div>

		<div id="nopkg" style="display: none;">
			<?php print_info_box(gettext("There are no packages currently installed."), 'warning', false); ?>
		</div>
	</div>
</div>
<script type="text/javascript">
//<![CDATA[

events.push(function() {

	// Retrieve the table formatted pacakge information and display it in the "Packages" panel
	// (Or display an appropriate error message)
	var ajaxRequest;

	$.ajax({
		url: "/pkg_mgr_installed.php",
		type: "post",
		data: { ajax: "ajax"},
		success: function(data) {
			if (data == "error") {
				$('#waitmsg').hide();
				$('#nopkg').hide();
				$('#errmsg').show();
			} else if (data == "nopkg") {
				$('#waitmsg').hide();
				$('#nopkg').show();
				$('#errmsg').hide();
			} else {
				$('#pkgtbl').html(data);
			}
		},
		error: function() {
			$('#waitmsg').hide();
			$('#nopkg').hide();
			$('#errmsg').show();
		}
	});

});
//]]>
</script>

<?php include("foot.inc")?>
