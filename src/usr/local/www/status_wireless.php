<?php
/*
	status_wireless.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-diagnostics-wirelessstatus
##|*NAME=Status: Wireless
##|*DESCR=Allow access to the 'Status: Wireless' page.
##|*MATCH=status_wireless.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Status"), gettext("Wireless"));
$shortcut_section = "wireless";

include("head.inc");

$if = $_POST['if'];

if ($_GET['if'] != "") {
	$if = $_GET['if'];
}

$ciflist = get_configured_interface_with_descr();
if (empty($if)) {
	/* Find the first interface that is wireless */
	foreach ($ciflist as $interface => $ifdescr) {
		if (is_interface_wireless(get_real_interface($interface))) {
			$if = $interface;
			break;
		}
	}
}

$tab_array = array();

foreach ($ciflist as $interface => $ifdescr) {
	if (is_interface_wireless(get_real_interface($interface))) {
		$enabled = false;
		if ($if == $interface) {
			$enabled = true;
		}

		$tab_array[] = array(gettext("Status") . " ({$ifdescr})", $enabled, "status_wireless.php?if={$interface}");
	}
}

$rwlif = get_real_interface($if);

if ($_POST['rescanwifi'] != "") {
	mwexec_bg("/sbin/ifconfig {$rwlif} scan 2>&1");
	$savemsg = gettext("Rescan has been initiated in the background. Refresh this page in 10 seconds to see the results.");
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Nearby access points or ad-hoc peers")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th>SSID</th>
						<th>BSSID</th>
						<th>CHAN</th>
						<th>RATE</th>
						<th>RSSI</th>
						<th>INT</th>
						<th>CAPS</th>
					</tr>
				</thead>
				<tbody>
<?php
	exec("/sbin/ifconfig {$rwlif} list scan 2>&1", $states, $ret);
	/* Skip Header */
	array_shift($states);

	$counter = 0;
	foreach ($states as $state) {
		/* Split by Mac address for the SSID Field */
		$split = preg_split("/([0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f])/i", $state);
		preg_match("/([0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f])/i", $state, $bssid);
		$ssid = htmlspecialchars($split[0]);
		$bssid = $bssid[0];
		/* Split the rest by using spaces for this line using the 2nd part */
		$split = preg_split("/[ ]+/i", $split[1]);
		$channel = $split[1];
		$rate = $split[2];
		$rssi = $split[3];
		$int = $split[4];
		$caps = "$split[5] $split[6] $split[7] $split[8] $split[9] $split[10] $split[11] ";
?>
					<tr>
						<td>
							<?=$ssid?>
						</td>
						<td>
							<?=$bssid?>
						</td>
						<td>
							<?=$channel?>
						</td>
						<td>
							<?=$rate?>
						</td>
						<td>
							<?=$rssi?>
						</td>
						<td>
							<?=$int?>
						</td>
						<td>
							<?=$caps?>
						</td>
					</tr>
<?php
	} // e-o-foreach
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Associated or ad-hoc peers")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th>ADDR</th>
						<th>AID</th>
						<th>CHAN</th>
						<th>RATE</th>
						<th>RSSI</th>
						<th>IDLE</th>
						<th>TXSEQ</th>
						<th>RXSEQ</th>
						<th>CAPS</th>
						<th>ERP</th>
					</tr>
				</thead>
				<tbody>

<?php
	$states = array();
	exec("/sbin/ifconfig {$rwlif} list sta 2>&1", $states, $ret);
	array_shift($states);

	$counter=0;

	foreach ($states as $state) {
		$split = preg_split("/[ ]+/i", $state);
?>
					<tr>
<?php
		/* Split the rest by using spaces for this line using the 2nd part */
		for ($idx=0; $idx<10; $idx++) {
?>
						<td>
							<?=$split[$idx]?>
						</td>
<?php
		}
?>
					</tr>
<?php
	}
?>
				</tbody>
			</table>
		</div>
	</div>
</div>


<form action="status_wireless.php" method="post">
	<nav class="action-buttons">
		<input type="hidden" name="if" id="if" value="<?=htmlspecialchars($if)?>" />
		<button type="submit" class="btn btn-success" name="rescanwifi" id="rescanwifi" value="Rescan">
			<i class="fa fa-undo icon-embed-btn"></i>
			<?=gettext("Rescan")?>
		</button>
	</nav>
</form>

<?php
print_info_box('<b>Flags:</b> A = authorized, E = Extended Rate (802.11g), P = Power saving mode<br />' .
			   '<b>Capabilities:</b> E = ESS (infrastructure mode), I = IBSS (ad-hoc mode), P = privacy (WEP/TKIP/AES), ' .
			   'S = Short preamble, s = Short slot time', 'info');

include("foot.inc");
