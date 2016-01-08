<?php
/*
	status_dhcpv6_leases.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2011 Seth Mos
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
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-status-dhcpv6leases
##|*NAME=Status: DHCPv6 leases
##|*DESCR=Allow access to the 'Status: DHCPv6 leases' page.
##|*MATCH=status_dhcpv6_leases.php*
##|-PRIV

require("guiconfig.inc");
require_once("config.inc");

$pgtitle = array(gettext("Status"), gettext("DHCPv6 leases"));
$shortcut_section = "dhcp6";

$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases";

if (($_GET['deleteip']) && (is_ipaddr($_GET['deleteip']))) {
	/* Stop DHCPD */
	killbyname("dhcpd");

	/* Read existing leases */
	$leases_contents = explode("\n", file_get_contents($leasesfile));
	$newleases_contents = array();
	$i = 0;
	while ($i < count($leases_contents)) {
		/* Find the lease(s) we want to delete */
		if ($leases_contents[$i] == "  iaaddr {$_GET['deleteip']} {") {
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
	header("Location: status_dhcpv6_leases.php?all={$_GET['all']}");
}

// Load MAC-Manufacturer table
$mac_man = load_mac_manufacturer_table();

include("head.inc");

function leasecmp($a, $b) {
	return strcmp($a[$_GET['order']], $b[$_GET['order']]);
}

function adjust_gmt($dt) {
	global $config;

	$dhcpv6leaseinlocaltime == "no";
	if (is_array($config['dhcpdv6'])) {
		$dhcpdv6 = $config['dhcpdv6'];
		foreach ($dhcpdv6 as $dhcpv6leaseinlocaltime) {
			$dhcpv6leaseinlocaltime = $dhcpv6leaseinlocaltime['dhcpv6leaseinlocaltime'];
			if ($dhcpv6leaseinlocaltime == "yes") {
				break;
			}
		}
	}

	$timezone = $config['system']['timezone'];
	$ts = strtotime($dt . " GMT");
	if ($dhcpv6leaseinlocaltime == "yes") {
		$this_tz = new DateTimeZone($timezone);
		$dhcp_lt = new DateTime(strftime("%I:%M:%S%p", $ts), $this_tz);
		$offset = $this_tz->getOffset($dhcp_lt);
		$ts = $ts + $offset;
		return strftime("%Y/%m/%d %I:%M:%S%p", $ts);
	} else {
		return strftime("%Y/%m/%d %H:%M:%S", $ts);
	}
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
			} elseif (is_numeric($n)) {
				$parsed_duid[] = sprintf("%02x", octdec(substr($duid_string, $i+1, 3)));
				$i += 3;
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

/* this pattern sticks comments into a single array item */
$cleanpattern = "'{ gsub(\"^#.*\", \"\");} { gsub(\"^server-duid.*\", \"\");} { gsub(\";$\", \"\"); print;}'";
/* We then split the leases file by } */
$splitpattern = "'BEGIN { RS=\"}\";} {for (i=1; i<=NF; i++) printf \"%s \", \$i; printf \"}\\n\";}'";

/* stuff the leases file in a proper format into a array by line */
exec("/bin/cat {$leasesfile} | {$awk} {$cleanpattern} | {$awk} {$splitpattern} | /usr/bin/grep '^ia-.. '", $leases_content);
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
				$entry['type'] = "dynamic";
				$f = $f+2;
				break;
			case "iaaddr":
				$entry['ip'] = $data[$f+1];
				$entry['type'] = "dynamic";
				if (in_array($entry['ip'], array_keys($ndpdata))) {
					$entry['online'] = 'online';
				} else {
					$entry['online'] = 'offline';
				}
				$f = $f+2;
				break;
			case "iaprefix":
				$is_prefix = true;
				$entry['prefix'] = $data[$f+1];
				$entry['type'] = "dynamic";
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
						$entry['act'] = "active";
						break;
					case "free":
						$entry['act'] = "expired";
						$entry['online'] = "offline";
						break;
					case "backup":
						$entry['act'] = "reserved";
						$entry['online'] = "offline";
						break;
					case "released":
						$entry['act'] = "released";
						$entry['online'] = "offline";
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
		$mappings[$entry['iaid'] . $entry['duid']] = $entry['ip'];
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
			$slease['act'] = "static";
			if (in_array($slease['ip'], array_keys($ndpdata))) {
				$slease['online'] = 'online';
			} else {
				$slease['online'] = 'offline';
			}

			$leases[] = $slease;
		}
	}
}

if ($_GET['order']) {
	usort($leases, "leasecmp");
}

/* only print pool status when we have one */
if (count($pools) > 0) {
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Pool status')?></h2></div>
	<div class="panel-body">
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

if (empty($leases)) {
	print '<div class="alert alert-warning" role="alert">'. gettext("No leases file found. Is the DHCP server active?") .'</div>';
}

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Leases')?></h2></div>
	<div class="panel-body">
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
			</tr>
		</thead>
		<tbody>
<?php
foreach ($leases as $data):
	if ($data['act'] != "active" && $data['act'] != "static" && $_GET['all'] != 1) {
		continue;
	}

	if ($data['act'] == 'active') {
		$icon = 'fa-check-circle-o';
	} elseif ($data['act'] == 'expired') {
		$icon = 'fa-ban';
	} else {
		$icon = 'fa-times-circle-o';
	}

	if ($data['act'] == "static") {
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
<?php if ($data['type'] != "static"):?>
				<td><?=adjust_gmt($data['start'])?></td>
				<td><?=adjust_gmt($data['end'])?></td>
<?php else: ?>
				<td>n/a</td>
				<td>n/a</td>
<?php endif; ?>
				<td><?=$data['online']?></td>
				<td><?=$data['act']?></td>
				<td>
<?php if ($data['type'] == "dynamic"): ?>
					<a <a class="fa fa-plus-square-o" title="<?=gettext("Add static mapping")?>" href="services_dhcpv6_edit.php?if=<?=$data['if']?>&amp;duid=<?=$data['duid']?>&amp;hostname=<?=htmlspecialchars($data['hostname'])?>"></a>
<?php endif; ?>
					<a class="fa fa-plus-square" title="<?=gettext("Add WOL mapping")?>" href="services_wol_edit.php?if=<?=$data['if']?>&amp;mac=<?=$data['mac']?>&amp;descr=<?=htmlentities($data['hostname'])?>"></a>
<?php if ($data['type'] == "dynamic" && $data['online'] != "online"):?>
					<a class="fa fa-trash" title="<?=gettext('Delete lease')?>"	href="status_dhcpv6_leases.php?deleteip=<?=$data['ip']?>&amp;all=<?=intval($_GET['all'])?>"></a>
<?php endif; ?>
				</td>
<?php endforeach; ?>
			</tr>
		</tbody>
		</table>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Delegated Prefixes')?></h2></div>
	<div class="panel-body">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
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
	if ($data['act'] != "active" && $data['act'] != "static" && $_GET['all'] != 1) {
		continue;
	}

	if ($data['act'] == 'active') {
		$icon = 'fa-check-circle-o';
	} elseif ($data['act'] == 'expired') {
		$icon = 'fa-ban';
	} else {
		$icon = 'fa-times-circle-o';
	}

	if ($data['act'] == "static") {
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
<?php if ($mappings[$data['iaid'] . $data['duid']]): ?>
					<br />
					<?=gettext('Routed To')?>: <?=$mappings[$data['iaid'] . $data['duid']]?>
<?php endif; ?>
				</td>
				<td><?=$data['iaid']?></td>
				<td><?=$data['duid']?></td>
<?php if ($data['type'] != "static"):?>
				<td><?=adjust_gmt($data['start'])?></td>
				<td><?=adjust_gmt($data['end'])?></td>
<?php else: ?>
				<td>n/a</td>
				<td>n/a</td>
<?php endif; ?>
				<td><?=$data['act']?></td>
<?php endforeach; ?>
			</tr>
		</tbody>
		</table>
	</div>
</div>

<?php if ($_GET['all']): ?>
	<a class="btn btn-default" href="status_dhcpv6_leases.php?all=0"><?=gettext("Show active and static leases only")?></a>
<?php else: ?>
	<a class="btn btn-default" href="status_dhcpv6_leases.php?all=1"><?=gettext("Show all configured leases")?></a>
<?php endif;

include("foot.inc");
