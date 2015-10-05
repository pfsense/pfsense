<?php
/*
	$Id: interface_statistics.widget.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

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
require_once("/usr/local/www/widgets/include/interface_statistics.inc");

$rows = array(
	'inpkts' => 'Packets In',
	'outpkts' => 'Packets Out',
	'inbytes' => 'Bytes In',
	'outbytes' => 'Bytes Out',
	'inerrs' => 'Errors In',
	'outerrs' => 'Errors Out',
	'collisions' => 'Collisions',
);
$ifdescrs = get_configured_interface_with_descr();

?>
<table class="table table-striped table-hover">
<thead>
	<tr>
		<td></td>
<?php foreach ($ifdescrs as $ifname): ?>
		<th><?=$ifname?></th>
<?php endforeach; ?>
	</tr>
</thead>
<tbody>
<?php foreach ($rows as $key => $name): ?>
	<tr>
		<th><?=$name?></th>
<?php foreach ($ifdescrs as $ifdescr => $ifname):
		$ifinfo = get_interface_info($ifdescr);

		if ($ifinfo['status'] == "down")
			continue;

		$ifinfo['inbytes'] = format_bytes($ifinfo['inbytes']);
		$ifinfo['outbytes'] = format_bytes($ifinfo['outbytes']);
	?>
		<td><?=(isset($ifinfo[$key]) ? htmlspecialchars($ifinfo[$key]) : 'n/a')?></td>
<?php endforeach; ?>
		</tr>
<?php endforeach; ?>
	</tbody>
</table>