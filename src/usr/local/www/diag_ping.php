<?php
/*
 * diag_ping.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2005 Bob Zoller (bob@kludgebox.com)
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
##|*IDENT=page-diagnostics-ping
##|*NAME=Diagnostics: Ping
##|*DESCR=Allow access to the 'Diagnostics: Ping' page.
##|*MATCH=diag_ping.php*
##|-PRIV

$allowautocomplete = true;
$pgtitle = array(gettext("Diagnostics"), gettext("Ping"));
require_once("guiconfig.inc");

define('MAX_COUNT', 10);
define('DEFAULT_COUNT', 3);
define('MAX_WAIT', 10);
define('DEFAULT_COUNT', 1);
$do_ping = false;
$host = '';
$count = DEFAULT_COUNT;
$wait = DEFAULT_WAIT;

if ($_POST || $_REQUEST['host']) {
	unset($input_errors);
	unset($do_ping);

	/* input validation */
	$reqdfields = explode(" ", "host count");
	$reqdfieldsn = array(gettext("Host"), gettext("Count"));
	do_input_validation($_REQUEST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_REQUEST['count'] < 1) || ($_REQUEST['count'] > MAX_COUNT)) {
		$input_errors[] = sprintf(gettext("Count must be between 1 and %s"), MAX_COUNT);
	}
	
	if (($_REQUEST['wait'] < 1) || ($_REQUEST['wait'] > MAX_WAIT)) {
		$input_errors[] = sprintf(gettext("Wait must be between 1 and %s"), MAX_WAIT);
	}
	
	$host = trim($_REQUEST['host']);
	$ipproto = $_REQUEST['ipproto'];
	if (($ipproto == "ipv4") && is_ipaddrv6($host)) {
		$input_errors[] = gettext("When using IPv4, the target host must be an IPv4 address or hostname.");
	}
	if (($ipproto == "ipv6") && is_ipaddrv4($host)) {
		$input_errors[] = gettext("When using IPv6, the target host must be an IPv6 address or hostname.");
	}

	if (!$input_errors) {
		if ($_POST) {
			$do_ping = true;
		}
		if (isset($_REQUEST['sourceip'])) {
			$sourceip = $_REQUEST['sourceip'];
		}
		$count = $_REQUEST['count'];
		if (preg_match('/[^0-9]/', $count)) {
			$count = DEFAULT_COUNT;
		}
		$count = $_REQUEST['wait'];
		if (preg_match('/[^0-9]/', wait)) {
			$count = DEFAULT_WAIT;
		}
	}
}

if ($do_ping) {
?>
	<script type="text/javascript">
	//<![CDATA[
	window.onload=function() {
		document.getElementById("pingCaptured").wrap='off';
	}
	//]]>
	</script>
<?php
	$ifscope = '';
	$command = "/sbin/ping";
	if ($ipproto == "ipv6") {
		$command .= "6";
		$ifaddr = is_ipaddr($sourceip) ? $sourceip : get_interface_ipv6($sourceip);
		if (is_linklocal($ifaddr)) {
			$ifscope = get_ll_scope($ifaddr);
		}
	} else {
		$ifaddr = is_ipaddr($sourceip) ? $sourceip : get_interface_ip($sourceip);
	}

	if ($ifaddr && (is_ipaddr($host) || is_hostname($host))) {
		$srcip = "-S" . escapeshellarg($ifaddr);
		if (is_linklocal($host) && !strstr($host, "%") && !empty($ifscope)) {
			$host .= "%{$ifscope}";
		}
	}

	$cmd = "{$command} {$srcip} -c" . escapeshellarg($count) . " -i " . escapeshellarg($wait) . " " . escapeshellarg($host);
	//echo "Ping command: {$cmd}\n";
	$result = shell_exec($cmd);

	if (empty($result)) {
		$input_errors[] = sprintf(gettext('Host "%s" did not respond or could not be resolved.'), $host);
	}

}

include('head.inc');

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form(false);

$section = new Form_Section('Ping');

$section->addInput(new Form_Input(
	'host',
	'*Hostname',
	'text',
	$host,
	['placeholder' => 'Hostname to ping']
));

$section->addInput(new Form_Select(
	'ipproto',
	'*IP Protocol',
	$ipproto,
	['ipv4' => 'IPv4', 'ipv6' => 'IPv6']
));

$section->addInput(new Form_Select(
	'sourceip',
	'*Source address',
	$sourceip,
	array('' => gettext('Automatically selected (default)')) + get_possible_traffic_source_addresses(true)
))->setHelp('Select source address for the ping.');

$section->addInput(new Form_Select(
	'count',
	'Maximum number of pings',
	$count,
	array_combine(range(1, MAX_COUNT), range(1, MAX_COUNT))
))->setHelp('Select the maximum number of pings.');

$section->addInput(new Form_Select(
	'wait',
	'Number of seconds to wait between pings',
	$wait,
	array_combine(range(1, MAX_WAIT), range(1, MAX_WAIT))
))->setHelp('Select the number of seconds to wait between pings.');

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Ping',
	null,
	'fa-rss'
))->addClass('btn-primary');

print $form;

if ($do_ping && !empty($result) && !$input_errors) {
?>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext('Results')?></h2>
		</div>

		<div class="panel-body">
			<pre><?= $result ?></pre>
		</div>
	</div>
<?php
}

include('foot.inc');
