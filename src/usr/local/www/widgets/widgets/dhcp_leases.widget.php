<?php
/*
	DHCP_leases.widget.php
	Modified by Dave Field
        (original by Bobby Earl @ www.bobbyearl.com) why reinvent the wheel ?
	Last Modified: 2015-08-09

Changes from Original:
* Added and included widgets/include/DHCP_leases.inc to provide a clickable link
    to DHCP Page
*  Added lease type, and online/offline statuses (online always for active dynamic
    leases, and actual status for dynamic leases
*  Added count for
*  Added count for static leases



Original File/Inspiration:

	status_dhcp_leases.php
	Copyright (C) 2004-2009 Scott Ullrich
	All rights reserved.

	originially part of m0n0wall (http://m0n0.ch/wall)
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
@require_once("/usr/local/www/widgets/include/dhcp_leases.inc");
@require_once("guiconfig.inc");
$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";

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
	while($f < $fcount) {
		switch($data[$f]) {
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

/* remove duplicate items by mac address */
if(count($leases) > 0) {
	$leases = remove_duplicate($leases,"ip");
}

$leases_count = count($leases);

if (count($pools) > 0) {
	$pools = remove_duplicate($pools, "name");
	asort($pools);
}

$static_count = 0;
$active_static_leases = 0;

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
				if (in_array(strtolower($slease['mac']), $arpdata_mac)) {
					$slease['online'] = $online_string;
					$active_static_leases++;
				} else {
					$slease['online'] = $offline_string;
				}
				$slease['staticmap_array_index'] = $idx;
				$leases[] = $slease;
				$static_count++;
			}
		}
	}
}

?>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th><?=gettext("IP address"); ?></th>
				<th><?=gettext("Hostname"); ?></th>
				<th><?=gettext("Description"); ?></th>
				<th><?=gettext("Online"); ?></th>
				<th><?=gettext("Lease Type"); ?></th>
			</tr>
		</thead>
		<tbody>
<?php
$dhcp_leases_subnet_counter = array(); //array to sum up # of leases / subnet
$iflist = get_configured_interface_with_descr(); //get interface descr for # of leases
$no_leases_displayed = true;
$active_dynamic_leases = 0;

foreach ($leases as $data) {
	if ($data['act'] != $active_string && $data['act'] != $static_string && $_GET['all'] != 1) {
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
		$active_dynamic_leases++;

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
				<td>
					<span class='hover' onclick="requestNmapScan('<?=$data['ip']?>')" style="cursor:pointer; white-space:nowrap;">
						<?=htmlspecialchars($data['ip'])?>
					</span>
				</td>
				<td><?=htmlspecialchars($data['hostname'])?></td>
				<td><?=htmlspecialchars($data['descr'])?></td>
                <td><?=$data['online']?></td>
				<td><?=$data['type']?></td>
			</tr>
<?
}
if ($no_leases_displayed):
?>
			<tr>
				<td><?=gettext("No leases to display")?></td>
			</tr>
<?php
endif;
?>
		</tbody>
	</table>
</div>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
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
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<tr>
			<td>
				<span style="float:left">Active Dynamic Leases: <?=$active_dynamic_leases?>/<?=$leases_count?></span>
				<span style="float:right">Active Static Leases: <?=$active_static_leases?>/<?=$static_count?></span>
			</td>
		</tr>
	</table>
</div>
<?php if($leases == 0){ ?>
<p><strong><?=gettext("No leases file found. Is the DHCP server active"); ?>?</strong></p>
<?php } ?>
