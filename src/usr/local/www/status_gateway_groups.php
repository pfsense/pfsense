<?php
/*
	status_gateway_groups.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2010 Seth Mos <seth.mos@dds.nl>
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
##|*IDENT=page-status-gatewaygroups
##|*NAME=Status: Gateway Groups
##|*DESCR=Allow access to the 'Status: Gateway Groups' page.
##|*MATCH=status_gateway_groups.php*
##|-PRIV

define('COLOR', true);
define('LIGHTGREEN', '#90EE90');
define('LIGHTCORAL', '#F08080');
define('KHAKI',		 '#F0E68C');
define('LIGHTGRAY',	 '#D3D3D3');
define('LIGHTBLUE',	 '#ADD8E6');
define('WHITE',		 '#FFFFFF');

require("guiconfig.inc");

if (!is_array($config['gateways']['gateway_group'])) {
	$config['gateways']['gateway_group'] = array();
}

$a_gateway_groups = &$config['gateways']['gateway_group'];
$changedesc = gettext("Gateway Groups") . ": ";

$gateways_status = return_gateways_status();

$pgtitle = array(gettext("Status"), gettext("Gateway Groups"));
$shortcut_section = "gateway-groups";
include("head.inc");

$tab_array = array();
$tab_array[0] = array(gettext("Gateways"), false, "status_gateways.php");
$tab_array[1] = array(gettext("Gateway Groups"), true, "status_gateway_groups.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Gateway Groups')?></h2></div>
	<div class="panel-body">

<div class="table-responsive">
	<table class="table table-hover table-condensed table-striped">
		<thead>
			<tr>
				<th><?=gettext("Group Name"); ?></th>
				<th><?=gettext("Gateways"); ?></th>
				<th><?=gettext("Description"); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($a_gateway_groups as $gateway_group): ?>
			<tr>
				<td>
					<?=htmlspecialchars($gateway_group['name'])?>
				</td>
				<td>
					<table class="table table-bordered table-condensed">
<?php
						/* process which priorities we have */
						$priorities = array();
						foreach ($gateway_group['item'] as $item) {
							$itemsplit = explode("|", $item);
							$priorities[$itemsplit[1]] = true;
						}
						$priority_count = count($priorities);
						ksort($priorities);
?>
						<thead>
							<tr>
<?php
							// Make a column for each tier
							foreach ($priorities as $number => $tier) {
								echo "<th>" . sprintf(gettext("Tier %s"), $number) . "</th>";
							}
?>
							</tr>
						</thead>
						<tbody>
<?php
							/* inverse gateway group to gateway priority */
							$priority_arr = array();
							foreach ($gateway_group['item'] as $item) {
								$itemsplit = explode("|", $item);
								$priority_arr[$itemsplit[1]][] = $itemsplit[0];
							}
							ksort($priority_arr);
							$p = 1;
							foreach ($priority_arr as $number => $tier) {
								/* for each priority process the gateways */
								foreach ($tier as $member) {
									/* we always have $priority_count fields */
?>
							<tr>
<?php
									$c = 1;
									while ($c <= $priority_count) {
										$monitor = lookup_gateway_monitor_ip_by_name($member);
										if ($p == $c) {
											$status = $gateways_status[$monitor]['status'];
											if (stristr($status, "down")) {
													$online = gettext("Offline");
													$bgcolor = LIGHTCORAL;
											} elseif (stristr($status, "loss")) {
													$online = gettext("Warning, Packetloss");
													$bgcolor = KHAKI;
											} elseif (stristr($status, "delay")) {
													$online = gettext("Warning, Latency");
													$bgcolor = KHAKI;
											} elseif ($status == "none") {
													$online = gettext("Online");
													$bgcolor = LIGHTGREEN;
											} else {
												$online = gettext("Gathering data");
												$bgcolor = LIGHTBLUE;
											}

											if (!COLOR) {
												$bgcolor = WHITE;
											}
?>
								<td bgcolor="<?=$bgcolor?>">
									<?=htmlspecialchars($member);?>,<br/><?=$online?>
								</td>

<?php
										} else {
?>
								<td>
								</td>
<?php							}
										$c++;
									}
?>
							</tr>
<?php
								}
								$p++;
							}
?>
						</tbody>
					</table>
				</td>
				<td>
					<?=htmlspecialchars($gateway_group['descr'])?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

	</div>
</div>

<?php include("foot.inc");
