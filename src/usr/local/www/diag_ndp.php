<?php
/*
 * diag_ndp.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2011 Seth Mos <seth.mos@dds.nl>
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-diagnostics-ndptable
##|*NAME=Diagnostics: NDP Table
##|*DESCR=Allow access to the 'Diagnostics: NDP Table' page.
##|*MATCH=diag_ndp.php*
##|-PRIV

@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);

require_once("guiconfig.inc");

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
unset($entry);

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
