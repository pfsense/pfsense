<?php
/*
	installed_packages.widget.php

	Copyright (C) 2013-2014 Electric Sheep Fencing, LP
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
	$instpkgs = array();
	foreach ($config['installedpackages']['package'] as $instpkg)
		$instpkgs[ $instpkg['name'] ] = $instpkg;
	ksort($instpkgs);
	$currentvers = get_pkg_info(array_keys($instpkgs), array('version', 'xmlver'));
}
?>

<?php if (empty($config['installedpackages']['package'])): ?>
	<div class="alert alert-warning" role="alert">
		<strong>No packages installed.</strong>
		You can install packages <a href="pkg_mgr.php" class="alert-link">here</a>.
	</div>
<?php else: ?>
	<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th>Package Name</th>
			<th>Category</th>
			<th>Package Version</th>
		</tr>
	</thead>
	<tbody>
<?php
foreach ($instpkgs as $pkgname => $pkg):
	if (empty($pkgname))
		continue;

	$latest_package = $currentvers[$pkg['name']]['version'];
	if ($latest_package) {
		// we're running a newer version of the package
		if(strcmp($pkg['version'], $latest_package) > 0) {
			$status = 'Newer then available ('. $latest_package .')';
			$statusicon = 'exclamation';
		}
		// we're running an older version of the package
		if(strcmp($pkg['version'], $latest_package) < 0) {
			$status = 'Upgrade available to '.$latest_package;
			$statusicon = 'plus';
		}
		// we're running the current version
		if(!strcmp($pkg['version'], $latest_package)) {
			$status = 'Up-to-date';
			$statusicon = 'ok';
		}
	} else {
		// unknown available package version
		$status = 'Unknown';
		$statusicon = 'question';
	}
?>
		<tr>
			<td><?=$pkg['name']?></td>
			<td><?=$pkg['category']?></td>
			<td>
				<i title="<?=$status?>" class="icon icon-<?=$statusicon?>-sign"></i>
				<?=$pkg['version']?>
			</td>
		</tr>
<?php endforeach; ?>
	</tbody>
	</table>
<?php endif; ?>