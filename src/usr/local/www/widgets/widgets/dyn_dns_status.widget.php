<?php
/*
 * dyn_dns_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013 Stanley P. Miller \ stan-qaz
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/dyn_dns_status.inc");

if (!is_array($config['dyndnses']['dyndns'])) {
	$config['dyndnses']['dyndns'] = array();
}

$a_dyndns = $config['dyndnses']['dyndns'];

if (!is_array($config['dnsupdates']['dnsupdate'])) {
	$config['dnsupdates']['dnsupdate'] = array();
}

$a_rfc2136 = $config['dnsupdates']['dnsupdate'];

$all_dyndns = array_merge($a_dyndns, $a_rfc2136);

if ($_REQUEST['getdyndnsstatus']) {
	$first_entry = true;
	foreach ($all_dyndns as $dyndns) {
		if ($first_entry) {
			$first_entry = false;
		} else {
			// Put a vertical bar delimiter between the echoed HTML for each entry processed.
			echo "|";
		}
		$cache_sep = ":";
		if ($dyndns['type'] == "namecheap") {
			$hostname = $dyndns['host'] . "." . $dyndns['domainname'];
		} elseif (empty($dyndns['type'])) {
			/* RFC2136, add some dummy values */
			$dyndns['type'] = '_rfc2136_';
			$dyndns['id'] = '_' . $dyndns['server'];
			$hostname = $dyndns['host'];
			$cache_sep = "|";
		} else {
			$hostname = $dyndns['host'];
		}

		$filename = "{$g['conf_path']}/dyndns_{$dyndns['interface']}{$dyndns['type']}" . escapeshellarg($hostname) . "{$dyndns['id']}.cache";
		if (file_exists($filename)) {
			if (($dyndns['type'] == '_rfc2136_') && (!isset($dyndns['usepublicip']))) {
				$ipaddr = get_interface_ip(get_failover_interface($dyndns['interface']));
			} else {
				$ipaddr = dyndnsCheckIP($dyndns['interface']);
			}
			$cached_ip_s = explode($cache_sep, file_get_contents($filename));
			$cached_ip = $cached_ip_s[0];
			if (trim($ipaddr) != trim($cached_ip)) {
				print('<span class="text-danger">');
			} else {
				print('<span class="text-success">');
			}
			print(htmlspecialchars($cached_ip));
			print('</span>');
		} else {
			print('N/A ' . date("H:i:s"));
		}
	}
	exit;
}

?>

<table id="dyn_dns_status" class="table table-striped table-hover">
	<thead>
	<tr>
		<th style="width:5%;"><?=gettext("Int.");?></th>
		<th style="width:20%;"><?=gettext("Service");?></th>
		<th style="width:25%;"><?=gettext("Hostname");?></th>
		<th style="width:25%;"><?=gettext("Cached IP");?></th>
	</tr>
	</thead>
	<tbody>
	<?php $dyndnsid = 0; foreach ($all_dyndns as $dyndns):

		if ($dyndns['type'] == "namecheap") {
			$hostname = $dyndns['host'] . "." . $dyndns['domainname'];
		} elseif (empty($dyndns['type'])) {
			/* RFC2136, add some dummy values */
			$dyndns['type'] = '_rfc2136_';
			$dyndns['id'] = '_' . $dyndns['server'];
			$hostname = $dyndns['host'];
		} else {
			$hostname = $dyndns['host'];
		} ?>
	<tr ondblclick="document.location='services_dyndns_edit.php?id=<?=$dyndnsid;?>'"<?=!isset($dyndns['enable'])?' class="disabled"':''?>>
		<td>
		<?php $iflist = get_configured_interface_with_descr();
		foreach ($iflist as $if => $ifdesc) {
			if ($dyndns['interface'] == $if) {
				print($ifdesc);
				break;
			}
		}
		$groupslist = return_gateway_groups_array();
		foreach ($groupslist as $if => $group) {
			if ($dyndns['interface'] == $if) {
				print($if);
				break;
			}
		}
		?>
		</td>
		<td>
		<?php
		$types = explode(",", DYNDNS_PROVIDER_DESCRIPTIONS);
		$vals = explode(" ", DYNDNS_PROVIDER_VALUES);
		for ($j = 0; $j < count($vals); $j++) {
			if ($vals[$j] == $dyndns['type']) {
				print(htmlspecialchars($types[$j]));
				break;
			}
		}
		if ($dyndns['type'] == '_rfc2136_') : ?>
			RFC 2136
		<?php endif; ?>
		</td>
		<td>
		<?=htmlspecialchars($hostname);?>
		</td>
		<td>
		<div id="dyndnsstatus<?= $dyndnsid;?>"><?= gettext("Checking ...");?></div>
		</td>
	</tr>
	<?php $dyndnsid++; endforeach;?>
	</tbody>
</table>

<script type="text/javascript">
//<![CDATA[
	function dyndns_getstatus() {
		scroll(0,0);
		var url = "/widgets/widgets/dyn_dns_status.widget.php";
		var pars = 'getdyndnsstatus=yes';
		$.ajax(
			url,
			{
				type: 'get',
				data: pars,
				complete: dyndnscallback
			});
		// Refresh the status every 5 minutes
		setTimeout('dyndns_getstatus()', 5*60*1000);
	}
	function dyndnscallback(transport) {
		// The server returns a string of statuses separated by vertical bars
		var responseStrings = transport.responseText.split("|");
		for (var count=0; count<responseStrings.length; count++) {
			var divlabel = '#dyndnsstatus' + count;
			$(divlabel).prop('innerHTML',responseStrings[count]);
		}
	}
	// Do the first status check 2 seconds after the dashboard opens
	setTimeout('dyndns_getstatus()', 2000);
//]]>
</script>
