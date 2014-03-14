<?php
/*
	diag_ndp.php
	part of the pfSense project	(https://www.pfsense.org)
	Copyright (C) 2004-2010 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2011 Seth Mos <seth.mos@dds.nl>
	

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2005 Paul Taylor (paultaylor@winndixie.com) and Manuel Kasper <mk@neon1.net>.
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
	pfSense_BUILDER_BINARIES:	/bin/cat		/usr/sbin/arp
	pfSense_MODULE:	arp
*/

##|+PRIV
##|*IDENT=page-diagnostics-ndptable
##|*NAME=Diagnostics: NDP Table page
##|*DESCR=Allow access to the 'Diagnostics: NDP Table' page.
##|*MATCH=diag_ndp.php*
##|-PRIV

@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);

require("guiconfig.inc");

exec("/usr/sbin/ndp -na", $rawdata);

$i = 0;

/* if list */
$ifdescrs = get_configured_interface_with_descr();

foreach ($ifdescrs as $key =>$interface) {
	$hwif[$config['interfaces'][$key]['if']] = $interface;
}

/* Array ( [0] => Neighbor [1] => Linklayer [2] => Address 
[3] => Netif [4] => Expire [5] => S 
[6] => Flags ) */
$data = array();
array_shift($rawdata);
foreach ($rawdata as $line) {
	$elements = preg_split('/[ ]+/', $line);

	$ndpent = array();
	$ndpent['ipv6'] = trim($elements[0]);
	$ndpent['mac'] = trim($elements[1]);
	$ndpent['interface'] = trim($elements[2]);
	$data[] = $ndpent;
}

/* FIXME: Not ipv6 compatible dns resolving. PHP needs fixing */
function _getHostName($mac,$ip)
{       
	if(is_ipaddr($ip)) {
		list($ip, $scope) = explode("%", $ip);
		if(gethostbyaddr($ip) <> "" and gethostbyaddr($ip) <> $ip)
			return gethostbyaddr($ip);
		else
			return "";
	}
}

// Resolve hostnames and replace Z_ with "".  The intention
// is to sort the list by hostnames, alpha and then the non
// resolvable addresses will appear last in the list.
foreach ($data as &$entry) {
	$dns = trim(_getHostName($entry['mac'], $entry['ipv6']));
	if(trim($dns))
		$entry['dnsresolve'] = "$dns";
	else
		$entry['dnsresolve'] = "Z_ ";
}
                
// Sort the data alpha first
$data = msort($data, "dnsresolve");

// Load MAC-Manufacturer table
$mac_man = load_mac_manufacturer_table();

$pgtitle = array(gettext("Diagnostics"),gettext("NDP Table"));
include("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000">

<?php include("fbegin.inc"); ?>

<div id="loading">
	<img src="/themes/<?=$g['theme'];?>/images/misc/loader.gif"><?= gettext("Loading, please wait..."); ?>
	<p/>&nbsp;
</div>

<?php

// Flush buffers out to client so that they see Loading, please wait....
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);

?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
			<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
				<tr>
					<td class="listhdrr"><?= gettext("IPv6 address"); ?></td>
					<td class="listhdrr"><?= gettext("MAC address"); ?></td>
					<td class="listhdrr"><?= gettext("Hostname"); ?></td>
					<td class="listhdr"><?= gettext("Interface"); ?></td>
					<td class="list"></td>
				</tr>
				<?php foreach ($data as $entry): ?>
					<tr>
						<td class="listlr"><?=$entry['ipv6'];?></td>
						<td class="listr">
							<?php
							$mac=trim($entry['mac']);
							$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
							print $mac;
							if(isset($mac_man[$mac_hi])){ print "<br/><font size=\"-2\"><i>{$mac_man[$mac_hi]}</i></font>"; }
							?>
						</td>
						<td class="listr">
							<?php
							echo "&nbsp;". str_replace("Z_ ", "", $entry['dnsresolve']);
							?>
						</td>
						<td class="listr">
							<?php 
							if(isset($hwif[$entry['interface']]))
								echo $hwif[$entry['interface']];
							else
								echo $entry['interface'];
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
</table>

<?php include("fend.inc"); ?>

<script type="text/javascript">
	jQuery('#loading').html('');
</script>
