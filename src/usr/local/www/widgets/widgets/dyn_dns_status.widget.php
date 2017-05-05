<?php
/*
 * dyn_dns_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013 Stanley P. Miller \ stan-qaz
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
global $dyndns_split_domain_types;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/dyn_dns_status.inc");

// Constructs a unique key that will identify a Dynamic DNS entry in the filter list.
if (!function_exists('get_dyndnsent_key')) {
	function get_dyndnsent_key($dyndns) {
		return $dyndns['id'];
	}
}

if (!function_exists('get_dyndns_hostname_text')) {
	function get_dyndns_hostname_text($dyndns) {
		global $dyndns_split_domain_types;
		if (in_array($dyndns['type'], $dyndns_split_domain_types)) {
			return $dyndns['host'] . "." . $dyndns['domainname'];
		}

		return $dyndns['host'];
	}
}

if (!is_array($config['dyndnses']['dyndns'])) {
	$config['dyndnses']['dyndns'] = array();
}

$a_dyndns = $config['dyndnses']['dyndns'];

if (!is_array($config['dnsupdates']['dnsupdate'])) {
	$config['dnsupdates']['dnsupdate'] = array();
}

$a_rfc2136 = $config['dnsupdates']['dnsupdate'];

$all_dyndns = array_merge($a_dyndns, $a_rfc2136);

array_walk($all_dyndns, function(&$dyndns) {
	if (empty($dyndns['type'])) {
		/* RFC2136, add some dummy values */
		$dyndns['type'] = '_rfc2136_';
		$dyndns['id'] = '_' . $dyndns['server'];
	}
});

if ($_REQUEST['getdyndnsstatus']) {
	$skipdyndns = explode(",", $user_settings['widgets'][$_REQUEST['getdyndnsstatus']]['filter']);
	$first_entry = true;
	foreach ($all_dyndns as $dyndns) {
		if (in_array(get_dyndnsent_key($dyndns), $skipdyndns)) {
			continue;
		}

		if ($first_entry) {
			$first_entry = false;
		} else {
			// Put a vertical bar delimiter between the echoed HTML for each entry processed.
			echo "|";
		}

		$hostname = get_dyndns_hostname_text($dyndns);
		$filename = "{$g['conf_path']}/dyndns_{$dyndns['interface']}{$dyndns['type']}" . escapeshellarg($hostname) . "{$dyndns['id']}.cache";
		$filename_v6 = "{$g['conf_path']}/dyndns_{$dyndns['interface']}{$dyndns['type']}" . escapeshellarg($hostname) . "{$dyndns['id']}_v6.cache";
		if (file_exists($filename)) {
			$ipaddr = dyndnsCheckIP($dyndns['interface']);
			$cached_ip_s = explode("|", file_get_contents($filename));
			$cached_ip = $cached_ip_s[0];

			if ($ipaddr != $cached_ip) {
				print('<span class="text-danger">');
			} else {
				print('<span class="text-success">');
			}

			print(htmlspecialchars($cached_ip));
			print('</span>');
		} else if (file_exists($filename_v6)) {
			$ipv6addr = get_interface_ipv6($dyndns['interface']);
			$cached_ipv6_s = explode("|", file_get_contents($filename_v6));
			$cached_ipv6 = $cached_ipv6_s[0];

			if ($ipv6addr != $cached_ipv6) {
				print('<span class="text-danger">');
			} else {
				print('<span class="text-success">');
			}

			print(htmlspecialchars($cached_ipv6));
			print('</span>');
		} else {
			print('N/A ' . date("H:i:s"));
		}
	}
	exit;
} else if ($_POST['widgetkey']) {

	$validNames = array();

	foreach ($all_dyndns as $dyndns) {
		array_push($validNames, get_dyndnsent_key($dyndns));
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', $validNames);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Dynamic DNS Filter via Dashboard."));
	header("Location: /index.php");
}

$iflist = get_configured_interface_with_descr();

if (!function_exists('get_dyndns_interface_text')) {
	function get_dyndns_interface_text($dyndns_iface) {
		global $iflist;
		if (isset($iflist[$dyndns_iface])) {
			return $iflist[$dyndns_iface];
		}

		// This will be a gateway group name.
		return $dyndns_iface;
	}
}

$dyndns_providers = array_combine(explode(" ", DYNDNS_PROVIDER_VALUES), explode(",", DYNDNS_PROVIDER_DESCRIPTIONS));
$skipdyndns = explode(",", $user_settings['widgets'][$widgetkey]['filter']);
$widgetkey_nodash = str_replace("-", "", $widgetkey);

if (!function_exists('get_dyndns_service_text')) {
	function get_dyndns_service_text($dyndns_type) {
		global $dyndns_providers;

		if (isset($dyndns_providers[$dyndns_type])) {
			return $dyndns_providers[$dyndns_type];
		} else if ($dyndns_type == '_rfc2136_') {
			return "RFC 2136";
		}

		return $dyndns_type;
	}
}

?>

<div class="table-responsive">
<table id="dyn_dns_status" class="table table-hover table-striped table-condensed">
	<thead>
	<tr>
		<th style="width:5%;"><?=gettext("Int.");?></th>
		<th style="width:20%;"><?=gettext("Service");?></th>
		<th style="width:25%;"><?=gettext("Hostname");?></th>
		<th style="width:25%;"><?=gettext("Cached IP");?></th>
	</tr>
	</thead>
	<tbody>
	<?php $dyndnsid = -1; $rfc2136id = -1; $rowid = -1; foreach ($all_dyndns as $dyndns):
		if ($dyndns['type'] == '_rfc2136_') {
			$dblclick_location = 'services_rfc2136_edit.php';
			$rfc2136id++;
			$locationid = $rfc2136id;
		} else {
			$dblclick_location = 'services_dyndns_edit.php';
			$dyndnsid++;
			$locationid = $dyndnsid;
		}

		if (in_array(get_dyndnsent_key($dyndns), $skipdyndns)) {
			continue;
		}

		$rowid++;

	?>
	<tr ondblclick="document.location='<?=$dblclick_location;?>?id=<?=$locationid;?>'"<?=!isset($dyndns['enable'])?' class="disabled"':''?>>
		<td>
		<?=get_dyndns_interface_text($dyndns['interface']);?>
		</td>
		<td>
		<?=htmlspecialchars(get_dyndns_service_text($dyndns['type']));?>
		</td>
		<td>
		<?=insert_word_breaks_in_domain_name(htmlspecialchars(get_dyndns_hostname_text($dyndns)));?>
		</td>
		<td>
		<div id="dyndnsstatus<?= $rowid;?>"><?= gettext("Checking ...");?></div>
		</td>
	</tr>
	<?php endforeach;?>
	<?php if ($rowid == -1):?>
	<tr>
		<td colspan="4" class="text-center">
			<?=gettext('All Dyn DNS entries are hidden.');?>
		</td>
	</tr>
	<?php endif;?>
	</tbody>
</table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<form action="/widgets/widgets/dyn_dns_status.widget.php" method="post" class="form-horizontal">
    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<input type="hidden" name="widgetkey" value="<?=$widgetkey; ?>">
			<div class="table responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Interface")?></th>
							<th><?=gettext("Service")?></th>
							<th><?=gettext("Hostname")?></th>
							<th><?=gettext("Show")?></th>
						</tr>
					</thead>
					<tbody>
<?php
				$skipdyndns = explode(",", $user_settings['widgets'][$widgetkey]['filter']);
				foreach ($all_dyndns as $dyndns):
?>
						<tr>
							<td><?=get_dyndns_interface_text($dyndns['interface'])?></td>
							<td><?=get_dyndns_service_text($dyndns['type'])?></td>
							<td><?=get_dyndns_hostname_text($dyndns)?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=get_dyndnsent_key($dyndns)?>" type="checkbox" <?=(!in_array(get_dyndnsent_key($dyndns), $skipdyndns) ? 'checked':'')?>></td>
						</tr>
<?php
				endforeach;
?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			<button id="<?=$widget_showallnone_id?>" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
		</div>
	</div>
</form>

<script type="text/javascript">
//<![CDATA[
	function dyndns_getstatus_<?=$widgetkey_nodash?>() {
		scroll(0,0);
		var url = "/widgets/widgets/dyn_dns_status.widget.php";
		var pars = 'getdyndnsstatus=<?=$widgetkey?>';
		$.ajax(
			url,
			{
				type: 'get',
				data: pars,
				complete: dyndnscallback_<?=$widgetkey_nodash?>
			});

	}
	function dyndnscallback_<?=$widgetkey_nodash?>(transport) {
		// The server returns a string of statuses separated by vertical bars
		var responseStrings = transport.responseText.split("|");
		for (var count=0; count<responseStrings.length; count++) {
			var divlabel = '#widget-<?=$widgetkey?> #dyndnsstatus' + count;
			$(divlabel).prop('innerHTML',responseStrings[count]);
		}

		// Refresh the status every 5 minutes
		setTimeout('dyndns_getstatus_<?=$widgetkey_nodash?>()', 5*60*1000);
	}
	events.push(function(){
		set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=show]", "<?=$widget_showallnone_id?>");
	});
	// Do the first status check 2 seconds after the dashboard opens
	setTimeout('dyndns_getstatus_<?=$widgetkey_nodash?>()', 2000);
//]]>
</script>
