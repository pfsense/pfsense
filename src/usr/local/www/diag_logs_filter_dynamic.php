<?php
/* $Id$ */
/*
	diag_logs_filter_dynamic.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved. 
 *  Copyright (c)  2004, 2005 Scott Ullrich
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

/*
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-hidden-nolongerincluded
##|*NAME=Hidden: No longer included page
##|*DESCR=Allow access to the 'Hidden: No longer included' page.
##|*MATCH=diag_logs_filter_dynamic.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter_log.inc");

$filter_logfile = "{$g['varlog_path']}/filter.log";

/* Hardcode this. AJAX doesn't do so well with large numbers */
$nentries = 50;

/* AJAX related routines */
handle_ajax($nentries, $nentries + 20);

if ($_POST['clear']) {
	clear_log_file($filter_logfile);
}

$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100);

$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("Firewall (Dynamic View)"));
$shortcut_section = "firewall";
include("head.inc");

?>

<script type="text/javascript">
//<![CDATA[
	lastsawtime = '<?=time(); ?>;';
	var lines = Array();
	var timer;
	var updateDelay = 25500;
	var isBusy = false;
	var isPaused = false;
	var nentries = <?=$nentries; ?>;
<?php
	if (isset($config['syslog']['reverse'])) {
		echo "var isReverse = true;\n";
	} else {
		echo "var isReverse = false;\n";
	}
?>
	/* Called by the AJAX updater */
	function format_log_line(row) {
		if (row[8] == '6') {
			srcIP = '[' + row[3] + ']';
			dstIP = '[' + row[5] + ']';
		} else {
			srcIP = row[3];
			dstIP = row[5];
		}

		if (row[4] == '') {
			srcPort = '';
		} else {
			srcPort = ':' + row[4];
		}
		if (row[6] == '') {
			dstPort = '';
		} else {
			dstPort = ':' + row[6];
		}

		var line =
			'<td>' + row[1] + '</td>' +
			'<td>' + row[2] + '</td>' +
			'<td>' + srcIP + srcPort + '</td>' +
			'<td>' + dstIP + dstPort + '</td>' +
			'<td>' + row[7] + '</td>' +
			'<td>' + row[0] + '</td>';

		return line;
	}
//]]>
</script>
<script src="/javascript/filter_log.js" type="text/javascript"></script>

<?php
$tab_array = array();
$tab_array[] = array(gettext("System"), false, "diag_logs.php");
$tab_array[] = array(gettext("Firewall"), true, "diag_logs_filter.php");
$tab_array[] = array(gettext("DHCP"), false, "diag_logs.php?logfile=dhcpd");
$tab_array[] = array(gettext("Portal Auth"), false, "diag_logs.php?logfile=portalauth");
$tab_array[] = array(gettext("IPsec"), false, "diag_logs.php?logfile=ipsec");
$tab_array[] = array(gettext("PPP"), false, "diag_logs.php?logfile=ppp");
$tab_array[] = array(gettext("VPN"), false, "diag_logs_vpn.php");
$tab_array[] = array(gettext("Load Balancer"), false, "diag_logs.php?logfile=relayd");
$tab_array[] = array(gettext("OpenVPN"), false, "diag_logs.php?logfile=openvpn");
$tab_array[] = array(gettext("NTP"), false, "diag_logs.php?logfile=ntpd");
$tab_array[] = array(gettext("Settings"), false, "diag_logs_settings.php");
display_top_tabs($tab_array);

$tab_array = array();
$tab_array[] = array(gettext("Normal View"), false, "/diag_logs_filter.php");
$tab_array[] = array(gettext("Dynamic View"), true, "/diag_logs_filter_dynamic.php");
$tab_array[] = array(gettext("Summary View"), false, "/diag_logs_filter_summary.php");
display_top_tabs($tab_array, false, 'nav nav-tabs');
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?=gettext('Last ') . $nentries . gettext(' records. ') . gettext('Pause ')?><input type="checkbox" onclick="javascript:toggle_pause();" />
		</h2>
	</div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("Time")?></th>
						<th><?=gettext("IF")?></th>
						<th><?=gettext("Source")?></th>
						<th><?=gettext("Destination")?></th>
						<th><?=gettext("Proto")?></th>
						<th></th> <!-- For the "Block" buttons-->
					</tr>
				</thead>
				<tbody id="filter-log-entries">
<?php
				$rowIndex = 0;
				$tcpcnt = 0;

				foreach ($filterlog as $filterent) {
					$evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
					$rowIndex++;
					if ($filterent['version'] == '6') {
						$srcIP = "[" . htmlspecialchars($filterent['srcip']) . "]";
						$dstIP = "[" . htmlspecialchars($filterent['dstip']) . "]";
					} else {
						$srcIP = htmlspecialchars($filterent['srcip']);
						$dstIP = htmlspecialchars($filterent['dstip']);
					}

					if ($filterent['srcport'])
						$srcPort = ":" . htmlspecialchars($filterent['srcport']);
					else
						$srcPort = "";

					if ($filterent['dstport'])
						$dstPort = ":" . htmlspecialchars($filterent['dstport']);
					else
						$dstPort = "";
?>
					<tr>
						<td><?=htmlspecialchars($filterent['time'])?></td>
						<td><?=htmlspecialchars($filterent['interface'])?></td>
						<td><?=$srcIP . $srcPort?></td>
						<td><?=$dstIP . $dstPort?></td>
<?php
						if ($filterent['proto'] == "TCP") {
							$filterent['proto'] .= ":{$filterent['tcpflags']}";
							$tcpcnt++;
						}
?>
						<td><?=htmlspecialchars($filterent['proto'])?></td>
						<td>
							<a href="#" class="btn btn-danger btn-xs" alt="<?=$filterent['act'];?>" title="<?=$filterent['act'];?> onclick="javascript:getURL('diag_logs_filter.php?getrulenum=<?="{$filterent['rulenum']},{$filterent['act']}"; ?>', outputrule);">
								<?=gettext('Block')?>
							</a>
						</td>
					</tr>
<?php
				} // e-o-foreach()
?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php

if ($tcpcnt > 0)
	print_info_box('<a href="https://doc.pfsense.org/index.php/What_are_TCP_Flags%3F">' .
					gettext("TCP Flags") . '</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR');
?>

<?php include("foot.inc");