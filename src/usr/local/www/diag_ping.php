<?php
/*
 * diag_ping.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
require_once("diag_ping_shared.php");

$do_ping = false;
$host = '';
$count = DEFAULT_PING_COUNT;

if ($_POST || $_REQUEST['host']) {
	unset($input_errors);
	unset($do_ping);

	/* input validation */
	$reqdfields = explode(" ", "host count");
	$reqdfieldsn = array(gettext("Host"), gettext("Count"));
	do_input_validation($_REQUEST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_REQUEST['count'] < 1) || ($_REQUEST['count'] > MAX_PING_COUNT)) {
		$input_errors[] = sprintf(gettext("Count must be between 1 and %s"), MAX_PING_COUNT);
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
			$count = DEFAULT_PING_COUNT;
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

	$cmd = "{$command} {$srcip} -c" . escapeshellarg($count) . " " . escapeshellarg($host);
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

echo getPingForm($host, $ipproto, $sourceip, $count);

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
