<?php
/*
	status_logs_filter.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
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

// The logs to display are specified in a GET argument. Default to 'system' logs
if (!$_GET['logfile']) {
	$logfile = 'filter';
	$view = 'normal';
} else {
	$logfile = $_GET['logfile'];
	$view = $_GET['view'];
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


$pgtitle = array(gettext("Status"), gettext("System logs"), gettext($allowed_logs[$logfile]["name"]), $view_title);
include("head.inc");

if (!$input_errors && $savemsg) {
	print_info_box($savemsg, 'success');
	$manage_log_active = false;
}


// Tab Array
tab_array_logs_common();


// Filter Section/Form - Firewall
filter_form_firewall();


// Now the forms are complete we can draw the log table and its controls
if (!$rawfilter) {
	$iflist = get_configured_interface_with_descr(false, true);

	if ($iflist[$interfacefilter]) {
		$interfacefilter = $iflist[$interfacefilter];
	}

	if ($filterlogentries_submit) {
		$filterlog = conv_log_filter($logfile_path, $nentries, $nentries + 100, $filterfieldsarray);
	} else {
		$filterlog = conv_log_filter($logfile_path, $nentries, $nentries + 100, $filtertext, $interfacefilter);
	}
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
<?php
	if ((!$filtertext) && (!$filterfieldsarray)) {
		printf(gettext("Last %d %s log entries."), count($filterlog), gettext($allowed_logs[$logfile]["name"]));
	} else {
		printf(gettext("%d matched %s log entries."), count($filterlog), gettext($allowed_logs[$logfile]["name"]));
	}

	printf(" (" . gettext("Maximum %d") . ")", $nentries);
?>
		</h2>
	</div>
	<div class="panel-body">
	   <div class="table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr class="text-nowrap">
					<th><?=gettext("Act")?></th>
					<th><?=gettext("Time")?></th>
					<th><?=gettext("IF")?></th>
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
					<th><?=gettext("Proto")?></th>
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
						<i style="margin-left:<?php echo $margin_left;?>" class="fa <?php echo $icon_act;?> icon-pointer" title="<?php echo $filterent['act'] .'/'. $filterent['tracker'];?>" onclick="javascript:getURL('status_logs_filter.php?getrulenum=<?="{$filterent['rulenum']},{$filterent['tracker']},{$filterent['act']}"; ?>', outputrule);"></i>
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

		if ($filterent['version'] == '6') {
			$ipproto = "inet6";
			$filterent['srcip'] = "[{$filterent['srcip']}]";
			$filterent['dstip'] = "[{$filterent['dstip']}]";
		} else {
			$ipproto = "inet";
		}

		$srcstr = $filterent['srcip'] . get_port_with_service($filterent['srcport'], $proto);
		$src_htmlclass = str_replace(array('.', ':'), '-', $filterent['srcip']);
		$dststr = $filterent['dstip'] . get_port_with_service($filterent['dstport'], $proto);
		$dst_htmlclass = str_replace(array('.', ':'), '-', $filterent['dstip']);
?>
					<td class="text-nowrap">
						<i class="fa fa-info icon-pointer icon-primary" onclick="javascript:resolve_with_ajax('<?="{$filterent['srcip']}"; ?>');" title="<?=gettext("Click to resolve")?>">
						</i>

						<a class="fa fa-minus-square-o icon-pointer icon-primary" href="easyrule.php?<?="action=block&amp;int={$int}&amp;src={$filterent['srcip']}&amp;ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Add to Block List")?>" onclick="return confirm('<?=gettext("Do you really want to add this BLOCK rule?")?>')">
						</a>

						<?=$srcstr . '<span class="RESOLVE-' . $src_htmlclass . '"></span>'?>
					</td>
					<td class="text-nowrap">
						<i class="fa fa-info icon-pointer icon-primary; ICON-<?= $dst_htmlclass; ?>" onclick="javascript:resolve_with_ajax('<?="{$filterent['dstip']}"; ?>');" title="<?=gettext("Click to resolve")?>">
						</i>

						<a class="fa fa-plus-square-o icon-pointer icon-primary" href="easyrule.php?<?="action=pass&amp;int={$int}&amp;proto={$proto}&amp;src={$filterent['srcip']}&amp;dst={$filterent['dstip']}&amp;dstport={$filterent['dstport']}&amp;ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Pass this traffic")?>" onclick="return confirm('<?=gettext("Do you really want to add this PASS rule?")?>')">
						</a>
						<?=$dststr . '<span class="RESOLVE-' . $dst_htmlclass . '"></span>'?>
					</td>
<?php
		if ($filterent['proto'] == "TCP") {
			$filterent['proto'] .= ":{$filterent['tcpflags']}";
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
		print_info_box(gettext('No logs to display'));
	}
?>
		</div>
	</div>
</div>

<?php
} else {
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Last ")?><?=$nentries?> <?=gettext($allowed_logs[$logfile]["name"])?><?=gettext(" log entries")?></h2></div>
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
	if ($filtertext) {
		$rows = dump_clog($logfile_path, $nentries, true, array("$filtertext"));
	} else {
		$rows = dump_clog($logfile_path, $nentries, true, array());
	}
?>
			</tbody>
		</table>
<?php
	if ($rows == 0) {
		print_info_box(gettext('No logs to display'));
	}
?>
	</div>
</div>
<?php
}
?>

<div class="infoblock">
<?php
print_info_box('<a href="https://doc.pfsense.org/index.php/What_are_TCP_Flags%3F">' .
	gettext("TCP Flags") . '</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR' . '<br />' .
	'<i class="fa fa-minus-square-o icon-primary"></i> = Add to block list., <i class="fa fa-plus-square-o icon-primary"></i> = Pass traffic, <i class="fa fa-info icon-primary"></i> = Resolve');
?>
</div>

<?php
# Manage Log - Section/Form
manage_log_section();
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

	jQuery('span.RESOLVE-' + resolve_class).html(resolve_text);
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
		http_request.open('GET', url, true);
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
