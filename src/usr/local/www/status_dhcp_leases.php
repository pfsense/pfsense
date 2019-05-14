<?php
/*
 * status_dhcp_leases.php
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
##|*IDENT=page-status-dhcpleases
##|*NAME=Status: DHCP leases
##|*DESCR=Allow access to the 'Status: DHCP leases' page.
##|*MATCH=status_dhcp_leases.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("config.inc");

$pgtitle = array(gettext("Status"), gettext("DHCP Leases"));
$shortcut_section = "dhcp";

$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";

if (($_POST['deleteip']) && (is_ipaddr($_POST['deleteip']))) {
	/* Stop DHCPD */
	killbyname("dhcpd");

	/* Read existing leases */
	/* $leases_contents has the lines of the file, including the newline char at the end of each line. */
	$leases_contents = file($leasesfile);
	$newleases_contents = array();
	$i = 0;
	while ($i < count($leases_contents)) {
		/* Find the lease(s) we want to delete */
		if ($leases_contents[$i] == "lease {$_POST['deleteip']} {\n") {
			/* Skip to the end of the lease declaration */
			do {
				$i++;
			} while ($leases_contents[$i] != "}\n");
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
	header("Location: status_dhcp_leases.php?all={$_REQUEST['all']}");
}

// Load MAC-Manufacturer table
$mac_man = load_mac_manufacturer_table();

include("head.inc");

function leasecmp($a, $b) {
	return strcmp($a[$_REQUEST['order']], $b[$_REQUEST['order']]);
}

function adjust_gmt($dt) {
	global $config;
	$dhcpd = $config['dhcpd'];
	foreach ($dhcpd as $dhcpditem) {
		$dhcpleaseinlocaltime = $dhcpditem['dhcpleaseinlocaltime'];
		if ($dhcpleaseinlocaltime == "yes") {
			break;
		}
	}
	if ($dhcpleaseinlocaltime == "yes") {
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

$awk = "/usr/bin/awk";
/* this pattern sticks comments into a single array item */
$cleanpattern = "'{ gsub(\"#.*\", \"\");} { gsub(\";\", \"\"); print;}'";
/* We then split the leases file by } */
$splitpattern = "'BEGIN { RS=\"}\";} {for (i=1; i<=NF; i++) printf \"%s \", \$i; printf \"}\\n\";}'";

/* stuff the leases file in a proper format into a array by line */
exec("/bin/cat {$leasesfile} | {$awk} {$cleanpattern} | {$awk} {$splitpattern}", $leases_content);
$leases_count = count($leases_content);
exec("/usr/sbin/arp -an", $rawdata);
$arpdata_ip = array();
$arpdata_mac = array();
foreach ($rawdata as $line) {
	$elements = explode(' ', $line);
	if ($elements[3] != "(incomplete)") {
		$arpent = array();
		$arpdata_ip[] = trim(str_replace(array('(', ')'), '', $elements[1]));
		$arpdata_mac[] = strtolower(trim($elements[3]));
	}
}
unset($rawdata);
$pools = array();
$leases = array();
$i = 0;
$l = 0;
$p = 0;

// Translate these once so we don't do it over and over in the loops below.
$online_string = gettext("online");
$offline_string = gettext("offline");
$active_string = gettext("active");
$expired_string = gettext("expired");
$reserved_string = gettext("reserved");
$dynamic_string = gettext("dynamic");
$static_string = gettext("static");

// Put everything together again
foreach ($leases_content as $lease) {
	/* split the line by space */
	$data = explode(" ", $lease);
	/* walk the fields */
	$f = 0;
	$fcount = count($data);
	/* with less than 20 fields there is nothing useful */
	if ($fcount < 20) {
		$i++;
		continue;
	}
	while ($f < $fcount) {
		switch ($data[$f]) {
			case "failover":
				$pools[$p]['name'] = trim($data[$f+2], '"');
				$pools[$p]['name'] = "{$pools[$p]['name']} (" . convert_friendly_interface_to_friendly_descr(substr($pools[$p]['name'], 5)) . ")";
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
				$leases[$l]['type'] = $dynamic_string;
				$f = $f+2;
				break;
			case "starts":
				$leases[$l]['start'] = $data[$f+2];
				$leases[$l]['start'] .= " " . $data[$f+3];
				$f = $f+3;
				break;
			case "ends":
				if ($data[$f+1] == "never") {
					// Quote from dhcpd.leases(5) man page:
					// If a lease will never expire, date is never instead of an actual date.
					$leases[$l]['end'] = gettext("Never");
					$f = $f+1;
				} else {
					$leases[$l]['end'] = $data[$f+2];
					$leases[$l]['end'] .= " " . $data[$f+3];
					$f = $f+3;
				}
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
						$leases[$l]['act'] = $active_string;
						break;
					case "free":
						$leases[$l]['act'] = $expired_string;
						$leases[$l]['online'] = $offline_string;
						break;
					case "backup":
						$leases[$l]['act'] = $reserved_string;
						$leases[$l]['online'] = $offline_string;
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
				if (in_array($leases[$l]['ip'], $arpdata_ip)) {
					$leases[$l]['online'] = $online_string;
				} else {
					$leases[$l]['online'] = $offline_string;
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
	/* slowly chisel away at the source array */
	array_shift($leases_content);
}
/* remove the old array */
unset($lease_content);

/* remove duplicate items by mac address */
if (count($leases) > 0) {
	$leases = remove_duplicate($leases, "ip");
}

if (count($pools) > 0) {
	$pools = remove_duplicate($pools, "name");
	asort($pools);
}

$got_cid = false;

foreach ($config['interfaces'] as $ifname => $ifarr) {
	if (is_array($config['dhcpd'][$ifname]) &&
	    is_array($config['dhcpd'][$ifname]['staticmap'])) {
		foreach ($config['dhcpd'][$ifname]['staticmap'] as $idx => $static) {
			if (!empty($static['mac']) || !empty($static['cid'])) {
				$slease = array();
				$slease['ip'] = $static['ipaddr'];
				$slease['type'] = $static_string;
				if (!empty($static['cid'])) {
					$slease['cid'] = $static['cid'];
					$got_cid = true;
				}
				$slease['mac'] = $static['mac'];
				$slease['if'] = $ifname;
				$slease['start'] = "";
				$slease['end'] = "";
				$slease['hostname'] = $static['hostname'];
				$slease['descr'] = $static['descr'];
				$slease['act'] = $static_string;
				$slease['online'] = in_array(strtolower($slease['mac']), $arpdata_mac) ? $online_string : $offline_string;
				$slease['staticmap_array_index'] = $idx;
				$leases[] = $slease;
			}
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
				<td><?=htmlspecialchars($data['name'])?></td>
				<td><?=htmlspecialchars($data['mystate'])?></td>
				<td><?=htmlspecialchars(adjust_gmt($data['mydate']))?></td>
				<td><?=htmlspecialchars($data['peerstate'])?></td>
				<td><?=htmlspecialchars(adjust_gmt($data['peerdate']))?></td>
			</tr>
<?php endforeach; ?>
		</tbody>
		</table>
	</div>
</div>
<?php
/* only print pool status when we have one */
}
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Leases')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><!-- icon --></th>
					<th><?=gettext("IP address")?></th>
					<th><?=gettext("MAC address")?></th>
<?php
/* only make CID column when we have one */
if ($got_cid) {
?>
					<th><?=gettext("Client Id")?></th>
<?php
}
?>
					<th><?=gettext("Hostname")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Start")?></th>
					<th><?=gettext("End")?></th>
					<th><?=gettext("Online")?></th>
					<th><?=gettext("Lease Type")?></th>
					<th data-sortable="false"><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
<?php
$dhcp_leases_subnet_counter = array(); //array to sum up # of leases / subnet
$iflist = get_configured_interface_with_descr(); //get interface descr for # of leases
$no_leases_displayed = true;

foreach ($leases as $data):
	if ($data['act'] != $active_string && $data['act'] != $static_string && $_REQUEST['all'] != 1) {
		continue;
	}

	$no_leases_displayed = false;

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

	if ($data['act'] != $static_string) {
		foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
			if (!is_array($dhcpifconf['range'])) {
				continue;
			}
			if (is_inrange_v4($data['ip'], $dhcpifconf['range']['from'], $dhcpifconf['range']['to'])) {
				$data['if'] = $dhcpif;
				$dlskey = $dhcpif . "-" . $dhcpifconf['range']['from'];
				$dhcp_leases_subnet_counter[$dlskey]['dhcpif'] = $dhcpif;
				$dhcp_leases_subnet_counter[$dlskey]['from'] = $dhcpifconf['range']['from'];
				$dhcp_leases_subnet_counter[$dlskey]['to'] = $dhcpifconf['range']['to'];
				$dhcp_leases_subnet_counter[$dlskey]['count'] += 1;
				break;
			}

			// Check if the IP is in the range of any DHCP pools
			if (is_array($dhcpifconf['pool'])) {
				foreach ($dhcpifconf['pool'] as $dhcppool) {
					if (is_array($dhcppool['range'])) {
						if (is_inrange_v4($data['ip'], $dhcppool['range']['from'], $dhcppool['range']['to'])) {
							$data['if'] = $dhcpif;
							$dlskey = $dhcpif . "-" . $dhcppool['range']['from'];
							$dhcp_leases_subnet_counter[$dlskey]['dhcpif'] = $dhcpif;
							$dhcp_leases_subnet_counter[$dlskey]['from'] = $dhcppool['range']['from'];
							$dhcp_leases_subnet_counter[$dlskey]['to'] = $dhcppool['range']['to'];
							$dhcp_leases_subnet_counter[$dlskey]['count'] += 1;
							break 2;
						}
					}
				}
			}
		}
	}

	$mac = $data['mac'];
	$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
?>
				<tr>
					<td><i class="fa <?=$icon?>"></i></td>
					<td><?=htmlspecialchars($data['ip'])?></td>
					<td>
						<?=htmlspecialchars($mac)?>

						<?php if (isset($mac_man[$mac_hi])):?>
							(<?=htmlspecialchars($mac_man[$mac_hi])?>)
						<?php endif; ?>
					</td>
<?php
/* only make CID column when we have one */
if ($got_cid) {
?>
					<td><?=htmlspecialchars($data['cid'])?></td>
<?php
}
?>
					<td><?=htmlspecialchars($data['hostname'])?></td>
					<td><?=htmlspecialchars($data['descr'])?></td>
					<? if ($data['type'] != "static"): ?>
						<td><?=htmlspecialchars(adjust_gmt($data['start']))?></td>
						<td><?=htmlspecialchars(adjust_gmt($data['end']))?></td>
					<? else: ?>
						<td><?=gettext("n/a")?></td>
						<td><?=gettext("n/a")?></td>
					<? endif; ?>
					<td><?=htmlspecialchars($data['online'])?></td>
					<td><?=htmlspecialchars($data['act'])?></td>
					<td>
<?php if ($data['type'] == $dynamic_string): ?>
						<a class="fa fa-plus-square-o"	title="<?=gettext("Add static mapping")?>"	href="services_dhcp_edit.php?if=<?=htmlspecialchars($data['if'])?>&amp;mac=<?=htmlspecialchars($data['mac'])?>&amp;hostname=<?=htmlspecialchars($data['hostname'])?>"></a>
<?php else: ?>
						<a class="fa fa-pencil"	title="<?=gettext('Edit static mapping')?>"	href="services_dhcp_edit.php?if=<?=htmlspecialchars($data['if'])?>&amp;id=<?=htmlspecialchars($data['staticmap_array_index'])?>"></a>
<?php endif; ?>
						<a class="fa fa-plus-square" title="<?=gettext("Add WOL mapping")?>" href="services_wol_edit.php?if=<?=htmlspecialchars($data['if'])?>&amp;mac=<?=htmlspecialchars($data['mac'])?>&amp;descr=<?=htmlspecialchars($data['hostname'])?>"></a>
<?php if ($data['online'] != $online_string):?>
						<a class="fa fa-power-off" title="<?=gettext("Send WOL packet")?>" href="services_wol.php?if=<?=htmlspecialchars($data['if'])?>&amp;mac=<?=htmlspecialchars($data['mac'])?>" usepost></a>
<?php endif; ?>

<?php if ($data['type'] == $dynamic_string && $data['online'] != $online_string):?>
						<a class="fa fa-trash" title="<?=gettext('Delete lease')?>"	href="status_dhcp_leases.php?deleteip=<?=htmlspecialchars($data['ip'])?>&amp;all=<?=intval($_POST['all'])?>" usepost></a>
<?php endif; ?>
					</td>
				</tr>
<?php endforeach; ?>
<?php if ($no_leases_displayed): ?>
				<tr>
					<td></td>
					<td><?=gettext("No leases to display")?></td>
				</tr>
<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Leases in Use')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Interface")?></th>
					<th><?=gettext("Pool Start")?></th>
					<th><?=gettext("Pool End")?></th>
					<th><?=gettext("# of leases in use")?></th>
				</tr>
			</thead>
			<tbody>
<?php
if (count($dhcp_leases_subnet_counter)):
	ksort($dhcp_leases_subnet_counter);
	foreach ($dhcp_leases_subnet_counter as $listcounters):
?>
				<tr>
					<td><?=$iflist[$listcounters['dhcpif']]?></td>
					<td><?=$listcounters['from']?></td>
					<td><?=$listcounters['to']?></td>
					<td><?=$listcounters['count']?></td>
				</tr>
<?php
	endforeach;
else:
?>
				<tr>
					<td><?=gettext("No leases are in use")?></td>
				</tr>
<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($_REQUEST['all']): ?>
	<a class="btn btn-info" href="status_dhcp_leases.php?all=0"><i class="fa fa-minus-circle icon-embed-btn"></i><?=gettext("Show active and static leases only")?></a>
<?php else: ?>
	<a class="btn btn-info" href="status_dhcp_leases.php?all=1"><i class="fa fa-plus-circle icon-embed-btn"></i><?=gettext("Show all configured leases")?></a>
<?php endif;

include("foot.inc");
