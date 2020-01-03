<?php
/*
 * diag_traceroute.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Paul Taylor (paultaylor@winndixie.com)
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
##|*IDENT=page-diagnostics-traceroute
##|*NAME=Diagnostics: Traceroute
##|*DESCR=Allow access to the 'Diagnostics: Traceroute' page.
##|*MATCH=diag_traceroute.php*
##|-PRIV

require_once("guiconfig.inc");

$allowautocomplete = true;
$pgtitle = array(gettext("Diagnostics"), gettext("Traceroute"));
include("head.inc");

/* Max TTL of both traceroute and traceroute6 is 255, but in practice more than
   64 hops would most likely time out in the GUI. If a user requires a
   traceroute that long, they can use the CLI. */
define('MAX_TTL', 64);
define('DEFAULT_TTL', 18);

// Set defaults in case they are not supplied.
$do_traceroute = false;
$host = '';
$ttl = DEFAULT_TTL;
$ipproto = 'ipv4';
$sourceip = 'any';

if ($_POST || $_REQUEST['host']) {
	unset($input_errors);

	/* input validation */
	$reqdfields = explode(" ", "host ttl");
	$reqdfieldsn = array(gettext("Host"), gettext("ttl"));
	do_input_validation($_REQUEST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_REQUEST['ttl'] < 1) || ($_REQUEST['ttl'] > MAX_TTL)) {
		$input_errors[] = sprintf(gettext("Maximum number of hops must be between 1 and %s"), MAX_TTL);
	}
	$host = trim($_REQUEST['host']);
	$ipproto = $_REQUEST['ipproto'];
	if (($ipproto == "ipv4") && is_ipaddrv6($host)) {
		$input_errors[] = gettext("When using IPv4, the target host must be an IPv4 address or hostname.");
	}
	if (($ipproto == "ipv6") && is_ipaddrv4($host)) {
		$input_errors[] = gettext("When using IPv6, the target host must be an IPv6 address or hostname.");
	}

	$sourceip = $_REQUEST['sourceip'];
	$ttl = $_REQUEST['ttl'];
	$resolve = $_REQUEST['resolve'];
	$useicmp = $_REQUEST['useicmp'];

	if ($_POST && !$input_errors) {
		$do_traceroute = true;
	}

} else {
	$resolve = false;
	$useicmp = false;
}

if ($input_errors) {
	print_input_errors($input_errors);
}

/* Do the traceroute and show any error */
if ($do_traceroute) {
	$useicmpparam = isset($useicmp) ? "-I" : "";
	$n = isset($resolve) ? "" : "-n";

	$command = "/usr/sbin/traceroute";
	if ($ipproto == "ipv6") {
		$command .= "6";
		if (empty($n)) {
			$n = "-l";
		}
		$ifaddr = is_ipaddr($sourceip) ? $sourceip : get_interface_ipv6($sourceip);
	} else {
		$ifaddr = is_ipaddr($sourceip) ? $sourceip : get_interface_ip($sourceip);
	}

	if ($ifaddr && (is_ipaddr($host) || is_hostname($host))) {
		$srcip = "-s " . escapeshellarg($ifaddr);
	}

	$cmd = "{$command} {$n} {$srcip} -w 2 {$useicmpparam} -m " . escapeshellarg($ttl) . " " . escapeshellarg($host);
	$result = shell_exec($cmd);

	if (!$result) {
		print_info_box(sprintf(gettext('Error: %s could not be traced/resolved'), htmlspecialchars($host)));
	}
}

$form = new Form(false);

$section = new Form_Section('Traceroute');

$section->addInput(new Form_Input(
	'host',
	'*Hostname',
	'text',
	$host,
	['placeholder' => 'Hostname to trace.']
));

$section->addInput(new Form_Select(
	'ipproto',
	'*IP Protocol',
	$ipproto,
	array('ipv4' => 'IPv4', 'ipv6' => 'IPv6')
))->setHelp('Select the protocol to use.');

$section->addInput(new Form_Select(
	'sourceip',
	'*Source Address',
	$sourceip,
	array('any' => gettext('Any')) + get_possible_traffic_source_addresses(true)
))->setHelp('Select source address for the trace.');

$section->addInput(new Form_Select(
	'ttl',
	'Maximum number of hops',
	$ttl,
	array_combine(range(1, MAX_TTL), range(1, MAX_TTL))
))->setHelp('Select the maximum number of network hops to trace.');

$section->addInput(new Form_Checkbox(
	'resolve',
	'Reverse Address Lookup',
	'',
	$resolve
))->setHelp('When checked, traceroute will attempt to perform a PTR lookup to locate hostnames for hops along the path. This will slow down the process as it has to wait for DNS replies.');

$section->addInput(new Form_Checkbox(
	'useicmp',
	gettext("Use ICMP"),
	'',
	$useicmp
))->setHelp('By default, traceroute uses UDP but that may be blocked by some routers. Check this box to use ICMP instead, which may succeed. ');

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Traceroute',
	null,
	'fa-rss'
))->addClass('btn-primary');

print $form;

/* Show the traceroute results */
if ($do_traceroute && $result) {
?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Results')?></h2></div>
		<div class="panel-body">
<?php
	print('<pre>' . $result . '</pre>');
?>
		</div>
	</div>
<?php
}

include("foot.inc");
?>
