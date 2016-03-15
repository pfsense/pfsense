<?php
/*
	diag_ping.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2003-2005 Bob Zoller (bob@kludgebox.com) and Manuel Kasper <mk@neon1.net>
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
$do_ping = false;
$host = '';
$count = DEFAULT_COUNT;

function create_sourceaddresslist() {
	$sourceips = get_possible_traffic_source_addresses(true);

	$list = array("" => gettext('Default'));

	foreach ($sourceips as $sipvalue => $sipname) {
		$list[$sipvalue] = $sipname;
	}

	return $list;
}

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

$form = new Form(false);

$section = new Form_Section('Ping');

$section->addInput(new Form_Input(
	'host',
	'Hostname',
	'text',
	$host,
	['placeholder' => 'Hostname to ping']
));

$section->addInput(new Form_Select(
	'ipproto',
	'IP Protocol',
	$ipproto,
	['ipv4' => 'IPv4', 'ipv6' => 'IPv6']
));

$section->addInput(new Form_Select(
	'sourceip',
	'Source address',
	$sourceip,
	create_sourceaddresslist()
))->setHelp('Select source address for the ping');

$section->addInput(new Form_Select(
	'count',
	'Maximum number of pings',
	$count,
	array_combine(range(1, MAX_COUNT), range(1, MAX_COUNT))
))->setHelp('Select the maximum number of pings');

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
