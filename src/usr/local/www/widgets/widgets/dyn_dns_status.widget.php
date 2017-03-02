<?php
/*
 * dyn_dns_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013 Stanley P. Miller \ stan-qaz
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 */

$nocsrf = true;
global $dyndns_split_domain_types;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/dyn_dns_status.inc");

// Constructs a unique key that will identify a Dynamic DNS entry in the filter list.
function get_dyndnsent_key($dyndns) {
	return $dyndns['id'];
}

function get_dyndns_hostname_text($dyndns) {
	global $dyndns_split_domain_types;
	if (in_array($dyndns['type'], $dyndns_split_domain_types)) {
		return $dyndns['host'] . "." . $dyndns['domainname'];
	}

	return $dyndns['host'];
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

$skipdyndns = explode(",", $user_settings['widgets']['dyn_dns_status']['filter']);

if ($_REQUEST['getdyndnsstatus']) {
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
} else if ($_POST) {

	$validNames = array();

	foreach ($all_dyndns as $dyndns) {
		array_push($validNames, get_dyndnsent_key($dyndns));
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets']['dyn_dns_status']['filter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets']['dyn_dns_status']['filter'] = "";
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Dynamic DNS Filter via Dashboard."));
	header("Location: /index.php");
}

$iflist = get_configured_interface_with_descr();

function get_dyndns_interface_text($dyndns_iface) {
	global $iflist;
	if (isset($iflist[$dyndns_iface])) {
		return $iflist[$dyndns_iface];
	}

	// This will be a gateway group name.
	return $dyndns_iface;
}

$dyndns_providers = array_combine(explode(" ", DYNDNS_PROVIDER_VALUES), explode(",", DYNDNS_PROVIDER_DESCRIPTIONS));

function get_dyndns_service_text($dyndns_type) {
	global $dyndns_providers;

	if (isset($dyndns_providers[$dyndns_type])) {
		return $dyndns_providers[$dyndns_type];
	} else if ($dyndns_type == '_rfc2136_') {
		return "RFC 2136";
	}

	return $dyndns_type;
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
		<?=htmlspecialchars(get_dyndns_hostname_text($dyndns));?>
		</td>
		<td>
		<div id="dyndnsstatus<?= $rowid;?>"><?= gettext("Checking ...");?></div>
		</td>
	</tr>
	<?php endforeach;?>
	</tbody>
</table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">

<form action="/widgets/widgets/dyn_dns_status.widget.php" method="post" class="form-horizontal">
    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
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
				$skipdyndns = explode(",", $user_settings['widgets']['dyn_dns_status']['filter']);
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
			<button id="showalldyndns" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
		</div>
	</div>
</form>

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

	}
	function dyndnscallback(transport) {
		// The server returns a string of statuses separated by vertical bars
		var responseStrings = transport.responseText.split("|");
		for (var count=0; count<responseStrings.length; count++) {
			var divlabel = '#dyndnsstatus' + count;
			$(divlabel).prop('innerHTML',responseStrings[count]);
		}

		// Refresh the status every 5 minutes
		setTimeout('dyndns_getstatus()', 5*60*1000);
	}
	events.push(function(){
		$("#showalldyndns").click(function() {
			$("[id^=show]").each(function() {
				$(this).prop("checked", true);
			});
		});

	});
	// Do the first status check 2 seconds after the dashboard opens
	setTimeout('dyndns_getstatus()', 2000);
//]]>
</script>
