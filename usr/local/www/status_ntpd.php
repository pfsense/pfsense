<?php
/* $Id$ */
/*
	status_ntpd.php
	part of pfSense (http://www.pfsense.com/)

	Copyright (C) 2012 Jim Pingle
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
	pfSense_BUILDER_BINARIES:	/usr/sbin/ntpd	/usr/bin/ntpq
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-status-ntp
##|*NAME=Status: NTP page
##|*DESCR=Allow access to the 'Status: NTP' page.
##|*MATCH=status_ntpd.php*
##|-PRIV

require_once("guiconfig.inc");

exec("/usr/bin/ntpq -pn | /usr/bin/tail +3", $ntpq_output);
$ntpq_servers = array();
foreach ($ntpq_output as $line) {
	$server = array();

	switch (substr($line, 0, 1)) {
		case " ":
			$server['status'] = "Unreach/Pending";
			break;
		case "*":
			$server['status'] = "Active Peer";
			break;
		case "+":
			$server['status'] = "Candidate";
			break;
		case "o":
			$server['status'] = "PPS Peer";
			break;
		case "#":
			$server['status'] = "Selected";
			break;
		case ".":
			$server['status'] = "Excess Peer";
			break;
		case "x":
			$server['status'] = "False Ticker";
			break;
		case "-":
			$server['status'] = "Outlier";
			break;
	}

	$line = substr($line, 1);
	$peerinfo = preg_split("/[\s\t]+/", $line);

	$server['server'] = $peerinfo[0];
	$server['refid'] = $peerinfo[1];
	$server['stratum'] = $peerinfo[2];
	$server['type'] = $peerinfo[3];
	$server['when'] = $peerinfo[4];
	$server['poll'] = $peerinfo[5];
	$server['reach'] = $peerinfo[6];
	$server['delay'] = $peerinfo[7];
	$server['offset'] = $peerinfo[8];
	$server['jitter'] = $peerinfo[9];

	$ntpq_servers[] = $server;
}

$pgtitle = array(gettext("Status"),gettext("NTP"));
include("head.inc");
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td><div id="mainarea">
	<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr><td>Status of the system's Network Time Protocol servers and clock.</td></tr>
	</table>
	<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
	<thead>
	<tr>
		<th class="listhdrr"><?=gettext("Status"); ?></td>
		<th class="listhdrr"><?=gettext("Server"); ?></td>
		<th class="listhdrr"><?=gettext("Ref ID"); ?></td>
		<th class="listhdrr"><?=gettext("Stratum"); ?></td>
		<th class="listhdrr"><?=gettext("Type"); ?></td>
		<th class="listhdrr"><?=gettext("When"); ?></td>
		<th class="listhdrr"><?=gettext("Poll"); ?></td>
		<th class="listhdrr"><?=gettext("Reach"); ?></td>
		<th class="listhdrr"><?=gettext("Delay"); ?></td>
		<th class="listhdrr"><?=gettext("Offset"); ?></td>
		<th class="listhdr"><?=gettext("Jitter"); ?></td>
	</tr>
	</thead>
	<tbody>
	<?php if (count($ntpq_servers) == 0): ?>
	<tr><td class="listlr" colspan="11" align="center">
		No peers found, <a href="status_services.php">is the ntp service running?</a>.
	</td></tr>
	<?php else: ?>
	<?php $i = 0; foreach ($ntpq_servers as $server): ?>
	<tr>
	<td class="listlr" nowrap>
		<?=$server['status'];?>
	</td>
	<td class="listlr">
		<?=$server['server'];?>
	</td>
	<td class="listlr">
		<?=$server['refid'];?>
	</td>
	<td class="listlr">
		<?=$server['stratum'];?>
	</td>
	<td class="listlr">
		<?=$server['type'];?>
	</td>
	<td class="listlr">
		<?=$server['when'];?>
	</td>
	<td class="listlr">
		<?=$server['poll'];?>
	</td>
	<td class="listlr">
		<?=$server['reach'];?>
	</td>
	<td class="listlr">
		<?=$server['delay'];?>
	</td>
	<td class="listlr">
		<?=$server['offset'];?>
	</td>
	<td class="listlr">
		<?=$server['jitter'];?>
	</td>
	</tr>
<?php $i++; endforeach; endif; ?>
	</tbody>
	</table>
</div></td></tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
