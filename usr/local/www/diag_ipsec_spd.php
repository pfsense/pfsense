<?php
/* $Id$ */
/*
	diag_ipsec_spd.php
	Copyright (C) 2004-2009 Scott Ullrich
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
	pfSense_BUILDER_BINARIES:	/sbin/setkey
	pfSense_MODULE: ipsec
*/

##|+PRIV
##|*IDENT=page-status-ipsec-spd
##|*NAME=Status: IPsec: SPD page
##|*DESCR=Allow access to the 'Status: IPsec: SPD' page.
##|*MATCH=diag_ipsec_spd.php*
##|-PRIV

define(DEBUG, true);
define(RIGHTARROW, '&#x25ba;');
define(LEFTARROW,  '&#x25c0;');

require("guiconfig.inc");
require("ipsec.inc");

$pgtitle = array(gettext("Status"),gettext("IPsec"),gettext("SPD"));
$shortcut_section = "ipsec";
include("head.inc");

if(DEBUG) { // Dummy data for testing. REMOVE for production
	$spd = array ( 0 => array ( 'srcid' => '172.27.0.0/16', 'dstid' => '172.21.2.0/24', 'dir' => 'in' , 'proto' => 'esp', 'dst' => '184.57.8.247', 'src' => '208.123.73.7', 'reqid' => 'nique:1' ),
				   1 => array ( 'srcid' => '172.21.2.0/24', 'dstid' => '172.27.0.0/16', 'dir' => 'out', 'proto' => 'esp', 'dst' => '208.123.73.7', 'src' => '184.57.8.247', 'reqid' => 'nique:1' ) );
}
else
	$spd = ipsec_dump_spd();

$tab_array = array();
$tab_array[0] = array(gettext("Overview"), false, "diag_ipsec.php");
$tab_array[1] = array(gettext("Leases"), false, "diag_ipsec_leases.php");
$tab_array[2] = array(gettext("SAD"), false, "diag_ipsec_sad.php");
$tab_array[3] = array(gettext("SPD"), true, "diag_ipsec_spd.php");
$tab_array[4] = array(gettext("Logs"), false, "diag_logs.php?logfile=ipsec");
display_top_tabs($tab_array);
?>
<?php
if (count($spd)){
?>
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th><?= gettext("Source"); ?></th>
				<th><?= gettext("Destination"); ?></th>
				<th><?= gettext("Direction"); ?></th>
				<th><?= gettext("Protocol"); ?></th>
				<th><?= gettext("Tunnel endpoints"); ?></th>
			</tr>
		</thead>

		<tbody>
<?php
	foreach ($spd as $sp) {
		if($sp['dir'] == 'in')
			$dirstr = LEFTARROW . ' Inbound';
		else
			$dirstr = RIGHTARROW . ' Outbound';
?>
			<tr>
				<td>
					<?=htmlspecialchars($sp['srcid'])?>
				</td>
				<td>
					<?=htmlspecialchars($sp['dstid'])?>
				</td>
				<td>
					<?=$dirstr ?>
				</td>
				<td>
					<?=htmlspecialchars(strtoupper($sp['proto']))?>
				</td>
				<td>
					<?=htmlspecialchars($sp['src'])?> -> <?=htmlspecialchars($sp['dst'])?>
				</td>
			</tr>
<?php
	}
?>
		</tbody>
	</table>

<?php
	 } // e-o-if (count($spd))
else {
	print_info_box(gettext('No IPsec security policies configured.'));
}

print_info_box(gettext('You can configure your IPsec subsystem by clicking ') . '<a href="vpn_ipsec.php">' . gettext("here.") . '</a>');

include("foot.inc");