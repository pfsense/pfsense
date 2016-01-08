<?php
/*
	status_gateways.php
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

##|+PRIV
##|*IDENT=page-status-gateways
##|*NAME=Status: Gateways
##|*DESCR=Allow access to the 'Status: Gateways' page.
##|*MATCH=status_gateways.php*
##|-PRIV

require("guiconfig.inc");

define('COLOR', true);
define('LIGHTGREEN', '#90EE90');
define('LIGHTCORAL', '#F08080');
define('KHAKI',		 '#F0E68C');
define('LIGHTGRAY',	 '#D3D3D3');
define('WHITE',		 '#FFFFFF');

$a_gateways = return_gateways_array();
$gateways_status = array();
$gateways_status = return_gateways_status(true);

$now = time();
$year = date("Y");

$pgtitle = array(gettext("Status"), gettext("Gateways"));
$shortcut_section = "gateways";
include("head.inc");

/* active tabs */
$tab_array = array();
$tab_array[] = array(gettext("Gateways"), true, "status_gateways.php");
$tab_array[] = array(gettext("Gateway Groups"), false, "status_gateway_groups.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Gateways')?></h2></div>
	<div class="panel-body">

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th><?=gettext("Name"); ?></th>
				<th><?=gettext("Gateway"); ?></th>
				<th><?=gettext("Monitor"); ?></th>
				<th><?=gettext("RTT"); ?></th>
				<th><?=gettext("Loss"); ?></th>
				<th><?=gettext("Status"); ?></th>
				<th><?=gettext("Description"); ?></th>
			</tr>
		</thead>
		<tbody>
<?php		foreach ($a_gateways as $gname => $gateway) {
?>
			<tr>
				<td>
					<?=htmlspecialchars($gateway['name']);?>
				</td>
				<td>
					<?php echo lookup_gateway_ip_by_name($gname);?>
				</td>
				<td>
<?php
					if ($gateways_status[$gname]) {
						echo $gateways_status[$gname]['monitorip'];
					} else {
						echo htmlspecialchars($gateway['monitorip']);
					}
?>
				</td>
				<td>
<?php
					if ($gateways_status[$gname]) {
						echo $gateways_status[$gname]['delay'];
					} else {
						echo gettext("Pending");
					}
?>
				</td>
				<td>
<?php
					if ($gateways_status[$gname]) {
						echo $gateways_status[$gname]['loss'];
					} else {
						echo gettext("Pending");
					}
?>
				</td>
<?php
				if ($gateways_status[$gname]) {
					$status = $gateways_status[$gname];
					if (stristr($status['status'], "force_down")) {
						$online = gettext("Offline (forced)");
						$bgcolor = LIGHTCORAL;
					} elseif (stristr($status['status'], "down")) {
						$online = gettext("Offline");
						$bgcolor = LIGHTCORAL;
					} elseif (stristr($status['status'], "loss")) {
						$online = gettext("Warning, Packetloss").': '.$status['loss'];
						$bgcolor = KHAKI;
					} elseif (stristr($status['status'], "delay")) {
						$online = gettext("Warning, Latency").': '.$status['delay'];
						$bgcolor = KHAKI;
					} elseif ($status['status'] == "none") {
						$online = gettext("Online");
						$bgcolor = LIGHTGREEN;
					}
				} else if (isset($gateway['monitor_disable'])) {
						$online = gettext("Online");
						$bgcolor = LIGHTGREEN;
				} else {
					$online = gettext("Pending");
					$bgcolor = LIGHTGRAY;
				}

				$lastchange = $gateways_status[$gname]['lastcheck'];

				if (!COLOR) {
				   $bgcolor = WHITE;
				}
?>

				<td bgcolor="<?=$bgcolor?>">
					<strong><?=$online?></strong> <?php
					if (!empty($lastchange)) { ?>
						<br /><i>Last checked <?=$lastchange?></i>
<?php				} ?>
				</td>

				<td>
					<?=htmlspecialchars($gateway['descr']); ?>
				</td>
			</tr>
<?php	} ?>	<!-- End-of-foreach -->
		</tbody>
	</table>
</div>

	</div>
</div>

<?php include("foot.inc"); ?>
