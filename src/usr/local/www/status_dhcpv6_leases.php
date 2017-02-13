<?php
/*
 * status_dhcpv6_leases.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2011 Seth Mos
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
##|*IDENT=page-status-dhcpv6leases
##|*NAME=Status: DHCPv6 leases
##|*DESCR=Allow access to the 'Status: DHCPv6 leases' page.
##|*MATCH=status_dhcpv6_leases.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("config.inc");

$pgtitle = array(gettext("Status"), gettext("DHCPv6 Leases"));
$shortcut_section = "dhcp6";

$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases";

if (($_POST['deleteip']) && (is_ipaddr($_POST['deleteip']))) {
	/* Stop DHCPD */
	killbyname("dhcpd");

	/* Read existing leases */
	$leases_contents = explode("\n", file_get_contents($leasesfile));
	$newleases_contents = array();
	$i = 0;
	while ($i < count($leases_contents)) {
		/* Find the lease(s) we want to delete */
		if ($leases_contents[$i] == "  iaaddr {$_POST['deleteip']} {") {
			/* The iaaddr line is two lines down from the start of the lease, so remove those two lines. */
			array_pop($newleases_contents);
			array_pop($newleases_contents);
			/* Skip to the end of the lease declaration */
			do {
				$i++;
			} while ($leases_contents[$i] != "}");
		} else {
			/* It's a line we want to keep, copy it over. */
			$newleases_contents[] = $leases_contents[$i];
		}
		$i++;
	}

	/* Write out the new leases file */
	$fd = fopen($leasesfile, 'w');
	fwrite($fd, implode("\n", $newleases_contents));
	fclose($fd);

	/* Restart DHCP Service */
	services_dhcpd_configure();
	header("Location: status_dhcpv6_leases.php?all={$_REQUEST['all']}");
}

// Load MAC-Manufacturer table
$mac_man = load_mac_manufacturer_table();

include("head.inc");

function leasecmp($a, $b) {
	return strcmp($a[$_REQUEST['order']], $b[$_REQUEST['order']]);
}

function adjust_gmt($dt) {
	global $config;

	$dhcpv6leaseinlocaltime = "no";
	if (is_array($config['dhcpdv6'])) {
		$dhcpdv6 = $config['dhcpdv6'];
		foreach ($dhcpdv6 as $dhcpdv6params) {
			$dhcpv6leaseinlocaltime = $dhcpdv6params['dhcpv6leaseinlocaltime'];
			if ($dhcpv6leaseinlocaltime == "yes") {
				break;
			}
		}
	}

	if ($dhcpv6leaseinlocaltime == "yes") {
		$ts = strtotime($dt . " GMT");
		if ($ts !== false) {
			return strftime("%Y/%m/%d %H:%M:%S", $ts);
		}
	}
	/* If we did not need to convert to local time or the conversion failed, just return the input. */
	return $dt;
}

function remove_duplicate($array, $field) {
	foreach ($array as $sub) {
		$cmp[] = $sub[$field];
	}
	$unique = array_unique(array_reverse($cmp, true));
	foreach ($unique as $k => $rien) {
		$new[] = $array[$k];
	}
	return $new;
}

function parse_duid($duid_string) {
	$parsed_duid = array();
	for ($i = 0; $i < strlen($duid_string); $i++) {
		$s = substr($duid_string, $i, 1);
		if ($s == '\\') {
			$n = substr($duid_string, $i+1, 1);
			if (($n == '\\') || ($n == '"')) {
				$parsed_duid[] = sprintf("%02x", ord($n));
				$i += 1;
			} else {
				$n = substr($duid_string, $i+1, 3);
				if (preg_match('/[0-3][0-7]{2}/', $n)) {
					$parsed_duid[] = sprintf("%02x", octdec($n));
					$i += 3;
				}
			}
		} else {
			$parsed_duid[] = sprintf("%02x", ord($s));
		}
	}
	$iaid = array_slice($parsed_duid, 0, 4);
	$duid = array_slice($parsed_duid, 4);
	return array($iaid, $duid);
}

$awk = "/usr/bin/awk";
$sed = "/usr/bin/sed";

/* Remove all lines except ia-.. blocks */
$cleanpattern = "'/^ia-.. /, /^}/ !d; s,;$,,; s,  *, ,g'";
/*                       |               |        |
 *                       |               |        |
 *                       |               |        -> Remove extra spaces
 *                       |               -> Remove ; from EOL
 *                       -> Delete all lines except blocks that start with ia-..
 *                          and end with }
 */

/* Join each block in single line */
$splitpattern = "'{printf $0}; $0 ~ /^\}/ {printf \"\\n\"}'";

if (is_file($leasesfile)) {
	/* stuff the leases file in a proper format into an array by line */
	exec("{$sed} {$cleanpattern} {$leasesfile} | {$awk} {$splitpattern}", $leases_content);
	$leasesfile_found = true;
} else {
	$leases_content = array();
	$leasesfile_found = false;
}

$leases_count = count($leases_content);
exec("/usr/sbin/ndp -an", $rawdata);
$ndpdata = array();
foreach ($rawdata as $line) {
	$elements = preg_split('/\s+/ ', $line);
	if ($elements[1] != "(incomplete)") {
		$ndpent = array();
		$ip = trim(str_replace(array('(', ')'), '', $elements[0]));
		$ndpent['mac'] = trim($elements[1]);
		$ndpent['interface'] = trim($elements[2]);
		$ndpdata[$ip] = $ndpent;
	}
}

$pools = array();
$leases = array();
$prefixes = array();
$mappings = array();
$i = 0;
$l = 0;
$p = 0;

// Translate these once so we don't do it over and over in the loops below.
$online_string = gettext("online");
$offline_string = gettext("offline");
$active_string = gettext("active");
$expired_string = gettext("expired");
$reserved_string = gettext("reserved");
$released_string = gettext("released");
$dynamic_string = gettext("dynamic");
$static_string = gettext("static");

// Put everything together again
while ($i < $leases_count) {
	$entry = array();
	/* split the line by space */
	$duid_split = array();
	preg_match('/ia-.. "(.*)" { (.*)/ ', $leases_content[$i], $duid_split);
	if (!empty($duid_split[1])) {
		$iaid_duid = parse_duid($duid_split[1]);
		$entry['iaid'] = hexdec(implode("", array_reverse($iaid_duid[0])));
		$entry['duid'] = implode(":", $iaid_duid[1]);
		$data = explode(" ", $duid_split[2]);
	} else {
		$data = explode(" ", $leases_content[$i]);
	}
	/* walk the fields */
	$f = 0;
	$fcount = count($data);
	/* with less then 12 fields there is nothing useful */
	if ($fcount < 12) {
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
			case "ia-pd":
				$is_prefix = true;
			case "ia-na":
				$entry['iaid'] = $tmp_iaid;
				$entry['duid'] = $tmp_duid;
				if ($data[$f+1][0] == '"') {
					$duid = "";
					/* FIXME: This needs a safety belt to prevent an infinite loop */
					while ($data[$f][strlen($data[$f])-1] != '"') {
						$duid .= " " . $data[$f+1];
						$f++;
					}
					$entry['duid'] = $duid;
				} else {
					$entry['duid'] = $data[$f+1];
				}
				$entry['type'] = $dynamic_string;
				$f = $f+2;
				break;
			case "iaaddr":
				$entry['ip'] = $data[$f+1];
				$entry['type'] = $dynamic_string;
				if (in_array($entry['ip'], array_keys($ndpdata))) {
					$entry['online'] = $online_string;
				} else {
					$entry['online'] = $offline_string;
				}
				$f = $f+2;
				break;
			case "iaprefix":
				$is_prefix = true;
				$entry['prefix'] = $data[$f+1];
				$entry['type'] = $dynamic_string;
				$f = $f+2;
				break;
			case "starts":
				$entry['start'] = $data[$f+2];
				$entry['start'] .= " " . $data[$f+3];
				$f = $f+3;
				break;
			case "ends":
				$entry['end'] = $data[$f+2];
				$entry['end'] .= " " . $data[$f+3];
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
				$entry['start'] = $data[$f+2];
				$entry['start'] .= " " . $data[$f+3];
				$f = $f+3;
				break;
			case "binding":
				switch ($data[$f+2]) {
					case "active":
						$entry['act'] = $active_string;
						break;
					case "free":
						$entry['act'] = $expired_string;
						$entry['online'] = $offline_string;
						break;
					case "backup":
						$entry['act'] = $reserved_string;
						$entry['online'] = $offline_string;
						break;
					case "released":
						$entry['act'] = $released_string;
						$entry['online'] = $offline_string;
				}
				$f = $f+1;
				break;
			case "next":
				/* skip the next binding statement */
				$f = $f+3;
				break;
			case "hardware":
				$f = $f+2;
				break;
			case "client-hostname":
				if ($data[$f+1] != "") {
					$entry['hostname'] = preg_replace('/"/', '', $data[$f+1]);
				} else {
					$hostname = gethostbyaddr($entry['ip']);
					if ($hostname != "") {
						$entry['hostname'] = $hostname;
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
	if ($is_prefix) {
		$prefixes[] = $entry;
	} else {
		$leases[] = $entry;
		$mappings[$entry['duid']] = $entry['ip'];
	}
	$l++;
	$i++;
	$is_prefix = false;
}

if (count($leases) > 0) {
	$leases = remove_duplicate($leases, "ip");
}

if (count($prefixes) > 0) {
	$prefixes = remove_duplicate($prefixes, "prefix");
}

if (count($pools) > 0) {
	$pools = remove_duplicate($pools, "name");
	asort($pools);
}

foreach ($config['interfaces'] as $ifname => $ifarr) {
	if (is_array($config['dhcpdv6'][$ifname]) &&
	    is_array($config['dhcpdv6'][$ifname]['staticmap'])) {
		foreach ($config['dhcpdv6'][$ifname]['staticmap'] as $static) {
			$slease = array();
			$slease['ip'] = $static['ipaddrv6'];
			$slease['type'] = "static";
			$slease['duid'] = $static['duid'];
			$slease['start'] = "";
			$slease['end'] = "";
			$slease['hostname'] = htmlentities($static['hostname']);
			$slease['act'] = $static_string;
			if (in_array($slease['ip'], array_keys($ndpdata))) {
				$slease['online'] = $online_string;
			} else {
				$slease['online'] = $offline_string;
			}

			$leases[] = $slease;
		}
	}
}

if ($_REQUEST['order']) {
	usort($leases, "leasecmp");
}

/* only print pool status when we have one */
if (count($pools) > 0) {
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Pool Status')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th><?=gettext("Failover Group")?></a></th>
				<th><?=gettext("My State")?></a></th>
				<th><?=gettext("Since")?></a></th>
				<th><?=gettext("Peer State")?></a></th>
				<th><?=gettext("Since")?></a></th>
			</tr>
		</thead>
		<tbody>
<?php foreach ($pools as $data):?>
			<tr>
				<td><?=$data['name']?></td>
				<td><?=$data['mystate']?></td>
				<td><?=adjust_gmt($data['mydate'])?></td>
				<td><?=$data['peerstate']?></td>
				<td><?=adjust_gmt($data['peerdate'])?></td>
			</tr>
<?php endforeach; ?>
		</tbody>
		</table>
	</div>
</div>
<?php
/* only print pool status when we have one */
}

if (!$leasesfile_found) {
	print_info_box(gettext("No leases file found. Is the DHCPv6 server active?"), 'warning', false);
}

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Leases')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th><!-- icon --></th>
				<th><?=gettext("IPv6 address")?></th>
				<th><?=gettext("IAID")?></th>
				<th><?=gettext("DUID")?></th>
				<th><?=gettext("MAC address")?></th>
				<th><?=gettext("Hostname")?></th>
				<th><?=gettext("Start")?></th>
				<th><?=gettext("End")?></th>
				<th><?=gettext("Online")?></th>
				<th><?=gettext("Lease Type")?></th>
				<th><?=gettext("Actions")?></th>
			</tr>
		</thead>
		<tbody>
<?php
foreach ($leases as $data):
	if ($data['act'] != $active_string && $data['act'] != $static_string && $_REQUEST['all'] != 1) {
		continue;
	}

	if ($data['act'] == $active_string) {
		/* Active DHCP Lease */
		$icon = 'fa-check-circle-o';
	} elseif ($data['act'] == $expired_string) {
		/* Expired DHCP Lease */
		$icon = 'fa-ban';
	} else {
		/* Static Mapping */
		$icon = 'fa-user';
	}

	if ($data['act'] == $static_string) {
		foreach ($config['dhcpdv6'] as $dhcpif => $dhcpifconf) {
			if (is_array($dhcpifconf['staticmap'])) {
				foreach ($dhcpifconf['staticmap'] as $staticent) {
					if ($data['ip'] == $staticent['ipaddr']) {
						$data['if'] = $dhcpif;
						break;
					}
				}
			}
			/* exit as soon as we have an interface */
			if ($data['if'] != "") {
				break;
			}
		}
	} else {
		$data['if'] = convert_real_interface_to_friendly_interface_name(guess_interface_from_ip($data['ip']));
	}

	$mac = trim($ndpdata[$data['ip']]['mac']);
	$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
?>
			<tr>
				<td><i class="fa <?=$icon?>"></i></td>
				<td><?=$data['ip']?></td>
				<td><?=$data['iaid']?></td>
				<td><?=$data['duid']?></td>
				<td>
					<?=$mac?>

					<?php if (isset($mac_man[$mac_hi])):?>
						(<?=$mac_man[$mac_hi]?>)
					<?php endif; ?>
				</td>
				<td><?=htmlentities($data['hostname'])?></td>
<?php if ($data['type'] != $static_string):?>
				<td><?=adjust_gmt($data['start'])?></td>
				<td><?=adjust_gmt($data['end'])?></td>
<?php else: ?>
				<td>n/a</td>
				<td>n/a</td>
<?php endif; ?>
				<td><?=$data['online']?></td>
				<td><?=$data['act']?></td>
				<td>
<?php if ($data['type'] == $dynamic_string): ?>
					<a class="fa fa-plus-square-o" title="<?=gettext("Add static mapping")?>" href="services_dhcpv6_edit.php?if=<?=$data['if']?>&amp;duid=<?=$data['duid']?>&amp;hostname=<?=htmlspecialchars($data['hostname'])?>"></a>
<?php endif; ?>
					<a class="fa fa-plus-square" title="<?=gettext("Add WOL mapping")?>" href="services_wol_edit.php?if=<?=$data['if']?>&amp;mac=<?=$data['mac']?>&amp;descr=<?=htmlentities($data['hostname'])?>"></a>
<?php if ($data['type'] == $dynamic_string && $data['online'] != $online_string):?>
					<a class="fa fa-trash" title="<?=gettext('Delete lease')?>"	href="status_dhcpv6_leases.php?deleteip=<?=$data['ip']?>&amp;all=<?=intval($_REQUEST['all'])?>" usepost></a>
<?php endif; ?>
				</td>
			</tr>
<?php endforeach; ?>
		</tbody>
		</table>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Delegated Prefixes')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th><!-- icon --></th>
				<th><?=gettext("IPv6 Prefix")?></th>
				<th><?=gettext("IAID")?></th>
				<th><?=gettext("DUID")?></th>
				<th><?=gettext("Start")?></th>
				<th><?=gettext("End")?></th>
				<th><?=gettext("State")?></th>
			</tr>
		</thead>
		<tbody>
<?php
foreach ($prefixes as $data):
	if ($data['act'] != $active_string && $data['act'] != $static_string && $_REQUEST['all'] != 1) {
		continue;
	}

	if ($data['act'] == $active_string) {
		$icon = 'fa-check-circle-o';
	} elseif ($data['act'] == $expired_string) {
		$icon = 'fa-ban';
	} else {
		$icon = 'fa-times-circle-o';
	}

	if ($data['act'] == $static_string) {
		foreach ($config['dhcpdv6'] as $dhcpif => $dhcpifconf) {
			if (is_array($dhcpifconf['staticmap'])) {
				foreach ($dhcpifconf['staticmap'] as $staticent) {
					if ($data['ip'] == $staticent['ipaddr']) {
						$data['if'] = $dhcpif;
						break;
					}
				}
			}
			/* exit as soon as we have an interface */
			if ($data['if'] != "") {
				break;
			}
		}
	} else {
		$data['if'] = convert_real_interface_to_friendly_interface_name(guess_interface_from_ip($data['ip']));
	}

	 {
		$dip = "";
	}
?>
			<tr>
				<td><i class="fa <?=$icon?>"></i></td>
				<td>
					<?=$data['prefix']?>
<?php if ($mappings[$data['duid']]): ?>
					<br />
					<?=gettext('Routed To')?>: <?=$mappings[$data['duid']]?>
<?php endif; ?>
				</td>
				<td><?=$data['iaid']?></td>
				<td><?=$data['duid']?></td>
<?php if ($data['type'] != $static_string):?>
				<td><?=adjust_gmt($data['start'])?></td>
				<td><?=adjust_gmt($data['end'])?></td>
<?php else: ?>
				<td>n/a</td>
				<td>n/a</td>
<?php endif; ?>
				<td><?=$data['act']?></td>
			</tr>
<?php endforeach; ?>
		</tbody>
		</table>
	</div>
</div>

<?php if ($_REQUEST['all']): ?>
	<a class="btn btn-info" href="status_dhcpv6_leases.php?all=0"><i class="fa fa-minus-circle icon-embed-btn"></i><?=gettext("Show active and static leases only")?></a>
<?php else: ?>
	<a class="btn btn-info" href="status_dhcpv6_leases.php?all=1"><i class="fa fa-plus-circle icon-embed-btn"></i><?=gettext("Show all configured leases")?></a>
<?php endif;

include("foot.inc");
