<?php
/*
	status_upnp.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2010 Seth Mos <seth.mos@dds.nl>
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
/*
	pfSense_BUILDER_BINARIES:	/sbin/pfctl
	pfSense_MODULE: upnp
*/

##|+PRIV
##|*IDENT=page-status-upnpstatus
##|*NAME=Status: UPnP Status
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

$pgtitle = array(gettext("Status"),gettext("UPnP &amp; NAT-PMP"));
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
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
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
			<button class="btn btn-danger btn-sm" type="submit" name="clear" id="clear" value="<?=gettext("Clear all sessions")?>">
				<i class="fa fa-trash icon-embed-btn"></i>
				<?=gettext("Clear all sessions")?>
			</button>
		</nav>
	</form>
</div>

<?php
include("foot.inc");
