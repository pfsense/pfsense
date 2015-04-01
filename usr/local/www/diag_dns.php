<?php
/*
	diag_dns.php

	Copyright© 2015 Rubicon Communications, LLC (Netgate)
	This file is a part of pfSense®

	Copyright (C) 2009 Jim Pingle (jpingle@gmail.com)
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	notice, this list of conditions and the following disclaimer in the
	documentation and/or other materials provided with the distribution.
s
	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

/*
	pfSense_MODULE: dns
*/

require("guiconfig.inc");
define('NO_BUTTON', false);
$pgtitle = array(gettext("Diagnostics"),gettext("DNS lookup"));
$host = trim($_REQUEST['host'], " \t\n\r\0\x0B[];\"'");
$host_esc = escapeshellarg($host);

if(isset($_POST['create_alias']))
	$host = $_POST['host'] = $_POST['alias'];

$host_esc = escapeshellarg($host);

/* If this section of config.xml has not been populated yet we need to set it up
*/
if (!is_array($config['aliases']['alias'])) {
	$config['aliases']['alias'] = array();
}
$a_aliases = &$config['aliases']['alias'];

$aliasname = str_replace(array(".","-"), "_", $host);
$alias_exists = false;
$counter=0;
foreach($a_aliases as $a) {
	if($a['name'] == $aliasname) {
		$alias_exists = true;
		$id=$counter;
	}
	$counter++;
}

if(isset($_POST['create_alias']) && (is_hostname($host) || is_ipaddr($host))) {
	if($_POST['override'])
		$override = true;

	$resolved = gethostbyname($host);

	$type = "hostname";
	if($resolved) {
		$resolved = array();
		exec("/usr/bin/drill {$host_esc} A | /usr/bin/grep {$host_esc} | /usr/bin/grep -v ';' | /usr/bin/awk '{ print $5 }'", $resolved);
		$isfirst = true;
		foreach($resolved as $re) {
			if($re != "") {
				if(!$isfirst)
					$addresses .= " ";
				$addresses .= rtrim($re) . "/32";
				$isfirst = false;
			}
		}
		$newalias = array();

	/*	if($override)  */
			$alias_exists = false;

		if($alias_exists == false) {
			$newalias['name'] = $aliasname;
			$newalias['type'] = "network";
			$newalias['address'] = $addresses;
			$newalias['descr'] = "Created from Diagnostics-> DNS Lookup";
			if($override)
				$a_aliases[$id] = $newalias;
			else
				$a_aliases[] = $newalias;
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
			if($query_time == "")
				$query_time = gettext("No response");
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
			if ($host != $resolved)
				$hostname = $resolved;
		} elseif (is_hostname($host)) {
			$type = "hostname";
			$resolved = gethostbyname($host);
			if($resolved) {
				$resolved = array();
				exec("/usr/bin/drill {$host_esc} A | /usr/bin/grep {$host_esc} | /usr/bin/grep -v ';' | /usr/bin/awk '{ print $5 }'", $resolved);
			}
			$hostname = $host;
			if ($host != $resolved)
				$ipaddr = $resolved[0];
		}

		if ($host == $resolved) {
			$resolved = gettext("No record found");
		}
	}
}

if( ($_POST['host']) && ($_POST['dialog_output']) ) {
	display_host_results ($host,$resolved,$dns_speeds);
	exit;
}

function display_host_results ($address,$hostname,$dns_speeds) {
	$map_lengths = function($element) { return strlen($element[0]); };

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
if ($input_errors)
	print_input_errors($input_errors);
else if (!$resolved && $type)
	print('<div class="alert alert-warning" role="alert">' . gettext("Host \"") . $host . "\"" . gettext(" could not be resolved") . '</div>');

if($createdalias)
   print('<div class="alert alert-success" role="alert">Alias was created/updated successfully</div>');
?>

<?php if (!$input_errors && $ipaddr) { ?>
<form action="diag_dns.php" method="post" name="iform" id="iform">

<?php
	unset($dns_results);
	if ($resolved && $type) {
		$dns_results = array();
		$found = 0;
		$dns_results[$found] = array("Address(es)");
		if(is_array($resolved)) {
			foreach($resolved as $hostitem) {
				if($hostitem != "") {
					$found++;
					$dns_results[$found] = array($hostitem);
				}
			}
		} else {
			$dns_results[1] = array($resolved);
	}
 }
?>

<!-- Second table displays the server resolution times -->
<?php
	$timing_results = array();
	$timing_results[0] = array("Name server", "Query time");

	if(is_array($dns_speeds)) {
		$i = 1;

		foreach($dns_speeds as $qt):
		  $timing_results[$i++] = array($qt['dns_server'], $qt['query_time']);
		endforeach;
	}

?>

<!-- Third table displays "More information" -->
<?php
	$more_information = array();
	$more_information[0] = array("");
	$more_information[1] = array("<a href =\"/diag_ping.php?host=" . htmlspecialchars($host) . "&amp;interface=wan&amp;count=3\">" . gettext("Ping") . "</a>");
	$more_information[2] = array("<a href =\"/diag_traceroute.php?host=" . htmlspecialchars($host) . "&amp;ttl=18\">" . gettext("Traceroute") . "</a>");
	$more_information[3] = array("<font size=\"-1\">\r\n" . "NOTE: The following links are to external services, so their reliability cannot be guaranteed. They use only the first IP address returned above.\r\n");
	$more_information[4] = array("<a target=\"_blank\" href=\"http://private.dnsstuff.com/tools/whois.ch?ip=" . $ipaddr . "\">" . gettext("IP WHOIS @ DNS Stuff") . "</a>");
	$more_information[5] = array("<a target=\"_blank\" href=\"http://private.dnsstuff.com/tools/ipall.ch?ip=" . $ipaddr . "\">" . gettext("IP Info @ DNS Stuff") . "</a>");
?>

<?php
}

require('classes/Form.class.php');

$form = new Form(NO_BUTTON);
$section = new Form_Section('DNS Lookup');

if( isset($dns_results))
	$section->addInput(new Form_Table("Results", $dns_results));

if(isset($timing_results))
	$section->addInput(new Form_Table("Timing", $timing_results));

$form->addGlobal(new Form_Button(
	'lookup',
	'Lookup'
));

$section->addInput(new Form_Input(
	'alias',
	'',
	'hidden',
	$host,
	['placeholder' => 'Description']
));

$section->addInput(new Form_Input(
	'host',
	'Host name',
	'text',
	$pconfig['descr'],
	['placeholder' => 'Description']
))->setWidth(3)->setHelp(gettext('Host name to look up.'));

if($found > 0)
	{
	$form->addGlobal(new Form_Button(
	   'create_alias',
	   'Add alias'
	))->removeClass('btn-primary')->addClass('btn-success');
}

$form->add($section);
print $form;

if($found > 0) {
	print("<br />");
	$form2 = new Form(NO_BUTTON);
	$section2 = new Form_Section('More information');
	$section2->addInput(new Form_Table("", $more_information));
	$form2->add($section2);
	print $form2;
}
include("foot.inc");
?>
