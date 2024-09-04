<?php
/*
 * status_dhcpv6_leases.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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

require_once('guiconfig.inc');
require_once('config.inc');
require_once('parser_dhcpv6_leases.inc');
require_once('util.inc');

$pgtitle = [gettext('Status'), gettext('DHCPv6 Leases')];
$shortcut_section = 'dhcp6';
if (dhcp_is_backend('kea')) {
	$shortcut_section = 'kea-dhcp6';
}

if (dhcp_is_backend('isc')):
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
endif; /* dhcp_is_backend('isc') */

if (dhcp_is_backend('kea')):
if ($_POST['deleteip'] && is_ipaddrv6($_POST['deleteip'])) {
	system_del_kea6lease($_POST['deleteip']);
	header("Location: status_dhcpv6_leases.php?all={$_REQUEST['all']}");
}

if ($_POST['cleardhcpleases']) {
	system_clear_all_kea6leases();
	header("Location: status_dhcpv6_leases.php?all={$_REQUEST['all']}");
}
endif; /* dhcp_is_backend('kea') */

// Load MAC-Manufacturer table
$mac_man = load_mac_manufacturer_table();

include("head.inc");

function leasecmp($a, $b) {
	return strcmp($a[$_REQUEST['order']], $b[$_REQUEST['order']]);
}

function adjust_gmt($dt) {
	$dhcpv6leaseinlocaltime = "no";
	if (is_array(config_get_path('dhcpdv6'))) {
		$dhcpdv6 = config_get_path('dhcpdv6');
		foreach ($dhcpdv6 as $dhcpdv6params) {
			if (empty($dhcpdv6params)) {
				continue;
			}
			$dhcpv6leaseinlocaltime = $dhcpdv6params['dhcpv6leaseinlocaltime'];
			if ($dhcpv6leaseinlocaltime == "yes") {
				break;
			}
		}
	}

	if ($dhcpv6leaseinlocaltime == "yes") {
		$ts = strtotime($dt . " GMT");
		if ($ts !== false) {
			return date("Y/m/d H:i:s", $ts);
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

$ndpdata = get_ndpdata();
$pools = array();
$leases = array();
$prefixes = array();
$mappings = array();

// Translate these once so we don't do it over and over in the loops below.
$online_string = gettext("active/online");
$offline_string = gettext("idle/offline");
$active_string = gettext("active");
$expired_string = gettext("expired");
$reserved_string = gettext("reserved");
$released_string = gettext("released");
$dynamic_string = gettext("dynamic");
$static_string = gettext("static");

if (dhcp_is_backend('isc')):
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

foreach (config_get_path('interfaces', []) as $ifname => $ifarr) {
	foreach (config_get_path("dhcpdv6/{$ifname}/staticmap", []) as $static) {
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
endif; /* dhcp_is_backend('isc') */

display_isc_warning();

if (dhcp_is_backend('kea')):
$kea6leases = system_get_kea6leases();
$leases = $kea6leases['lease'];
endif; /* dhcp_is_backend('kea') */

?>
<div class="panel panel-default" id="search-panel">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?=gettext('Search')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#search-panel_panel-body">
					<i class="fa-solid fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="search-panel_panel-body" class="panel-body collapse in">
		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?=gettext('Search Term')?>
			</label>
			<div class="col-sm-5"><input class="form-control" name="searchstr" id="searchstr" type="text"/></div>
			<div class="col-sm-2">
				<select id="where" class="form-control">
					<option value="1" selected><?=gettext('All')?></option>
					<option value="2"><?=gettext('Lease Type')?></option>
					<option value="3"><?=gettext('Client Status')?></option>
					<option value="4"><?=gettext('IPv6 Address')?></option>
					<option value="5"><?=gettext('DHCP Unique Identifier (DUID)')?></option>
					<option value="6"><?=gettext('Identity Association Identifier (IAID)')?></option>
					<option value="7"><?=gettext('MAC Address')?></option>
					<option value="8"><?=gettext('Hostname')?></option>
					<option value="9"><?=gettext('Description')?></option>
					<option value="10"><?=gettext('Start')?></option>
					<option value="11"><?=gettext('End')?></option>

				</select>
			</div>
			<div class="col-sm-3">
				<a id="btnsearch" title="<?=gettext('Search')?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-search icon-embed-btn"></i><?=gettext("Search")?></a>
				<a id="btnclear" title="<?=gettext('Clear')?>" class="btn btn-info btn-sm"><i class="fa-solid fa-undo icon-embed-btn"></i><?=gettext("Clear")?></a>
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
					<th data-sortable="false"><!-- status icons --></th>
					<th><?=gettext('IPv6 Address')?></th>
					<th><?=gettext('DHCP Unique Identifier (DUID)')?></th>
					<th><?=gettext('Hostname')?></th>
					<th><?=gettext('Description')?></th>
					<th><?=gettext('Start')?></th>
					<th><?=gettext('End')?></th>
					<th data-sortable="false"><?=gettext('Actions')?></th>
				</tr>
			</thead>
			<tbody id="leaselist">
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
		$icon = 'fa-regular fa-circle-check';
	} elseif ($data['act'] == $expired_string) {
		/* Expired DHCP Lease */
		$icon = 'fa-solid fa-ban';
	} else {
		/* Static Mapping */
		$icon = 'fa-solid fa-user';
	}

	if ($data['act'] !== $static_string) {
		foreach (config_get_path('dhcpdv6', []) as $dhcpif => $dhcpifconf) {
			if (empty($dhcpifconf)) {
				continue;
			}

			if (!is_array($dhcpifconf['range']) || !isset($dhcpifconf['enable'])) {
				continue;
			}

			$data['if'] = convert_real_interface_to_friendly_interface_name(guess_interface_from_ip($data['ip']));

			if (!empty($data['if']) && is_inrange_v6($data['ip'], $dhcpifconf['range']['from'], $dhcpifconf['range']['to'])) {
				$dlskey = $data['if'] . '-' . $dhcpifconf['range']['from'];
				$dhcp_leases_subnet_counter[$dlskey]['dhcpif'] = $data['if'];
				$dhcp_leases_subnet_counter[$dlskey]['from'] = $dhcpifconf['range']['from'];
				$dhcp_leases_subnet_counter[$dlskey]['to'] = $dhcpifconf['range']['to'];
				$dhcp_leases_subnet_counter[$dlskey]['count'] += 1;
				break;
			}

			if (is_array($dhcpifconf['pool'])) {
				foreach ($dhcpifconf['pool'] as $dhcppool) {
					if (is_array($dhcppool['range'])) {
						if (!empty($data['if']) && is_inrange_v6($data['ip'], $dhcppool['range']['from'], $dhcppool['range']['to'])) {
							$dlskey = $data['if'] . '-' . $dhcpifconf['range']['from'];
							$dhcp_leases_subnet_counter[$dlskey]['dhcpif'] = $data['if'];
							$dhcp_leases_subnet_counter[$dlskey]['from'] = $dhcppool['from'];
							$dhcp_leases_subnet_counter[$dlskey]['to'] = $dhcppool['to'];
							$dhcp_leases_subnet_counter[$dlskey]['count'] += 1;
							break 2;
						}
					}
				}
			}
		}
	}

	$mac = trim($ndpdata[$data['ip']]['mac']);
	$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
?>
				<tr>
					<td>
						<i class="<?=$icon?> act" title="<?=htmlspecialchars($data['act'])?>"></i>
<?php if ($data['online'] === $online_string): ?>
						<i class="fa-solid fa-arrow-up text-success online" title="<?=htmlspecialchars($data['online'])?>"></i>
<?php else: ?>
						<i class="fa-solid fa-arrow-down online" title="<?=htmlspecialchars($data['online'])?>"></i>
<?php endif; ?>
					</td>
					<td><?=$data['ip']?></td>
					<td style="cursor: context-menu;" data-toggle="popover" data-container="body" data-trigger="hover focus" data-content="<?=gettext('DUID')?>: <span class=&quot;duid&quot;><?=$data['duid']?></span><?php if ($data['iaid']): ?><br /><?=gettext('IAID')?>: <span class=&quot;iaid&quot;><?=$data['iaid']?></span><?php endif; if ($mac): ?><br /><?=gettext('MAC Address')?>: <span class=&quot;mac&quot;><?=$mac?><?php if (isset($mac_man[$mac_hi])):?><br /><small>(<?=$mac_man[$mac_hi]?>)</small><?php endif; ?></span><?php endif; ?>" data-html="true" data-original-title="<?=gettext('DHCPv6 Client Information')?>"><?=$data['duid']?></td>
					<td><?=htmlentities($data['hostname'])?></td>
					<td><?=htmlspecialchars($data['descr'])?></td>
<?php if (dhcp_is_backend('isc') && ($data['type'] != $static_string)):?>
					<td><?=adjust_gmt($data['start'])?></td>
					<td><?=adjust_gmt($data['end'])?></td>
<?php elseif (dhcp_is_backend('kea') && ($data['type'] != $static_string)): ?>
					<td><?=$data['starts']?></td>
					<td><?=$data['ends']?></td>
<?php else: ?>
					<td><?=gettext('n/a')?></td>
					<td><?=gettext('n/a')?></td>
<?php endif; ?>
					<td>
<?php if ($data['type'] == $dynamic_string): ?>
						<a class="fa-regular fa-square-plus" title="<?=gettext('Add static mapping')?>" href="services_dhcpv6_edit.php?if=<?=$data['if']?>&amp;duid=<?=$data['duid']?>&amp;hostname=<?=htmlspecialchars($data['hostname'])?>"></a>
<?php endif; ?>
<?php if ($mac): /* we can only add a WOL mapping if MAC address is known */ ?>
						<a class="fa-solid fa-plus-square" title="<?=gettext('Add WOL mapping')?>" href="services_wol_edit.php?if=<?=$data['if']?>&amp;mac=<?=$mac?>&amp;descr=<?=htmlentities($data['hostname'])?>"></a>
<?php endif; ?>
<?php if ($data['type'] == $static_string): ?>
						<a class="fa-solid fa-pencil"	title="<?=gettext('Edit static mapping')?>" href="services_dhcpv6_edit.php?if=<?=htmlspecialchars($data['if'])?>&amp;id=<?=htmlspecialchars($data['staticmap_array_index'])?>"></a>
<?php endif; ?>
<?php if ($data['type'] == $dynamic_string && $data['online'] != $online_string):?>
						<a class="fa-solid fa-trash-can" title="<?=gettext('Delete lease')?>"	href="status_dhcpv6_leases.php?deleteip=<?=$data['ip']?>&amp;all=<?=intval($_REQUEST['all'])?>" usepost></a>
<?php endif; ?>
					</td>
				</tr>
<?php endforeach; ?>

<?php if ($no_leases_displayed): ?>
				<tr>
					<td><!-- icon --></td>
					<td colspan="8"><?=gettext('No leases to display')?></td>
				</tr>
<?php
endif;
?>
			</tbody>
		</table>
	</div>
</div>

<?php
if (dhcp_is_backend('isc')):
?>
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
		$icon = 'fa-regular fa-circle-check';
	} elseif ($data['act'] == $expired_string) {
		$icon = 'fa-solid fa-ban';
	} else {
		$icon = 'fa-regular fa-circle-xmark';
	}

	if ($data['act'] == $static_string) {
		foreach (config_get_path('dhcpdv6', []) as $dhcpif => $dhcpifconf) {
			if (empty($dhcpifconf)) {
				continue;
			}
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
				<td><i class="<?=$icon?>"></i></td>
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
<?php
endif; /* dhcp_is_backend('isc') */
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Lease Utilization')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext('Interface')?></th>
					<th><?=gettext('Pool Start')?></th>
					<th><?=gettext('Pool End')?></th>
					<th><?=gettext('Used')?></th>
				</tr>
			</thead>
			<tbody>
<?php
if (count($dhcp_leases_subnet_counter)):
	ksort($dhcp_leases_subnet_counter);
	foreach ($dhcp_leases_subnet_counter as $listcounters):
		$now = $listcounters['count'];
?>
				<tr>
					<td><?=$iflist[$listcounters['dhcpif']]?></td>
					<td><?=$listcounters['from']?></td>
					<td><?=$listcounters['to']?></td>
					<td><?=$now?></td>
				</tr>
<?php
	endforeach;
else:
?>
				<tr>
					<td colspan="4"><?=gettext('No leases are in use')?></td>
				</tr>
<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
<?php if ($_REQUEST['all']): ?>
	<a class="btn btn-info" href="status_dhcpv6_leases.php?all=0"><i class="fa-solid fa-minus-circle icon-embed-btn"></i><?=gettext('Show Active and Static Leases Only')?></a>
<?php else: ?>
	<a class="btn btn-info" href="status_dhcpv6_leases.php?all=1"><i class="fa-solid fa-plus-circle icon-embed-btn"></i><?=gettext('Show all Configured Leases')?></a>
<?php endif; ?>
	<a class="btn btn-danger no-confirm" id="cleardhcp"><i class="fa-solid fa-trash-can icon-embed-btn"></i><?=gettext('Clear all DHCPv6 Leases')?></a>
</nav>

<?php
if (dhcp_is_backend('kea')):
	$status = system_get_kea6status();
	if (is_array($status) && array_key_exists('high-availability', $status['arguments'])):
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('High Availability Status')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th><?=gettext('Node Name')?></th>
				<th><?=gettext('Node Type')?></th>
				<th><?=gettext('Node Role')?></th>
				<th><?=gettext('Latest Heartbeat')?></th>
				<th><?=gettext('Node State')?></th>
			</tr>
		</thead>
		<tbody>
<?php
		foreach ($status['arguments']['high-availability'] as $ha_status):
			foreach ($ha_status['ha-servers'] as $where => $ha_server):
?>
			<tr>
				<td><?=dhcp_ha_status_icon($where, $ha_server)?> <?=htmlspecialchars($ha_server['server-name'])?></td>
				<td><?=htmlspecialchars($where)?></td>
				<td><?=htmlspecialchars($ha_server['role'])?></td>
				<td><?=htmlspecialchars(kea_format_age($ha_server['age']))?></td>
				<td><?=htmlspecialchars($ha_server['state'] ?? $ha_server['last-state'])?></td>
			</tr>
<?php
			endforeach;
		endforeach;
?>
		</tbody>
		</table>
	</div>
</div>
<?php
	endif;
endif; /* dhcp_is_backend('kea') */
?>

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

		// Trim on values where a space doesn't make sense
		if ((where >= 2) && (where <= 8)) {
			searchstr = searchstr.trim();
		}

		table.find('tr').each(function (i) {
			var $tds	= $(this).find('td');
			var $popover	= $($.parseHTML($tds.eq(2).attr('data-content')));

			var lease	= $tds.eq(0).find('.act').attr('title').trim().toLowerCase();
			var online	= $tds.eq(0).find('.online').attr('title').trim().toLowerCase();
			var ipaddr	= $tds.eq(1).text().trim().toLowerCase();
			var duid	= $tds.eq(2).text().trim().toLowerCase();
			var iaid	= $popover.closest('.iaid').text().trim().toLowerCase();
			var mac		= $popover.closest('.mac').text().trim().toLowerCase();
			var hostname	= $tds.eq(3).text().trim().toLowerCase();
			var descr	= $tds.eq(4).text().trim().toLowerCase();
			var start	= $tds.eq(5).text().trim().toLowerCase();
			var end		= $tds.eq(6).text().trim().toLowerCase();

			regexp = new RegExp(searchstr);
			if (searchstr.length > 0) {
				if (!(regexp.test(lease)    && ((where == 2)  || (where == 1))) &&
				    !(regexp.test(online)   && ((where == 3)  || (where == 1))) &&
				    !(regexp.test(ipaddr)   && ((where == 4)  || (where == 1))) &&
				    !(regexp.test(duid)     && ((where == 5)  || (where == 1))) &&
				    !(regexp.test(iaid)     && ((where == 6)  || (where == 1))) &&
				    !(regexp.test(mac)      && ((where == 7)  || (where == 1))) &&
				    !(regexp.test(hostname) && ((where == 8)  || (where == 1))) &&
				    !(regexp.test(descr)    && ((where == 9)  || (where == 1))) &&
				    !(regexp.test(start)    && ((where == 10) || (where == 1))) &&
				    !(regexp.test(end)      && ((where == 11) || (where == 1)))
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

		$('#where option[value="1"]').prop('selected', true);

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

<?php
include('foot.inc');
