<?php
/*
 * status_dhcp_leases.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
require_once("system.inc");

$pgtitle = array(gettext("Status"), gettext("DHCP Leases"));
$shortcut_section = "dhcp";

if (($_POST['deleteip']) && (is_ipaddr($_POST['deleteip']))) {
	$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";

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

$leases = system_get_dhcpleases();

$arp_table = system_get_arp_table();
$arpdata_mac = array();
foreach ($arp_table as $arp_entry) {
	if (isset($arpentry['incomplete'])) {
		continue;
	}
	$arpdata_mac[] = $arp_entry['mac-address'];
}
unset($arp_table);

/*
 * Translate these once so we don't do it over and over in the loops
 * below.
 */
$online_string = gettext("online");
$active_string = gettext("active");
$expired_string = gettext("expired");
$dynamic_string = gettext("dynamic");
$static_string = gettext("static");

if ($_REQUEST['order']) {
	usort($leases['lease'], function($a, $b) {
		return strcmp($a[$_REQUEST['order']], $b[$_REQUEST['order']]);
	});
}

/* only print pool status when we have one */
if (count($leases['failover']) > 0):
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
<?php foreach ($leases['failover'] as $data):?>
			<tr>
				<td><?=htmlspecialchars($data['name'])?></td>
				<td><?=htmlspecialchars($data['mystate'])?></td>
				<td><?=htmlspecialchars($data['mydate'])?></td>
				<td><?=htmlspecialchars($data['partnerstate'])?></td>
				<td><?=htmlspecialchars($data['partnerdate'])?></td>
			</tr>
<?php endforeach; ?>
		</tbody>
		</table>
	</div>
</div>
<?php
/* only print pool status when we have one */
endif;
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
					<option value="2"><?=gettext("MAC Address")?></option>
					<option value="3"><?=gettext("Client ID")?></option>
					<option value="4"><?=gettext("Hostname")?></option>
					<option value="5"><?=gettext("Description")?></option>
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
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><!-- icon --></th>
					<th><?=gettext("IP address")?></th>
					<th><?=gettext("MAC address")?></th>
					<th><?=gettext("Client Id")?></th>
					<th><?=gettext("Hostname")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Start")?></th>
					<th><?=gettext("End")?></th>
					<th><?=gettext("Online")?></th>
					<th><?=gettext("Lease Type")?></th>
					<th data-sortable="false"><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody id="leaselist">
<?php
$dhcp_leases_subnet_counter = array(); //array to sum up # of leases / subnet
$iflist = get_configured_interface_with_descr(); //get interface descr for # of leases
$no_leases_displayed = true;

foreach ($leases['lease'] as $data):
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
					<td><?=htmlspecialchars($data['cid'])?></td>
					<td><?=htmlspecialchars($data['hostname'])?></td>
					<td><?=htmlspecialchars($data['descr'])?></td>
					<? if ($data['type'] != $static_string): ?>
						<td><?=htmlspecialchars($data['starts'])?></td>
						<td><?=htmlspecialchars($data['ends'])?></td>
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
						<a class="fa fa-trash" title="<?=gettext('Delete lease')?>"	href="status_dhcp_leases.php?deleteip=<?=htmlspecialchars($data['ip'])?>&amp;all=<?=intval($_REQUEST['all'])?>" usepost></a>
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
<?php endif; ?>

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
				macaddr  = $tds.eq(2).text().trim().toLowerCase();
				clientid = $tds.eq(3).text().trim().toLowerCase(),
				hostname = $tds.eq(4).text().trim().toLowerCase();
				descr    = $tds.eq(5).text().trim().toLowerCase();
				start    = $tds.eq(6).text().trim().toLowerCase();
				end      = $tds.eq(7).text().trim().toLowerCase();
				online   = $tds.eq(8).text().trim().toLowerCase();
				lease    = $tds.eq(9).text().trim().toLowerCase();

			regexp = new RegExp(searchstr);
			if (searchstr.length > 0) {
				if (!(regexp.test(ipaddr)   && ((where == 1) || (where == 10))) &&
				    !(regexp.test(macaddr)  && ((where == 2) || (where == 10))) &&
				    !(regexp.test(clientid) && ((where == 3) || (where == 10))) &&
				    !(regexp.test(hostname) && ((where == 4) || (where == 10))) &&
				    !(regexp.test(descr)    && ((where == 5) || (where == 10))) &&
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

});
//]]>
</script>

<?php include("foot.inc");
