<?php
/*
 * status_dhcpv6_leases.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
require_once("parser_dhcpv6_leases.inc");
require_once("util.inc");

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

if ($_POST['cleardhcpleases']) {
	killbyname("dhcpd");
	sleep(2);
	unlink_if_exists("{$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases*");

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

if (is_file($leasesfile)) {
	$leases_content = file_get_contents ($leasesfile);
	$leasesfile_found = true;
} else {
	$leases_content = array();
	$leasesfile_found = false;
}

$ndpdata = get_ndpdata ();
$pools = array();
$leases = array();
$prefixes = array();
$mappings = array();

// Translate these once so we don't do it over and over in the loops below.
$online_string = gettext("online");
$offline_string = gettext("offline");
$active_string = gettext("active");
$expired_string = gettext("expired");
$reserved_string = gettext("reserved");
$released_string = gettext("released");
$dynamic_string = gettext("dynamic");
$static_string = gettext("static");

$lang_pack = [ 'online' =>  $online_string, 'offline' => $offline_string,
               'active' =>  $active_string, 'expired' => $expired_string,
               'reserved' => $reserved_string, 'released' => $released_string,
               'dynamic' => $dynamic_string, 'static' =>  $static_string];
// Handle the content of the lease file - parser_dhcpv6_leases.inc
gui_parse_leases ($pools, $leases, $prefixes, $mappings, $leases_content,
		  $ndpdata, $lang_pack);

if (count($leases) > 0) {
	$leases = array_remove_duplicate($leases, "ip");
}

if (count($prefixes) > 0) {
	$prefixes = array_remove_duplicate($prefixes, "prefix");
}

if (count($pools) > 0) {
	$pools = array_remove_duplicate($pools, "name");
	asort($pools);
}

foreach ($config['interfaces'] as $ifname => $ifarr) {
	if (is_array($config['dhcpdv6'][$ifname]) &&
	    is_array($config['dhcpdv6'][$ifname]['staticmap'])) {
		foreach ($config['dhcpdv6'][$ifname]['staticmap'] as $static) {
			$slease = array();
			$slease['ip'] = merge_ipv6_delegated_prefix(get_interface_ipv6($ifname), $static['ipaddrv6'], get_interface_subnetv6($ifname));
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
<div class="panel panel-default" id="search-panel">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?=gettext('Search')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#search-panel_panel-body">
					<i class="fa fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="search-panel_panel-body" class="panel-body collapse in">
		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?=gettext("Search term")?>
			</label>
			<div class="col-sm-5"><input class="form-control" name="searchstr" id="searchstr" type="text"/></div>
			<div class="col-sm-2">
				<select id="where" class="form-control">
					<option value="1"><?=gettext("IP Address")?></option>
					<option value="2"><?=gettext("IAID")?></option>
					<option value="3"><?=gettext("DUID")?></option>
					<option value="4"><?=gettext("MAC Address")?></option>
					<option value="5"><?=gettext("Hostname")?></option>
					<option value="6"><?=gettext("Start")?></option>
					<option value="7"><?=gettext("End")?></option>
					<option value="8"><?=gettext("Online")?></option>
					<option value="9"><?=gettext("Lease Type")?></option>
					<option value="10" selected><?=gettext("All")?></option>
				</select>
			</div>
			<div class="col-sm-3">
				<a id="btnsearch" title="<?=gettext("Search")?>" class="btn btn-primary btn-sm"><i class="fa fa-search icon-embed-btn"></i><?=gettext("Search")?></a>
				<a id="btnclear" title="<?=gettext("Clear")?>" class="btn btn-info btn-sm"><i class="fa fa-undo icon-embed-btn"></i><?=gettext("Clear")?></a>
			</div>
			<div class="col-sm-10 col-sm-offset-2">
				<span class="help-block"><?=gettext('Enter a search string or *nix regular expression to filter entries.')?></span>
			</div>
		</div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Leases')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table statusdhcpv6leases table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
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
		<tbody id="leaselist">
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
					if ($data['ip'] == $staticent['ipaddrv6']) {
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
				<td><?=$mac?><?php if (isset($mac_man[$mac_hi])):?><br /><small>(<?=$mac_man[$mac_hi]?>)</small><?php endif; ?></td>
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
					<a class="fa fa-plus-square" title="<?=gettext("Add WOL mapping")?>" href="services_wol_edit.php?if=<?=$data['if']?>&amp;mac=<?=$mac?>&amp;descr=<?=htmlentities($data['hostname'])?>"></a>
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
		<table class="table statusdhcpv6prefixes table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th><!-- icon --></th>
				<th><?=gettext("IPv6 Prefix")?></th>
				<th><?=gettext("Routed To")?></th>
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
					if ($data['ip'] == $staticent['ipaddrv6']) {
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
?>
			<tr>
				<td><i class="fa <?=$icon?>"></i></td>
				<td><?=$data['prefix']?></td>
				<td><?php foreach ($mappings[$data['duid']] as $iaid => $iproute):?><?=$iproute?><br />IAID: <?=$iaid?><br /><?php endforeach; ?></td>
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
<?php endif; ?>
	<a class="btn btn-danger no-confirm" id="cleardhcp"><i class="fa fa-trash icon-embed-btn"></i><?=gettext("Clear all DHCPv6 leases")?></a>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Make these controls plain buttons
	$("#btnsearch").prop('type', 'button');
	$("#btnclear").prop('type', 'button');

	// Search for a term in the entry name and/or dn
	$("#btnsearch").click(function() {
		var searchstr = $('#searchstr').val().toLowerCase();
		var table = $("#leaselist");
		var where = $('#where').val();

		table.find('tr').each(function (i) {
			var $tds = $(this).find('td'),
				ipaddr   = $tds.eq(1).text().trim().toLowerCase();
				iaid     = $tds.eq(2).text().trim().toLowerCase(),
				duid     = $tds.eq(3).text().trim().toLowerCase();
				macaddr  = $tds.eq(4).text().trim().toLowerCase();
				hostname = $tds.eq(5).text().trim().toLowerCase();
				start    = $tds.eq(6).text().trim().toLowerCase();
				end      = $tds.eq(7).text().trim().toLowerCase();
				online   = $tds.eq(8).text().trim().toLowerCase();
				lease    = $tds.eq(9).text().trim().toLowerCase();

			regexp = new RegExp(searchstr);
			if (searchstr.length > 0) {
				if (!(regexp.test(ipaddr)   && ((where == 1) || (where == 10))) &&
				    !(regexp.test(iaid)     && ((where == 2) || (where == 10))) &&
				    !(regexp.test(duid)     && ((where == 3) || (where == 10))) &&
				    !(regexp.test(macaddr)  && ((where == 4) || (where == 10))) &&
				    !(regexp.test(hostname) && ((where == 5) || (where == 10))) &&
				    !(regexp.test(start)    && ((where == 6) || (where == 10))) &&
				    !(regexp.test(end)      && ((where == 7) || (where == 10))) &&
				    !(regexp.test(online)   && ((where == 8) || (where == 10))) &&
				    !(regexp.test(lease)    && ((where == 9) || (where == 10)))
				    ) {
					$(this).hide();
				} else {
					$(this).show();
				}
			} else {
				$(this).show();	// A blank search string shows all
			}
		});
	});

	// Clear the search term and unhide all rows (that were hidden during a previous search)
	$("#btnclear").click(function() {
		var table = $("#leaselist");

		$('#searchstr').val("");

		table.find('tr').each(function (i) {
			$(this).show();
		});
	});

	// Hitting the enter key will do the same as clicking the search button
	$("#searchstr").on("keyup", function (event) {
		if (event.keyCode == 13) {
			$("#btnsearch").get(0).click();
		}
	});

	$('#cleardhcp').click(function() {
		if (confirm("Are you sure you wish to clear all DHCPv6 leases?")) {
			postSubmit({cleardhcpleases: 'true'}, 'status_dhcpv6_leases.php');
		}
	});

});
//]]>
</script>

<?php include("foot.inc");
