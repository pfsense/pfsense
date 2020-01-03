<?php
/*
 * diag_dns.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-diagnostics-dns
##|*NAME=Diagnostics: DNS Lookup
##|*DESCR=Allow access to the 'Diagnostics: DNS Lookup' page.
##|*MATCH=diag_dns.php*
##|-PRIV

$pgtitle = array(gettext("Diagnostics"), gettext("DNS Lookup"));
require_once("guiconfig.inc");

$host = trim($_REQUEST['host'], " \t\n\r\0\x0B[];\"'");

init_config_arr(array('aliases', 'alias'));
$a_aliases = &$config['aliases']['alias'];

$aliasname = substr(str_replace(array(".", "-"), "_", $host), 0, 31);
$alias_exists = false;
$counter = 0;
foreach ($a_aliases as $a) {
	if ($a['name'] == $aliasname) {
		$alias_exists = true;
		$id = $counter;
	}
	$counter++;
}

function resolve_host_addresses($host) {
	$recordtypes = array(DNS_A, DNS_AAAA, DNS_CNAME);
	$dnsresult = array();
	$resolved = array();
	$errreporting = error_reporting();
	error_reporting($errreporting & ~E_WARNING);// dns_get_record throws a warning if nothing is resolved..
	foreach ($recordtypes as $recordtype) {
		$tmp = dns_get_record($host, $recordtype);
		if (is_array($tmp)) {
			$dnsresult = array_merge($dnsresult, $tmp);
		}
	}
	error_reporting($errreporting);// restore original php warning/error settings.

	foreach ($dnsresult as $item) {
		$newitem = array();
		$newitem['type'] = $item['type'];
		switch ($item['type']) {
			case 'CNAME':
				$newitem['data'] = $item['target'];
				$resolved[] = $newitem;
				break;
			case 'A':
				$newitem['data'] = $item['ip'];
				$resolved[] = $newitem;
				break;
			case 'AAAA':
				$newitem['data'] = $item['ipv6'];
				$resolved[] = $newitem;
				break;
		}
	}
	return $resolved;
}

if (isAllowedPage('firewall_aliases_edit.php') && isset($_POST['create_alias']) && (is_hostname($host) || is_ipaddr($host))) {
	$resolved = gethostbyname($host);
	$type = "hostname";
	if ($resolved) {
		$resolved = resolve_host_addresses($host);
		$isfirst = true;
		$addresses = "";
		foreach ($resolved as $re) {
			if ($re['data'] != "") {
				if (!$isfirst) {
					$addresses .= " ";
				}
				$re = rtrim($re['data']);
				if (is_ipaddr($re)) {
					$sn = is_ipaddrv6($re) ? '/128' : '/32';
				} else {
					// The name was a CNAME and resolved to another name, rather than an address.
					// In this case the alias entry will have a FQDN, so do not put a CIDR after it.
					$sn = "";
				}
				$addresses .= $re . $sn;
				$isfirst = false;
			}
		}
		if ($addresses == "") {
			$couldnotcreatealias = true;
		} else {
			$newalias = array();
			$newalias['name'] = $aliasname;
			$newalias['type'] = "network";
			$newalias['address'] = $addresses;
			$newalias['descr'] = gettext("Created from Diagnostics-> DNS Lookup");
			if ($alias_exists) {
				$a_aliases[$id] = $newalias;
			} else {
				$a_aliases[] = $newalias;
			}
			write_config(gettext("Created an alias from Diagnostics - DNS Lookup page."));
			$createdalias = true;
		}
	} else {
		$couldnotcreatealias = true;
	}
}

if ($_POST) {
	unset($input_errors);

	$reqdfields = explode(" ", "host");
	$reqdfieldsn = explode(",", "Host");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!is_hostname(rtrim($host, '.')) && !is_ipaddr($host)) {
		$input_errors[] = gettext("Host must be a valid hostname or IP address.");
	} else {
		// Test resolution speed of each DNS server.
		$dns_speeds = array();
		$dns_servers = array();
		exec("/usr/bin/grep nameserver /etc/resolv.conf | /usr/bin/cut -f2 -d' '", $dns_servers);
		foreach ($dns_servers as $dns_server) {
			$query_time = exec("/usr/bin/drill {$host_esc} " . escapeshellarg("@" . trim($dns_server)) . " | /usr/bin/grep Query | /usr/bin/cut -d':' -f2");
			if ($query_time == "") {
				$query_time = gettext("No response");
			}
			$new_qt = array();
			$new_qt['dns_server'] = $dns_server;
			$new_qt['query_time'] = $query_time;
			$dns_speeds[] = $new_qt;
			unset($new_qt);
		}
	}

	$type = "unknown";
	$resolved = array();
	$ipaddr = "";
	if (!$input_errors) {
		if (is_ipaddr($host)) {
			$type = "ip";
			$resolvedptr = gethostbyaddr($host);
			$ipaddr = $host;
			if ($host != $resolvedptr) {
				$tmpresolved = array();
				$tmpresolved['type'] = "PTR";
				$tmpresolved['data'] = $resolvedptr;
				$resolved[] = $tmpresolved;
			}
		} elseif (is_hostname(rtrim($host, '.'))) {
			$type = "hostname";
			$ipaddr = gethostbyname($host);
			$resolved = resolve_host_addresses($host);
		}
	}
}

if ($_POST['host'] && $_POST['dialog_output']) {
	$host = (isset($resolvedptr) ? $resolvedptr : $host);
	display_host_results ($ipaddr, $host, $dns_speeds);
	exit;
}

function display_host_results ($address, $hostname, $dns_speeds) {
	$map_lengths = function($element) { return strlen($element[0]); };

	echo gettext("IP Address") . ": " . htmlspecialchars($address) . " \n";
	echo gettext("Host Name") . ": " . htmlspecialchars($hostname) .  " \n";
	echo "\n";
	$text_table = array();
	$text_table[] = array(gettext("Server"), gettext("Query Time"));
	if (is_array($dns_speeds)) {
		foreach ($dns_speeds as $qt) {
			$text_table[] = array(trim($qt['dns_server']), trim($qt['query_time']));
		}
	}
	$col0_padlength = max(array_map($map_lengths, $text_table)) + 4;
	foreach ($text_table as $text_row) {
		echo str_pad($text_row[0], $col0_padlength) . $text_row[1] . "\n";
	}
}

include("head.inc");

/* Display any error messages resulting from user input */
if ($input_errors) {
	print_input_errors($input_errors);
} else if (!$resolved && $type) {
	print_info_box(sprintf(gettext('Host "%s" could not be resolved.'), $host), 'warning', false);
}

if ($createdalias) {
	if ($alias_exists) {
		print_info_box(gettext("Alias was updated successfully."), 'success');
	} else {
		print_info_box(gettext("Alias was created successfully."), 'success');
	}

	$alias_exists = true;
}

if ($couldnotcreatealias) {
	if ($alias_exists) {
		print_info_box(sprintf(gettext("Could not update alias for %s"), $host), 'warning', false);
	} else {
		print_info_box(sprintf(gettext("Could not create alias for %s"), $host), 'warning', false);
	}
}

$form = new Form(false);
$section = new Form_Section('DNS Lookup');

$section->addInput(new Form_Input(
	'host',
	'*Hostname',
	'text',
	$host,
	['placeholder' => 'Hostname to look up.']
));

$form->add($section);

$form->addGlobal(new Form_Button(
        'Submit',
        'Lookup',
        null,
        'fa-search'
))->addClass('btn-primary');

if (!empty($resolved) && isAllowedPage('firewall_aliases_edit.php')) {
	if ($alias_exists) {
		$button_text = gettext("Update alias");
	} else {
		$button_text = gettext("Add alias");
	}
	$form->addGlobal(new Form_Button(
		'create_alias',
		$button_text,
		null,
		'fa-plus'
	))->removeClass('btn-primary')->addClass('btn-success');
}

print $form;

if (!$input_errors && $type) {
	if ($resolved):
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Results')?></h2></div>
	<div class="panel-body">

		<table class="table">
		<thead>
			<tr>
				<th><?=gettext('Result')?></th>
				<th><?=gettext('Record type')?></th>
			</tr>
		</thead>
		<tbody>
<?php foreach ((array)$resolved as $hostitem):?>
		<tr>
			<td><?=htmlspecialchars($hostitem['data'])?></td><td><?=htmlspecialchars($hostitem['type'])?></td>
		</tr>
<?php endforeach; ?>
		</tbody>
		</table>
	</div>
</div>
<?php endif; ?>

<!-- Second table displays the server resolution times -->
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Timings')?></h2></div>
	<div class="panel-body">
		<table class="table">
		<thead>
			<tr>
				<th><?=gettext('Name server')?></th>
				<th><?=gettext('Query time')?></th>
			</tr>
		</thead>

		<tbody>
<?php foreach ((array)$dns_speeds as $qt):?>
		<tr>
			<td><?=htmlspecialchars($qt['dns_server'])?></td><td><?=htmlspecialchars($qt['query_time'])?></td>
		</tr>
<?php endforeach; ?>
		</tbody>
		</table>
	</div>
</div>

<!-- Third table displays "More information" -->
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('More Information')?></h2></div>
	<div class="panel-body">
		<ul class="list-group">
			<li class="list-group-item"><a href="/diag_ping.php?host=<?=htmlspecialchars($host)?>&amp;count=3"><?=gettext("Ping")?></a></li>
			<li class="list-group-item"><a href="/diag_traceroute.php?host=<?=htmlspecialchars($host)?>&amp;ttl=18"><?=gettext("Traceroute")?></a></li>
		</ul>
	</div>
</div>
<?php
}
if (!$input_errors):
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var original_host = <?=json_encode($host);?>;

	$('input[name="host"]').on('input', function() {
		if ($('#host').val() == original_host) {
			disableInput('create_alias', false);
		} else {
			disableInput('create_alias', true);
		}
	});
});
//]]>
</script>
<?php
endif;
include("foot.inc");
