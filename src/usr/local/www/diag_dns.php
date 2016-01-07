<?php
/*
	diag_dns.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-diagnostics-dns
##|*NAME=Diagnostics: DNS Lookup
##|*DESCR=Allow access to the 'Diagnostics: DNS Lookup' page.
##|*MATCH=diag_dns.php*
##|-PRIV

$pgtitle = array(gettext("Diagnostics"), gettext("DNS Lookup"));
require("guiconfig.inc");

$host = trim($_REQUEST['host'], " \t\n\r\0\x0B[];\"'");
$host_esc = escapeshellarg($host);

/* If this section of config.xml has not been populated yet we need to set it up
*/
if (!is_array($config['aliases']['alias'])) {
	$config['aliases']['alias'] = array();
}
$a_aliases = &$config['aliases']['alias'];

$aliasname = str_replace(array(".", "-"), "_", $host);
$alias_exists = false;
$counter = 0;
foreach ($a_aliases as $a) {
	if ($a['name'] == $aliasname) {
		$alias_exists = true;
		$id = $counter;
	}
	$counter++;
}

if (isset($_POST['create_alias']) && (is_hostname($host) || is_ipaddr($host))) {
	if ($_POST['override']) {
		$override = true;
	}
	$resolved = gethostbyname($host);
	$type = "hostname";
	if ($resolved) {
		$resolved = array();
		exec("/usr/bin/drill {$host_esc} A | /usr/bin/grep {$host_esc} | /usr/bin/grep -v ';' | /usr/bin/awk '{ print $5 }'", $resolved);
		$isfirst = true;
		foreach ($resolved as $re) {
			if ($re != "") {
				if (!$isfirst) {
					$addresses .= " ";
				}
				$addresses .= rtrim($re) . "/32";
				$isfirst = false;
			}
		}
		$newalias = array();
		if ($override) {
			$alias_exists = false;
		}
		if ($alias_exists == false) {
			$newalias['name'] = $aliasname;
			$newalias['type'] = "network";
			$newalias['address'] = $addresses;
			$newalias['descr'] = "Created from Diagnostics-> DNS Lookup";
			if ($override) {
				$a_aliases[$id] = $newalias;
			} else {
				$a_aliases[] = $newalias;
			}
			write_config();
			$createdalias = true;
		}
	}
}

if ($_POST) {
	unset($input_errors);

	$reqdfields = explode(" ", "host");
	$reqdfieldsn = explode(",", "Host");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!is_hostname($host) && !is_ipaddr($host)) {
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
	$resolved = "";
	$ipaddr = "";
	$hostname = "";
	if (!$input_errors) {
		if (is_ipaddr($host)) {
			$type = "ip";
			$resolved = gethostbyaddr($host);
			$ipaddr = $host;
			if ($host != $resolved) {
				$hostname = $resolved;
			}
		} elseif (is_hostname($host)) {
			$type = "hostname";
			$resolved = gethostbyname($host);
			if ($resolved) {
				$resolved = array();
				exec("/usr/bin/drill {$host_esc} A | /usr/bin/grep {$host_esc} | /usr/bin/grep -v ';' | /usr/bin/awk '{ print $5 }'", $resolved);
			}
			$hostname = $host;
			if ($host != $resolved) {
				$ipaddr = $resolved[0];
			}
		}

		if ($host == $resolved) {
			$resolved = gettext("No record found");
		}
	}
}

if (($_POST['host']) && ($_POST['dialog_output'])) {
	display_host_results ($host, $resolved, $dns_speeds);
	exit;
}

function display_host_results ($address, $hostname, $dns_speeds) {
	$map_lengths = function($element) { return strlen($element[0]); };

	echo gettext("IP Address") . ": {$address} \n";
	echo gettext("Host Name") . ": {$hostname} \n";
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
	print('<div class="alert alert-warning" role="alert">' . gettext("Host") .' "'. $host .'" '. gettext("could not be resolved") . '</div>');
}

if ($createdalias) {
	print('<div class="alert alert-success" role="alert">'.gettext("Alias was created/updated successfully").'</div>');
}

$form = new Form('Lookup');
$section = new Form_Section('DNS Lookup');

$section->addInput(new Form_Input(
	'host',
	'Hostname',
	'text',
	$host,
	['placeholder' => 'Hostname to look up.']
));

if (!empty($resolved)) {
	$form->addGlobal(new Form_Button(
		'create_alias',
		'Add alias'
	))->removeClass('btn-primary')->addClass('btn-success');
}

$form->add($section);
print $form;

if (!$input_errors && $type) {
	if ($resolved):
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">Results</h2></div>
	<div class="panel-body">
		<ul class="list-group">
<?
		foreach ((array)$resolved as $hostitem) {
?>
			<li class="list-group-item"><?=$hostitem?></li>
<?
			if ($hostitem != "") {
				$found++;
			}
		}
?>
		</ul>
	</div>
</div>
<?php endif; ?>

<!-- Second table displays the server resolution times -->
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">Timings</h2></div>
	<div class="panel-body">
		<table class="table">
		<thead>
			<tr>
				<th>Name server</th>
				<th>Query time</th>
			</tr>
		</thead>

		<tbody>
<?php foreach ((array)$dns_speeds as $qt):?>
		<tr>
			<td><?=$qt['dns_server']?></td><td><?=$qt['query_time']?></td>
		</tr>
<?php endforeach; ?>
		</tbody>
		</table>
	</div>
</div>

<!-- Third table displays "More information" -->
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">More information</h2></div>
	<div class="panel-body">
		<ul class="list-group">
			<li class="list-group-item"><a href="/diag_ping.php?host=<?=htmlspecialchars($host)?>&amp;count=3"><?=gettext("Ping")?></a></li>
			<li class="list-group-item"><a href="/diag_traceroute.php?host=<?=htmlspecialchars($host)?>&amp;ttl=18"><?=gettext("Traceroute")?></a></li>
		</ul>
		<h5><?=gettext("NOTE: The following links are to external services, so their reliability cannot be guaranteed.");?></h5>
		<ul class="list-group">
			<li class="list-group-item"><a target="_blank" href="http://private.dnsstuff.com/tools/whois.ch?ip=<?php echo $ipaddr; ?>"><?=gettext("IP WHOIS @ DNS Stuff");?></a></li>
			<li class="list-group-item"><a target="_blank" href="http://private.dnsstuff.com/tools/ipall.ch?ip=<?php echo $ipaddr; ?>"><?=gettext("IP Info @ DNS Stuff");?></a></li>
		</ul>
	</div>
</div>
<?php
}
include("foot.inc");
