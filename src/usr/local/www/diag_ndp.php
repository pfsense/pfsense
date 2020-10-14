<?php
/*
 * diag_ndp.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
define('NDP_BINARY_PATH', '/usr/sbin/ndp');
require_once("guiconfig.inc");

// Delete ndp entry.
if (isset($_POST['deleteentry'])) {
	$ip = $_POST['deleteentry'];
	if (is_ipaddrv6($ip)) {
		$commandReturnValue = mwexec(NDP_BINARY_PATH . " -d " . escapeshellarg($ip), true);
		$deleteSucceededFlag = ($commandReturnValue == 0);
	} else {
		$deleteSucceededFlag = false;
	}

	$deleteResultMessage = ($deleteSucceededFlag)
		? sprintf(gettext("The NDP entry for %s has been deleted."), $ip)
		: sprintf(gettext("%s is not a valid IPv6 address or could not be deleted."), $ip);
	$deleteResultMessageType = ($deleteSucceededFlag)
		? 'success'
		: 'alert-warning';
} elseif (isset($_POST['clearndptable'])) {
	$out = "";
	$ret = exec("/usr/sbin/ndp -c", $out, $ndpTableRetVal);
	if ($ndpTableRetVal == 0) { 
		$deleteResultMessage = gettext("NDP Table has been cleared.");
		$deleteResultMessageType = 'success';
	} else {
		$deleteResultMessage = gettext("Unable to clear NDP Table.");
		$deleteResultMessageType = 'alert-warning';
	}
}

exec(NDP_BINARY_PATH . " -na", $rawdata);

$i = 0;

/* if list */
$ifdescrs = get_configured_interface_with_descr();

foreach ($ifdescrs as $key =>$interface) {
	$hwif[$config['interfaces'][$key]['if']] = $interface;
}

/*
 * Key map for each element in $rawdata
 * 0 => Neighbor IP
 * 1 => Physical address (MAC)
 * 2 => Interface
 * 3 => Expiration
 * 4 => State
 * 5 => Flags
 */
$data = array();
array_shift($rawdata);
foreach ($rawdata as $line) {
	$elements = preg_split('/[ ]+/', $line);

	$ndpent = array();
	$ndpent['ipv6'] = trim($elements[0]);
	$ndpent['mac'] = trim($elements[1]);
	$ndpent['interface'] = trim($elements[2]);
	$ndpent['expiration'] = trim($elements[3]);
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

// Show message if defined.
if (isset($deleteResultMessage, $deleteResultMessageType)) {
	print_info_box(htmlentities($deleteResultMessage), $deleteResultMessageType);
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
					<option value="0"><?=gettext("IPv6 Address")?></option>
					<option value="1"><?=gettext("MAC Address")?></option>
					<option value="2"><?=gettext("Hostname")?></option>
					<option value="3"><?=gettext("Interface")?></option>
					<option value="4"><?=gettext("Expiration")?></option>
					<option value="5" selected><?=gettext("All")?></option>
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
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('NDP Table')?></h2></div>
	<div class="panel-body">

<div class="table-responsive">
	<table class="table table-striped table-condensed table-hover sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th><?=gettext("IPv6 address")?></th>
				<th><?=gettext("MAC address")?></th>
				<th><?=gettext("Hostname")?></th>
				<th><?=gettext("Interface")?></th>
				<th><?=gettext("Expiration")?></th>
				<th data-sortable="false"><?=gettext("Actions")?></th>
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
					<td>
						<?=$entry['expiration']?>
					</td>
					<td>
						<a class="fa fa-trash" title="<?=gettext('Delete NDP entry')?>"	href="diag_ndp.php?deleteentry=<?=$entry['ipv6']?>" usepost></a>
					</td>
				</tr>
			<?php endforeach; ?>
	</tbody>
	</table>
</div>

	</div>
</div>

<nav class="action-buttons">
	<button id="clearndp" class="btn btn-danger no-confirm">
		<i class="fa fa-trash icon-embed-btn"></i>
		<?=gettext("Clear NDP Table")?>
	</button>
</nav>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Make these controls plain buttons
	$("#btnsearch").prop('type', 'button');
	$("#btnclear").prop('type', 'button');

	// Search for a term in the entry name and/or dn
	$("#btnsearch").click(function() {
		var searchstr = $('#searchstr').val().toLowerCase();
		var table = $("table tbody");
		var where = $('#where').val();

		table.find('tr').each(function (i) {
			var $tds = $(this).find('td'),
				ipaddr   = $tds.eq(0).text().trim().toLowerCase();
				macaddr  = $tds.eq(1).text().trim().toLowerCase();
				hostname = $tds.eq(2).text().trim().toLowerCase();
				iface    = $tds.eq(3).text().trim().toLowerCase(),
				stat     = $tds.eq(4).text().trim().toLowerCase();

			regexp = new RegExp(searchstr);
			if (searchstr.length > 0) {
				if (!(regexp.test(ipaddr)   && ((where == 0) || (where == 5))) &&
				    !(regexp.test(macaddr)  && ((where == 1) || (where == 5))) &&
				    !(regexp.test(hostname) && ((where == 2) || (where == 5))) &&
				    !(regexp.test(iface)    && ((where == 3) || (where == 5))) &&
				    !(regexp.test(stat)     && ((where == 4) || (where == 5)))
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
		var table = $("table tbody");

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

	$('#clearndp').click(function() {
		if (confirm("Are you sure you wish to clear NDP table?")) {
			postSubmit({clearndptable: 'true'}, 'diag_ndp.php');
		}
	});

});
//]]>
</script>

<?php include("foot.inc");
