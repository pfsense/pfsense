<?php
/* $Id$ */
/*
	services_dyndns.php

	Copyright (C) 2008 Ermal Luçi
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

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
	pfSense_BUILDER_BINARIES:	/usr/bin/host
	pfSense_MODULE: dyndns
*/

##|+PRIV
##|*IDENT=page-services-dynamicdnsclients
##|*NAME=Services: Dynamic DNS clients page
##|*DESCR=Allow access to the 'Services: Dynamic DNS clients' page.
##|*MATCH=services_dyndns.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['dyndnses']['dyndns'])) {
	$config['dyndnses']['dyndns'] = array();
}

$a_dyndns = &$config['dyndnses']['dyndns'];

if ($_GET['act'] == "del") {
	$conf = $a_dyndns[$_GET['id']];
	@unlink("{$g['conf_path']}/dyndns_{$conf['interface']}{$conf['type']}" . escapeshellarg($conf['host']) . "{$conf['id']}.cache");
	unset($a_dyndns[$_GET['id']]);

	write_config();
	services_dyndns_configure();

	header("Location: services_dyndns.php");
	exit;
}

$pgtitle = array(gettext("Services"), gettext("Dynamic DNS clients"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

$tab_array = array();
$tab_array[] = array(gettext("DynDns"), true, "services_dyndns.php");
$tab_array[] = array(gettext("RFC 2136"), false, "services_rfc2136.php");
display_top_tabs($tab_array);
?>
<form action="services_dyndns.php" method="post" name="iform" id="iform">
	<div class="table-responsive">
		<table class="table table-striped table-hover table-condensed">
			<thead>
				<tr>
					<th><?=gettext("Interface")?></th>
					<th><?=gettext("Service")?></th>
					<th><?=gettext("Hostname")?></th>
					<th><?=gettext("Cached IP")?></th>
					<th><?=gettext("Description")?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
<?php
$i = 0;
foreach ($a_dyndns as $dyndns):
?>
				<tr<?=!isset($dyndns['enable'])?' class="disabled""':''?>>
					<td>
<?php
	$iflist = get_configured_interface_with_descr();
	foreach ($iflist as $if => $ifdesc) {
		if ($dyndns['interface'] == $if) {
			print($ifdesc);

			break;
		}
	}

	$groupslist = return_gateway_groups_array();
	foreach ($groupslist as $if => $group) {
		if ($dyndns['interface'] == $if) {
			print($if);
			break;
		}
	}
?>
					</td>
					<td>
<?php
	$types = explode(",", DYNDNS_PROVIDER_DESCRIPTIONS);
	$vals = explode(" ", DYNDNS_PROVIDER_VALUES);

	for ($j = 0; $j < count($vals); $j++) {
		if ($vals[$j] == $dyndns['type']) {
			print(htmlspecialchars($types[$j]));

			break;
		}
	}
?>
					</td>
					<td>
<?php
	print(htmlspecialchars($dyndns['host']));
?>
					</td>
					<td>
<?php
	$filename = "{$g['conf_path']}/dyndns_{$dyndns['interface']}{$dyndns['type']}" . escapeshellarg($dyndns['host']) . "{$dyndns['id']}.cache";
	$filename_v6 = "{$g['conf_path']}/dyndns_{$dyndns['interface']}{$dyndns['type']}" . escapeshellarg($dyndns['host']) . "{$dyndns['id']}_v6.cache";
	if (file_exists($filename)) {
		$ipaddr = dyndnsCheckIP($dyndns['interface']);
		$cached_ip_s = explode(":", file_get_contents($filename));
		$cached_ip = $cached_ip_s[0];

		if ($ipaddr != $cached_ip)
			print('<font color="red">');
		else
			print('<font color="green">');

		print(htmlspecialchars($cached_ip));
		print('</font>');
	} else if (file_exists($filename_v6)) {
		$ipv6addr = get_interface_ipv6($dyndns['interface']);
		$cached_ipv6_s = explode("|", file_get_contents($filename_v6));
		$cached_ipv6 = $cached_ipv6_s[0];

		if ($ipv6addr != $cached_ipv6)
			print('<font color="red">');
		else
			print('<font color="green">');

		print(htmlspecialchars($cached_ipv6));
		print('</font>');
	} else {
		print('N/A');
	}
?>
					</td>
					<td>
<?php
	print(htmlspecialchars($dyndns['descr']));
?>
					</td>
					<td>
						<a href="services_dyndns_edit.php?id=<?=$i?>" class="btn btn-xs btn-info"><?=gettext('Edit')?></a>
						<a href="services_dyndns.php?act=del&amp;id=<?=$i?>" class="btn btn-xs btn-danger"><?=gettext("Delete")?></a>
					</td>
				</tr>
<?php
	$i++;
	endforeach;
?>
			</tbody>
	  </table>
	</div>
</form>

<nav class="action-buttons">
	<a href="services_dyndns_edit.php" class="btn btn-sm btn-success"><?=gettext('Add')?></a>
</nav>

<?php

print_info_box(gettext("IP addresses appearing in green are up to date with Dynamic DNS provider. " .
			           "You can force an update for an IP address on the edit page for that service."));

include("foot.inc");