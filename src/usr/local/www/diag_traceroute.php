<?php
/*
	diag_traceroute.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Copyright (c)  2005 Paul Taylor (paultaylor@winndixie.com) and Manuel Kasper <mk@neon1.net>
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
##|*IDENT=page-diagnostics-traceroute
##|*NAME=Diagnostics: Traceroute
##|*DESCR=Allow access to the 'Diagnostics: Traceroute' page.
##|*MATCH=diag_traceroute.php*
##|-PRIV

require("guiconfig.inc");

$allowautocomplete = true;
$pgtitle = array(gettext("Diagnostics"), gettext("Traceroute"));
include("head.inc");

define('MAX_TTL', 64);
define('DEFAULT_TTL', 18);

$do_traceroute = false;
$host = '';
$ttl = DEFAULT_TTL;
$pconfig['ttl'] = DEFAULT_TTL;
$pconfig['ipproto'] = 'IPv4';
$pconfig['sourceip'] = 'Any';

function create_sourceaddresslist() {
	$list = array('any' => 'Any');

	$sourceips = get_possible_traffic_source_addresses(true);

	foreach ($sourceips as $sipvalue => $sipname) {
		$list[$sipvalue] = $sipname;
	}

	return($list);
}

if ($_POST || $_REQUEST['host']) {
	unset($input_errors);
	unset($do_traceroute);

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

	if (!$input_errors) {
		$host = $_REQUEST['host'];
	}

	$sourceip = $_REQUEST['sourceip'];
	$ttl = $_REQUEST['ttl'];
	$resolve = $_REQUEST['resolve'];
	$useicmp = $_REQUEST['useicmp'];

	if ($_POST) {
		$do_traceroute = true;
	}

} else {
	$resolve = false;
	$useicmp = false;
}

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form('Traceroute');

$section = new Form_Section('Traceroute');

$section->addInput(new Form_Input(
	'host',
	'Hostname',
	'text',
	$host,
	['placeholder' => 'Hostname to trace.']
));

$section->addInput(new Form_Select(
	'ipproto',
	'IP Protocol',
	$pconfig['ipproto'],
	array('ipv4' => 'IPv4', 'ipv6' => 'IPv6')
))->setHelp('Select the protocol to use');

$section->addInput(new Form_Select(
	'sourceip',
	'Source Address',
	$pconfig['sourceip'],
	create_sourceaddresslist()
))->setHelp('Select source address for the trace');

$section->addInput(new Form_Select(
	'ttl',
	'Maximum nuber of hops',
	$ttl,
	array_combine(range(1, MAX_TTL), range(1, MAX_TTL))
))->setHelp('Select the maximum number of network hops to trace');

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
print $form;

/* Show the traceroute results */
if (!$input_errors && $do_traceroute) {

	$useicmp = isset($_REQUEST['useicmp']) ? "-I" : "";
	$n = isset($resolve) ? "" : "-n";

	$command = "/usr/sbin/traceroute";
	if ($ipproto == "ipv6") {
		$command .= "6";
		$ifaddr = is_ipaddr($sourceip) ? $sourceip : get_interface_ipv6($sourceip);
	} else {
		$ifaddr = is_ipaddr($sourceip) ? $sourceip : get_interface_ip($sourceip);
	}

	if ($ifaddr && (is_ipaddr($host) || is_hostname($host))) {
		$srcip = "-s " . escapeshellarg($ifaddr);
	}

	$cmd = "{$command} {$n} {$srcip} -w 2 {$useicmp} -m " . escapeshellarg($ttl) . " " . escapeshellarg($host);
?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title">Results</h2></div>
		<div class="panel-body">
<?php
		if ($result = shell_exec($cmd)) {
			print('<pre>'.$result.'</pre>');
		} else {
			print('Error: ' . $host . ' ' . gettext("could not be traced/resolved"));
		}
?>
		</div>
	</div>
<?php
}

include("foot.inc");
?>
