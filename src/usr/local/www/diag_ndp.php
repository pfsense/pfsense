<?php
/*
	diag_ndp.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c) 2011 Seth Mos <seth.mos@dds.nl>
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-diagnostics-ndptable
##|*NAME=Diagnostics: NDP Table
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
function _getHostName($mac, $ip) {
	if (is_ipaddr($ip)) {
		list($ip, $scope) = explode("%", $ip);
		if (gethostbyaddr($ip) <> "" and gethostbyaddr($ip) <> $ip) {
			return gethostbyaddr($ip);
		} else {
			return "";
		}
	}
}

// Resolve hostnames and replace Z_ with "".  The intention
// is to sort the list by hostnames, alpha and then the non
// resolvable addresses will appear last in the list.
foreach ($data as &$entry) {
	$dns = trim(_getHostName($entry['mac'], $entry['ipv6']));
	if (trim($dns)) {
		$entry['dnsresolve'] = "$dns";
	} else {
		$entry['dnsresolve'] = "Z_ ";
	}
}

// Sort the data alpha first
$data = msort($data, "dnsresolve");

// Load MAC-Manufacturer table
$mac_man = load_mac_manufacturer_table();

$pgtitle = array(gettext("Diagnostics"), gettext("NDP Table"));
include("head.inc");
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('NDP Table')?></h2></div>
	<div class="panel-body">

<div class="table-responsive">
	<table class="table table-striped table-condensed table-hover sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th><?= gettext("IPv6 address"); ?></th>
				<th><?= gettext("MAC address"); ?></th>
				<th><?= gettext("Hostname"); ?></th>
				<th><?= gettext("Interface"); ?></th>
			</tr>
	</thead>
	<tbody>
			<?php foreach ($data as $entry): ?>
				<tr>
					<td><?=$entry['ipv6']?></td>
					<td>
						<?php
						$mac=trim($entry['mac']);
						$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
						?>
						<?=$mac?>

						<?php if (isset($mac_man[$mac_hi])):?>
							(<?=$mac_man[$mac_hi]?>)
						<?php endif; ?>

					</td>
					<td>
						<?=htmlspecialchars(str_replace("Z_ ", "", $entry['dnsresolve']))?>
					</td>
					<td>
						<?php
						if (isset($hwif[$entry['interface']])) {
							echo $hwif[$entry['interface']];
						} else {
							echo $entry['interface'];
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
	</tbody>
	</table>
</div>

	</div>
</div>

<?php include("foot.inc");
