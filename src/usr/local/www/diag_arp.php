<?php
/*
 * diag_arp.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-diagnostics-arptable
##|*NAME=Diagnostics: ARP Table
##|*DESCR=Allow access to the 'Diagnostics: ARP Table' page.
##|*MATCH=diag_arp.php*
##|-PRIV

@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);

require_once("guiconfig.inc");

// delete arp entry
if (isset($_POST['deleteentry'])) {
	$ip = $_POST['deleteentry'];
	if (is_ipaddrv4($ip)) {
		$ret = mwexec("arp -d " . $_POST['deleteentry'], true);
	} else {
		$ret = 1;
	}
	if ($ret) {
		$savemsg = sprintf(gettext("%s is not a valid IPv4 address or could not be deleted."), $ip);
		$savemsgtype = 'alert-warning';
	} else {
		$savemsg = sprintf(gettext("The ARP cache entry for %s has been deleted."), $ip);
		$savemsgtype = 'success';
	}
}

function leasecmp($a, $b) {
	return strcmp($a[$_REQUEST['order']], $b[$_REQUEST['order']]);
}

function adjust_gmt($dt) {
	$ts = strtotime($dt . " GMT");
	return strftime("%Y/%m/%d %H:%M:%S", $ts);
}

function remove_duplicate($array, $field) {
	foreach ($array as $sub) {
		$cmp[] = $sub[$field];
	}
	$unique = array_unique($cmp);
	foreach ($unique as $k => $rien) {
		$new[] = $array[$k];
	}
	return $new;
}

// Define path to AWK
$awk = "/usr/bin/awk";

// Read in leases file
$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";

/* this pattern sticks comments into a single array item */
$cleanpattern = "'{ gsub(\"#.*\", \"\");} { gsub(\";\", \"\"); print;}'";

/* We then split the leases file by } */
$splitpattern = "'BEGIN { RS=\"}\";} {for (i=1; i<=NF; i++) printf \"%s \", \$i; printf \"}\\n\";}'";

/* stuff the leases file in a proper format into an array by line */
exec("cat {$leasesfile} | {$awk} {$cleanpattern} | {$awk} {$splitpattern}", $leases_content);
$leases_count = count($leases_content);

$pools = array();
$leases = array();
$i = 0;
$l = 0;
$p = 0;
// Put everything together again
while ($i < $leases_count) {
	/* split the line by space */
	$data = explode(" ", $leases_content[$i]);
	/* walk the fields */
	$f = 0;
	$fcount = count($data);
	/* with less then 20 fields there is nothing useful */
	if ($fcount < 20) {
		$i++;
		continue;
	}
	while ($f < $fcount) {
		switch ($data[$f]) {
			case "failover":
				$pools[$p]['name'] = $data[$f+2];
				$pools[$p]['mystate'] = $data[$f+7];
				$pools[$p]['peerstate'] = $data[$f+14];
				$pools[$p]['mydate'] = $data[$f+10];
				$pools[$p]['mydate'] .= " " . $data[$f+11];
				$pools[$p]['peerdate'] = $data[$f+17];
				$pools[$p]['peerdate'] .= " " . $data[$f+18];
				$p++;
				$i++;
				continue 3;
			case "lease":
				$leases[$l]['ip'] = $data[$f+1];
				$leases[$l]['type'] = "dynamic";
				$f = $f+2;
				break;
			case "starts":
				$leases[$l]['start'] = $data[$f+2];
				$leases[$l]['start'] .= " " . $data[$f+3];
				$f = $f+3;
				break;
			case "ends":
				$leases[$l]['end'] = $data[$f+2];
				$leases[$l]['end'] .= " " . $data[$f+3];
				$f = $f+3;
				break;
			case "tstp":
				$f = $f+3;
				break;
			case "tsfp":
				$f = $f+3;
				break;
			case "atsfp":
				$f = $f+3;
				break;
			case "cltt":
				$f = $f+3;
				break;
			case "binding":
				switch ($data[$f+2]) {
					case "active":
						$leases[$l]['act'] = "active";
						break;
					case "free":
						$leases[$l]['act'] = "expired";
						$leases[$l]['online'] = "offline";
						break;
					case "backup":
						$leases[$l]['act'] = "reserved";
						$leases[$l]['online'] = "offline";
						break;
				}
				$f = $f+1;
				break;
			case "next":
				/* skip the next binding statement */
				$f = $f+3;
				break;
			case "rewind":
				/* skip the rewind binding statement */
				$f = $f+3;
				break;
			case "hardware":
				$leases[$l]['mac'] = $data[$f+2];
				/* check if it's online and the lease is active */
				if ($leases[$l]['act'] == "active") {
					$online = exec("/usr/sbin/arp -an |/usr/bin/awk '/{$leases[$l]['ip']}/ {print}'|wc -l");
					if ($online == 1) {
						$leases[$l]['online'] = 'online';
					} else {
						$leases[$l]['online'] = 'offline';
					}
				}
				$f = $f+2;
				break;
			case "client-hostname":
				if ($data[$f+1] <> "") {
					$leases[$l]['hostname'] = preg_replace('/"/', '', $data[$f+1]);
				} else {
					$hostname = gethostbyaddr($leases[$l]['ip']);
					if ($hostname <> "") {
						$leases[$l]['hostname'] = $hostname;
					}
				}
				$f = $f+1;
				break;
			case "uid":
				$f = $f+1;
				break;
		}
		$f++;
	}
	$l++;
	$i++;
}

/* remove duplicate items by mac address */
if (count($leases) > 0) {
	$leases = remove_duplicate($leases, "ip");
}

if (count($pools) > 0) {
	$pools = remove_duplicate($pools, "name");
	asort($pools);
}

// Put this in an easy to use form
$dhcpmac = array();
$dhcpip = array();

foreach ($leases as $value) {
	$dhcpmac[$value['mac']] = $value['hostname'];
	$dhcpip[$value['ip']] = $value['hostname'];
}

exec("/usr/sbin/arp -an", $rawdata);

$i = 0;

/* if list */
$ifdescrs = get_configured_interface_with_descr();

foreach ($ifdescrs as $key => $interface) {
	$thisif = convert_friendly_interface_to_real_interface_name($key);
	if (!empty($thisif)) {
		$hwif[$thisif] = $interface;
	}
}

$data = array();
foreach ($rawdata as $line) {
	$elements = explode(' ', $line, 7);
	$arpent = array();
	$arpent['ip'] = trim(str_replace(array('(', ')'), '', $elements[1]));
	$arpent['mac'] = trim($elements[3]);
	$arpent['interface'] = trim($elements[5]);
	$arpent['status'] = trim(substr($elements[6], 0, strrpos($elements[6], ' ')));
	$arpent['linktype'] = trim(str_replace(array('[', ']'), '', strrchr($elements[6], ' ')));
	$data[] = $arpent;
}

function _getHostName($mac, $ip) {
	global $dhcpmac, $dhcpip;

	if ($dhcpmac[$mac]) {
		return $dhcpmac[$mac];
	} else if ($dhcpip[$ip]) {
		return $dhcpip[$ip];
	} else {
		exec("host -W 1 " . escapeshellarg($ip), $output);
		if (preg_match('/.*pointer ([A-Za-z_0-9.-]+)\..*/', $output[0], $matches)) {
			if ($matches[1] <> $ip) {
				return $matches[1];
			}
		}
	}
	return "";
}

$pgtitle = array(gettext("Diagnostics"), gettext("ARP Table"));
include("head.inc");

// Handle save msg if defined
if ($savemsg) {
	print_info_box(htmlentities($savemsg), $savemsgtype);
}
?>

<!-- On modern hardware the table will load so fast you may never see this! -->
<div id="loading">
	<?= gettext(" Loading, please wait...")?>
</div>

<?php

// Flush buffers out to client so that they see Loading, please wait....
for ($i = 0; $i < ob_get_level(); $i++) {
	ob_end_flush();
}

ob_implicit_flush(1);

// Resolve hostnames and replace Z_ with "".  The intention
// is to sort the list by hostnames, alpha and then the non
// resolvable addresses will appear last in the list.
$dnsavailable=1;
$dns = trim(_getHostName("", "8.8.8.8"));
if ($dns == "") {
	$dns = trim(_getHostName("", "8.8.4.4"));
	if ($dns == "") {
		$dnsavailable = 0;
	}
}

foreach ($data as &$entry) {
	if ($dnsavailable) {
		$dns = trim(_getHostName($entry['mac'], $entry['ip']));
	} else {
		$dns="";
	}
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
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('ARP Table')?></h2></div>
	<div class="panel-body">

<div class="table-responsive">
	<table class="sortable-theme-bootstrap table table-striped table-hover" data-sortable>
		<thead>
			<tr>
				<th><?= gettext("Interface")?></th>
				<th><?= gettext("IP address")?></th>
				<th><?= gettext("MAC address")?></th>
				<th><?= gettext("Hostname")?></th>
				<th><?= gettext("Status")?></th>
				<th><?= gettext("Link Type")?></th>
				<th data-sortable="false"><?=gettext("Actions")?></th>
			</tr>
		</thead>
		<tbody>

<?php
		foreach ($data as $entry): ?>
			<tr>
				<td><?=$hwif[$entry['interface']]?></td>
				<td><?=$entry['ip']?></td>
				<td>
					<?=trim($entry['mac'])?>
				<?php
					$mac = trim($entry['mac']);
					$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);

					if (isset($mac_man[$mac_hi])) {
						print '<small>('. $mac_man[$mac_hi] .')</small>';
					}
	?>
				</td>
				<td><?=trim(str_replace("Z_ ", "", $entry['dnsresolve']))?></td>
				<td><?=ucfirst($entry['status'])?></td>
				<td><?=$entry['linktype']?></td>
				<td>
					<a class="fa fa-trash" title="<?=gettext('Delete arp cache entry')?>"	href="diag_arp.php?deleteentry=<?=$entry['ip']?>" usepost></a>
				</td>
			</tr>
		<?php endforeach?>
		</tbody>
	</table>
</div>

	</div>
</div>

<script type="text/javascript">
//<![CDATA[
// Clear the "loading" div once the page has loaded"
events.push(function() {
	$('#loading').empty();
});
//]]>
</script>

<div class="infoblock blockopen">
<?php
print_info_box(sprintf(gettext('Local IPv6 peers use %1$sNDP%2$s instead of ARP.'), '<a href="diag_ndp.php">', '</a>') . '<br />' .
   '<br />' . gettext('Permanent ARP entries are shown for local interfaces or static ARP entries.') .
   '<br />' . gettext('Normal dynamic ARP entries show a countdown timer until they will expire and then be re-checked.') .
   '<br />' . gettext('Incomplete ARP entries indicate that the target host has not yet replied to an ARP request.'), 'info', false);
?>
</div>

<?php
include("foot.inc");
?>
