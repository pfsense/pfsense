<?php
/*
	installed_packages.widget.php
*/
/*
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  Scott Dale
 *	Copyright (c)  2004-2005 T. Lechat <dev@lechat.org>
 *	Copyright (c)  Manuel Kasper <mk@neon1.net>
 *	Copyright (c)  Jonathan Watt <jwatt@jwatt.org>
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
 */

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/installed_packages.inc");
require_once("pkg-utils.inc");

$package_list = get_pkg_info();
$installed_packages = array_filter($package_list, function($v) {
	return isset($v['installed']);
});

if (empty($installed_packages)): ?>
	<div class="alert alert-warning" role="alert">
		<strong>No packages installed.</strong>
		You can install packages <a href="pkg_mgr.php" class="alert-link">here</a>.
	</div>
<?php else: ?>
	<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th>Name</th>
			<th>Category</th>
			<th>Version</th>
		</tr>
	</thead>
	<tbody>
<?php


foreach ($installed_packages as $pkg):
	if (!$pkg['name']) {
		continue;
	}

	$txtcolor = "black";
	$upgradeavail = false;
	$vergetstr = "";

	if (isset($pkg['installed_version']) && isset($pkg['version'])) {
		$version_compare = pkg_version_compare(
		    $pkg['installed_version'], $pkg['version']);
		if ($version_compare == '>') {
			// we're running a newer version of the package
			$status = 'Newer then available ('. $pkg['version'] .')';
			$statusicon = 'exclamation';
		} else if ($version_compare == '<') {
			// we're running an older version of the package
			$status = 'Upgrade available to '.$pkg['version'];
			$statusicon = 'plus';
			$txtcolor = "blue";
			$upgradeavail = true;
			$vergetstr = '&amp;from=' . $pkg['installed_version'] .
			    '&amp;to=' . $pkg['version'];
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
	}
?>
		<tr>
			<td><?=$pkg['shortname']?></td>
			<td><?=implode(' ', $pkg['categories'])?></td>
			<td>
				<i title="<?=$status?>" class="icon icon-<?=$statusicon?>-sign"></i>
				<?=$pkg['installed_version']?>
			</td>
		</tr>
<?php endforeach; ?>
	</tbody>
	</table>
<?php endif; ?>
