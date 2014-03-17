<?php
/*
	$Id$
	Copyright 2007 Scott Dale
	Part of pfSense widgets (https://www.pfsense.org)
	originally based on m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
	and Jonathan Watt <jwatt@jwatt.org>.
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/installed_packages.inc");
require_once("pkg-utils.inc");

if(is_array($config['installedpackages']['package'])) {
	foreach($config['installedpackages']['package'] as $instpkg) {
		$tocheck[] = $instpkg['name'];
	}
	$currentvers = get_pkg_info($tocheck, array('version', 'xmlver'));
}

$updateavailable = false;
?>

<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="installed packages">
	<tr>
		<td width="15%" class="listhdrr">Package Name</td>
		<td width="15%" class="listhdrr">Category</td>
		<td width="30%" class="listhdrr">Package Version</td>
	</tr>
	<?php
	if($config['installedpackages']['package'] != "") {
		$instpkgs = array();
		foreach($config['installedpackages']['package'] as $instpkg)
			$instpkgs[] = $instpkg['name'];
		natcasesort($instpkgs);
		$y=1;
		foreach ($instpkgs as $index => $pkgname){

			$pkg = $config['installedpackages']['package'][$index];
			if($pkg['name'] <> "") { ?>
				<tr valign="top">
				<td class="listlr">
					<?= $pkg['name'] ?>
				</td>
				<td class="listlr">
					<?= $pkg['category'] ?>
				</td>
				<td class="listlr">
				<?php
				$latest_package = $currentvers[$pkg['name']]['version'];
				if($latest_package == false) {
					// We can't determine this package's version status.
					echo "Current: Unknown.<br />Installed: " . $pkg['version'];
				} elseif(strcmp($pkg['version'], $latest_package) > 0) {
					/* we're running a newer version of the package */
					echo "Current: {$latest_package}";
					echo "<br />Installed: {$pkg['version']}";
				} elseif(strcmp($pkg['version'], $latest_package) < 0) {
					/* our package is out of date */
					$updateavailable = true;
					?>
					<div id="updatediv-<?php echo $y; ?>" style="color:red">
						<b>Update Available!</b></div><div style="float:left">
						Current: <?php echo $latest_package; ?><br />
						Installed: <?php echo $pkg['version']; ?></div><div style="float:right">
					<a href="pkg_mgr_install.php?mode=reinstallpkg&amp;pkg=<?= $pkg['name']; ?>"><img title="Update this package." src="./themes/<?= $g['theme']; ?>/images/icons/icon_reinstall_pkg.gif" width="17" height="17" border="0" alt="reinstall" /></a>
					</div>
					<?php
					$y++;
				} else {
					echo $pkg['version'];
				} ?>
				</td>
				</tr>
		<?php	}
		}
	} else {
		echo "<tr><td colspan=\"5\" align=\"center\">There are no packages currently installed.</td></tr>";
	}
	?>
</table>

<?php if ($updateavailable): ?>
<script type="text/javascript">
//<![CDATA[
	window.onload = function(in_event)
	{
		for (y=1; y<=<?php echo $y;?>; y++){
			textID = "#updatediv-" + y;
			jQuery(textID).effect('pulsate');
		}
	}
//]]>
</script>
<?php endif; ?>
