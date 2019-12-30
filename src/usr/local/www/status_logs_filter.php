<?php
/*
 * status_logs_filter.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-diagnostics-logs-firewall
##|*NAME=Status: Logs: Firewall
##|*DESCR=Allow access to the 'Status: Logs: Firewall' page.
##|*MATCH=status_logs_filter.php*
##|-PRIV

require_once("status_logs_common.inc");
require_once("ipsec.inc");


# --- AJAX RESOLVE ---
if (isset($_POST['resolve'])) {
	$ip = strtolower($_POST['resolve']);
	$res = (is_ipaddr($ip) ? gethostbyaddr($ip) : '');

	if ($res && $res != $ip) {
		$response = array('resolve_ip' => $ip, 'resolve_text' => $res);
	} else {
		$response = array('resolve_ip' => $ip, 'resolve_text' => gettext("Cannot resolve"));
	}

	echo json_encode(str_replace("\\", "\\\\", $response)); // single escape chars can break JSON decode
	exit;
}


/*
Build a list of allowed log files so we can reject others to prevent the page
from acting on unauthorized files.
*/
$allowed_logs = array(
	"filter" => array("name" => "Firewall",
		    "shortcut" => "filter"),
);

// The logs to display are specified in a REQUEST argument. Default to 'system' logs
if (!$_REQUEST['logfile']) {
	$logfile = 'filter';
	$view = 'normal';
} else {
	$logfile = $_REQUEST['logfile'];
	$view = $_REQUEST['view'];
	if (!array_key_exists($logfile, $allowed_logs)) {
		/* Do not let someone attempt to load an unauthorized log. */
		$logfile = 'filter';
		$view = 'normal';
	}
}

if ($view == 'normal')  { $view_title = gettext("Normal View"); }
if ($view == 'dynamic') { $view_title = gettext("Dynamic View"); }
if ($view == 'summary') { $view_title = gettext("Summary View"); }

$rulenum = getGETPOSTsettingvalue('getrulenum', null);

if ($rulenum) {
	list($rulenum, $tracker, $type) = explode(',', $rulenum);
	$rule = find_rule_by_number($rulenum, $tracker, $type);
	echo gettext("The rule that triggered this action is") . ":\n\n{$rule}";
	exit;
}


// Log Filter Submit - Firewall
log_filter_form_firewall_submit();


// Manage Log Section - Code
manage_log_code();


// Status Logs Common - Code
status_logs_common_code();


$pgtitle = array(gettext("Status"), gettext("System Logs"), gettext($allowed_logs[$logfile]["name"]), $view_title);
$pglinks = array("", "status_logs.php", "status_logs_filter.php", "@self");
include("head.inc");

if ($changes_applied) {
	print_apply_result_box($retval, $extra_save_msg);
	$manage_log_active = false;
}

// Tab Array
tab_array_logs_common();


// Manage Log - Section/Form
if ($system_logs_manage_log_form_hidden) {
	manage_log_section();
}


// Filter Section/Form - Firewall
filter_form_firewall();


// Now the forms are complete we can draw the log table and its controls
if (!$rawfilter) {
	$iflist = get_configured_interface_with_descr(true);

	if ($iflist[$interfacefilter]) {
		$interfacefilter = $iflist[$interfacefilter];
	}

	system_log_filter();
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
<?php
	print(system_log_table_panel_title());
?>
		</h2>
	</div>
	<div class="panel-body">
	   <div class="table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr class="text-nowrap">
					<th><?=gettext("Action")?></th>
					<th><?=gettext("Time")?></th>
					<th><?=gettext("Interface")?></th>
<?php
	if ($config['syslog']['filterdescriptions'] === "1") {
?>
					<th style="width:100%">
						<?=gettext("Rule")?>
					</th>
<?php
	}
?>
					<th><?=gettext("Source")?></th>
					<th><?=gettext("Destination")?></th>
					<th><?=gettext("Protocol")?></th>
				</tr>
			</thead>
			<tbody>
<?php
	if ($config['syslog']['filterdescriptions']) {
		buffer_rules_load();
	}

	foreach ($filterlog as $filterent) {
?>
				<tr class="text-nowrap">
					<td>
<?php
		if ($filterent['act'] == "block") {
			$icon_act = "fa-times text-danger";
		} else {
			$icon_act = "fa-check text-success";
		}

		if ($filterent['count']) {
			$margin_left = '0em';
		} else {
			$margin_left = '0.4em';
		}
?>
						<i style="margin-left:<?=$margin_left;?>" class="fa <?=$icon_act;?> icon-pointer" title="<?php echo $filterent['act'] .'/'. $filterent['tracker'];?>" onclick="javascript:getURL('status_logs_filter.php?getrulenum=<?="{$filterent['rulenum']},{$filterent['tracker']},{$filterent['act']}"; ?>', outputrule);"></i>
<?php
		if ($filterent['count']) {
			echo $filterent['count'];
		}
?>
					</td>
					<td>
		<?=htmlspecialchars($filterent['time'])?>
					</td>
					<td>
<?php
		if ($filterent['direction'] == "out") {
			print('&#x25ba;' . ' ');
		}
?>
		<?=htmlspecialchars($filterent['interface'])?>
					</td>
<?php
		if ($config['syslog']['filterdescriptions'] === "1") {
?>
					<td style="white-space:normal;">
			<?=find_rule_by_number_buffer($filterent['rulenum'], $filterent['tracker'], $filterent['act'])?>
					</td>
<?php
		}

		$int = strtolower($filterent['interface']);
		$proto = strtolower($filterent['proto']);
		$rawsrcip = $filterent['srcip'];
		$rawdstip = $filterent['dstip'];

		if ($filterent['version'] == '6') {
			$ipproto = "inet6";
			$filterent['srcip'] = "[{$filterent['srcip']}]";
			$filterent['dstip'] = "[{$filterent['dstip']}]";
		} else {
			$ipproto = "inet";
		}

		$srcstr = $filterent['srcip'] . get_port_with_service($filterent['srcport'], $proto);
		$src_htmlclass = str_replace(array('.', ':'), '-', $rawsrcip);
		$dststr = $filterent['dstip'] . get_port_with_service($filterent['dstport'], $proto);
		$dst_htmlclass = str_replace(array('.', ':'), '-', $rawdstip);
?>
					<td class="text-nowrap">
						<i class="fa fa-info icon-pointer icon-primary" onclick="javascript:resolve_with_ajax('<?="{$rawsrcip}"; ?>');" title="<?=gettext("Click to resolve")?>">
						</i>

						<a class="fa fa-minus-square-o icon-pointer icon-primary" href="easyrule.php?<?="action=block&amp;int={$int}&amp;src={$filterent['srcip']}&amp;ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Add to Block List")?>">
						</a>

						<?=$srcstr . '<span class="RESOLVE-' . $src_htmlclass . '"></span>'?>
					</td>
					<td class="text-nowrap">
						<i class="fa fa-info icon-pointer icon-primary; ICON-<?= $dst_htmlclass; ?>" onclick="javascript:resolve_with_ajax('<?="{$rawdstip}"; ?>');" title="<?=gettext("Click to resolve")?>">
						</i>

						<a class="fa fa-plus-square-o icon-pointer icon-primary" href="easyrule.php?<?="action=pass&amp;int={$int}&amp;proto={$proto}&amp;src={$filterent['srcip']}&amp;dst={$filterent['dstip']}&amp;dstport={$filterent['dstport']}&amp;ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Pass this traffic")?>">
						</a>
						<?=$dststr . '<span class="RESOLVE-' . $dst_htmlclass . '"></span>'?>
					</td>
<?php
		if ($filterent['proto'] == "TCP") {
			$filterent['proto'] .= ":{$filterent['tcpflags']}";
		} elseif ($filterent['protoid'] == '112') {
			$carp_details = array();
			if (strlen($filterent['vhid'])) {
				$carp_details[] = $filterent['vhid'];
			}
			if (strlen($filterent['advskew'])) {
				$carp_details[] = $filterent['advskew'];
			}
			if (strlen($filterent['advbase'])) {
				$carp_details[] = $filterent['advbase'];
			}
			if (!empty($carp_details)) {
				$filterent['proto'] .= " " . implode("/", $carp_details);
			}
		}
?>
					<td>
						<?=htmlspecialchars($filterent['proto'])?>
					</td>
				</tr>
<?php
		if (isset($config['syslog']['filterdescriptions']) && $config['syslog']['filterdescriptions'] === "2") {
?>
				<tr>
					<td colspan="2" />
					<td colspan="4"><?=find_rule_by_number_buffer($filterent['rulenum'], $filterent['tracker'], $filterent['act'])?></td>
				</tr>
<?php
		}
	} // e-o-foreach
	buffer_rules_clear();
?>
			</tbody>
		</table>
<?php
	if (count($filterlog) == 0) {
		print_info_box(gettext('No logs to display.'));
	}
?>
		</div>
	</div>
</div>

<?php
} else {
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
<?php
	print(system_log_table_panel_title());
?>
		</h2>
	</div>
	<div class="table table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr class="text-nowrap">
					<th><?=gettext("Time")?></th>
					<th style="width:100%"><?=gettext("Message")?></th>
				</tr>
			</thead>
			<tbody>
<?php
	system_log_filter();
?>
			</tbody>
		</table>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$("#count").html(<?=$rows?>);
});
//]]>
</script>

<?php
	if ($rows == 0) {
		print_info_box(gettext('No logs to display.'));
	}
?>
	</div>
</div>
<?php
}
?>

<div class="infoblock">
<?php
print_info_box('<a href="https://docs.netgate.com/pfsense/en/latest/firewall/tcp-flag-definitions.html">' .
	gettext("TCP Flags") . '</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR.' . '<br />' .
	'<i class="fa fa-minus-square-o icon-primary"></i> = ' . gettext('Add to block list') . ', <i class="fa fa-plus-square-o icon-primary"></i> = ' . gettext('Pass traffic') . ', <i class="fa fa-info icon-primary"></i> = ' . gettext('Resolve'), 'info', false);
?>
</div>

<?php
# Manage Log - Section/Form
if (!$system_logs_manage_log_form_hidden) {
	manage_log_section();
}
?>

<!-- AJAXY STUFF -->
<script type="text/javascript">
//<![CDATA[
function outputrule(req) {
	alert(req.content);
}

function resolve_with_ajax(ip_to_resolve) {
	var url = "/status_logs_filter.php";

	$.ajax(
		url,
		{
			method: 'post',
			dataType: 'json',
			data: {
				resolve: ip_to_resolve,
				},
			complete: resolve_ip_callback
		});

}

function resolve_ip_callback(transport) {
	var response = $.parseJSON(transport.responseText);
	var resolve_class = htmlspecialchars(response.resolve_ip.replace(/[.:]/g, '-'));
	var resolve_text = '<small><br />' + htmlspecialchars(response.resolve_text) + '<\/small>';

	$('span.RESOLVE-' + resolve_class).html(resolve_text);
}

// From http://stackoverflow.com/questions/5499078/fastest-method-to-escape-html-tags-as-html-entities
function htmlspecialchars(str) {
	return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
}

if (typeof getURL == 'undefined') {
	getURL = function(url, callback) {
		if (!url)
			throw 'No URL for getURL';
		try {
			if (typeof callback.operationComplete == 'function')
				callback = callback.operationComplete;
		} catch (e) {}
			if (typeof callback != 'function')
				throw 'No callback function for getURL';
		var http_request = null;
		if (typeof XMLHttpRequest != 'undefined') {
			http_request = new XMLHttpRequest();
		}
		else if (typeof ActiveXObject != 'undefined') {
			try {
				http_request = new ActiveXObject('Msxml2.XMLHTTP');
			} catch (e) {
				try {
					http_request = new ActiveXObject('Microsoft.XMLHTTP');
				} catch (e) {}
			}
		}
		if (!http_request)
			throw 'Both getURL and XMLHttpRequest are undefined';
		http_request.onreadystatechange = function() {
			if (http_request.readyState == 4) {
				callback( { success : true,
				  content : http_request.responseText,
				  contentType : http_request.getResponseHeader("Content-Type") } );
			}
		};
		http_request.open('REQUEST', url, true);
		http_request.send(null);
	};
}

events.push(function() {
    $('.fa').tooltip();
});
//]]>
</script>

<?php include("foot.inc");
?>
