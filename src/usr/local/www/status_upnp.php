<?php
/* $Id$ */
/*
	status_upnp.php
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>.
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
	pfSense_BUILDER_BINARIES:	/sbin/pfctl
	pfSense_MODULE: upnp
*/

##|+PRIV
##|*IDENT=page-status-upnpstatus
##|*NAME=Status: UPnP Status page
##|*DESCR=Allow access to the 'Status: UPnP Status' page.
##|*MATCH=status_upnp.php*
##|-PRIV

require("guiconfig.inc");

if ($_POST) {
	if ($_POST['clear']) {
		upnp_action('restart');
		$savemsg = gettext("Rules have been cleared and the daemon restarted");
	}
}

$rdr_entries = array();
exec("/sbin/pfctl -aminiupnpd -sn", $rdr_entries, $pf_ret);

$pgtitle = array(gettext("Status"),gettext("UPnP &amp; NAT-PMP Status"));
$shortcut_section = "upnp";

include("head.inc");

if ($savemsg)
	print_info_box($savemsg, 'success');

if(!$config['installedpackages'] || !$config['installedpackages']['miniupnpd']['config'][0]['iface_array'] ||
   !$config['installedpackages']['miniupnpd']['config'][0]['enable']) {

	print_info_box('UPnP is currently disabled.', 'danger');
	include("foot.inc");
	exit;
}

?>

<div class="panel-body panel-default">
	<div class="table-responsive">
		<table class="table table-striped table-hover table-condensed">
			<thead>
				<tr>
					<th><?=gettext("Port")?></th>
					<th><?=gettext("Protocol")?></th>
					<th><?=gettext("Internal IP")?></th>
					<th><?=gettext("Int. Port")?></th>
					<th><?=gettext("Description")?></th>
				</tr>
			</thead>
			<tbody>
<?php
$i = 0;

foreach ($rdr_entries as $rdr_entry) {
	if (preg_match("/on (.*) inet proto (.*) from any to any port = (.*) keep state label \"(.*)\" rtable [0-9] -> (.*) port (.*)/", $rdr_entry, $matches)) {
	$rdr_proto = $matches[2];
	$rdr_port = $matches[3];
	$rdr_label =$matches[4];
	$rdr_ip = $matches[5];
	$rdr_iport = $matches[6];

?>
				<tr>
					<td>
						<?=$rdr_port?>
					</td>
					<td>
						<?=$rdr_proto?>
					</td>
					<td>
						<?=$rdr_ip?>
					</td>
					<td>
						<?=$rdr_iport?>
					</td>
					<td>
						<?=$rdr_label?>
					</td>
				</tr>
<?php
	}
	$i++;
}
?>
			</tbody>
		</table>
	</div>
	<form action="status_upnp.php" method="post">
		<nav class="action-buttons">
			<input class="btn btn-danger" type="submit" name="clear" id="clear" value="<?=gettext("Clear all sessions")?>" />
		</nav>
	</form>
</div>

<?php
include("foot.inc");