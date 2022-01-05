<?php
/*
 * status_ipsec.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-status-ipsec
##|*NAME=Status: IPsec
##|*DESCR=Allow access to the 'Status: IPsec' page.
##|*MATCH=status_ipsec.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("ipsec.inc");
require_once("service-utils.inc");

if ($_POST['act'] == 'connect') {
	/* Assume type is IKE */
	$type = empty($_POST['type']) ? 'ike' : $_POST['type'];
	ipsec_initiate_by_conid($type, $_POST['conid']);
} elseif ($_POST['act'] == 'disconnect') {
	/* Assume type is IKE */
	$type = empty($_POST['type']) ? 'ike' : $_POST['type'];
	ipsec_terminate_by_conid($type, $_POST['conid'], $_POST['uniqueid']);
}

// If this is just an AJAX call to update the table body, just generate the body and quit
if ($_REQUEST['ajax']) {
	print_ipsec_body();
	exit;
}

// Table body is composed here so that it can be more easily updated via AJAX
function print_ipsec_body() {
	global $config;

	if (!ipsec_enabled()) {
?>
<tr>
	<td colspan="10">
		<?php print_info_box(addslashes(gettext("IPsec is disabled.")), "warning", ""); ?>
	</td>
</tr>
<?php
		return;
	}
	if (!get_service_status(array('name' => 'ipsec'))) {
?>
<tr>
	<td colspan="10">
		<?php print_info_box(addslashes(gettext("IPsec daemon is stopped.")), "warning", ""); ?>
	</td>
</tr>
<?php
		return;
	}

	$cmap = ipsec_map_config_by_id();
	$status = ipsec_list_sa();

	$p1conids = array_column($status, 'con-id');
	$p1uniqueids = array_column($status, 'uniqueid');
	array_multisort($p1conids, SORT_NATURAL,
			$p1uniqueids, SORT_NUMERIC,
			$status);

	$p1connected = array();
	$p2connected = array();
	if (!is_array($status)) {
		$status = array();
	}
	foreach ($status as $ikesa) {
		list($ikeid, $reqid) = ipsec_id_by_conid($ikesa['con-id']);
		if (!array_key_exists($ikeid, $cmap)) {
			// Doesn't match known tunnel
			$p1connected[$ikesa['con-id']] = $ikesa['con-id'];
		} else {
			$p1connected[$ikeid] = $ph1idx = $ikeid;
		}
		if (array_key_exists('child-sas', $ikesa) && is_array($ikesa['child-sas'])) {
			$p2conids = array_column($ikesa['child-sas'], 'name');
			$p2uniqueids = array_column($ikesa['child-sas'], 'uniqueid');
			array_multisort($p2conids, SORT_NATURAL,
					$p2uniqueids, SORT_NUMERIC,
					$ikesa['child-sas']);

			foreach ($ikesa['child-sas'] as $childid => $childsa) {
				list($childikeid, $childreqid) = ipsec_id_by_conid($childsa['name']);
				if ($childreqid != null) {
					$p2connected[$childreqid] = $childsa['name'];
				} else {
					/* If this is IKEv2 w/o Split, mark all reqids for the P1 as connected */
					if (($cmap[$childikeid]['p1']['iketype'] == 'ikev2') &&
					    !isset($cmap[$childikeid]['p1']['splitconn']) &&
					    isset($cmap[$ikeid]['p2']) && is_array($cmap[$ikeid]['p2'])) {
						foreach ($cmap[$ikeid]['p2'] as $p2) {
							$p2connected[$p2['reqid']] = $childsa['name'];
						}
					}
				}
			}
		}
		$p2disconnected = array();
		if (!$cmap[$ikeid]['p1']['mobile'] &&
		    isset($cmap[$ikeid]) &&
		    is_array($cmap[$ikeid]) &&
		    is_array($cmap[$ikeid]['p2'])) {
			foreach ($cmap[$ikeid]['p2'] as $p2) {
				if (!array_key_exists($p2['reqid'], $p2connected)) {
					/* This P2 is not connected */
					$p2conid = ipsec_conid($cmap[$ikeid]['p1'], $p2);
					$p2disconnected[$p2conid] = $p2;
				}
			}
		}
?>

<tr>
	<td>
		<?= htmlspecialchars($ikesa['con-id']) ?>
		#<?= htmlspecialchars($ikesa['uniqueid']) ?>
	</td>
	<td>
		<?= htmlspecialchars($cmap[$ikeid]['p1']['descr']) ?>
		<br/>
		<a class="fa fa-pencil" href="vpn_ipsec_phase1.php?ikeid=<?= htmlspecialchars($ikeid) ?>"
			title="<?= htmlspecialchars(gettext("Edit Phase 1 Entry")) ?>">
		</a>
	</td>
	<td>
		<b>ID:</b>
<?php
		$localid = gettext("Unknown");
		if (!empty($ikesa['local-id'])) {
			if ($ikesa['local-id'] == '%any') {
				$localid = gettext('Any identifier');
			} else {
				$localid = $ikesa['local-id'];
			}
		}
?>
		<?= htmlspecialchars($localid) ?>
		<br/>
		<b><?= htmlspecialchars(gettext("Host:")); ?></b>
<?php
		$lhost = gettext("Unknown");
		if (!empty($ikesa['local-host'])) {
			$lhost = $ikesa['local-host'];
			if (!empty($ikesa['local-port'])) {
				if (is_ipaddrv6($ikesa['local-host'])) {
					$lhost = "[{$lhost}]";
				}
				$lhost .= ":{$ikesa['local-port']}";
			}
		}
?>
		<?= htmlspecialchars($lhost) ?>
		<br/>
		<b>SPI:</b>
		<?= htmlspecialchars( ($ikesa['initiator'] == 'yes') ? $ikesa['initiator-spi'] : $ikesa['responder-spi'] ) ?>
<?php		if (isset($ikesa['nat-local'])): ?>
			<?= htmlspecialchars(gettext("NAT-T")); ?>
<?php		endif; ?>
	</td>
	<td>
		<b>ID:</b>
<?php
		$identity = "";
		if (!empty($ikesa['remote-id'])) {
			if ($ikesa['remote-id'] == '%any') {
				$identity = gettext('Any identifier');
			} else {
				$identity = $ikesa['remote-id'];
			}
		}
		$remoteid = "";
		if (!empty($ikesa['remote-xauth-id'])) {
			$remoteid = $ikesa['remote-xauth-id'];
		} elseif (!empty($ikesa['remote-eap-id'])) {
			$remoteid = $ikesa['remote-eap-id'];
		} else {
			if (empty($identity)) {
				$identity = gettext("Unknown");
			}
		}
?>
<?php		if (!empty($remoteid)): ?>
		<?= htmlspecialchars($remoteid) ?><br/>
<?php		endif; ?>
		<?= htmlspecialchars($identity) ?>
		<br/>
		<b><?= htmlspecialchars(gettext("Host:")); ?></b>
<?php
		$rhost = gettext("Unknown");
		if (!empty($ikesa['remote-host'])) {
			$rhost = $ikesa['remote-host'];
			if (!empty($ikesa['remote-port'])) {
				if (is_ipaddrv6($ikesa['remote-host'])) {
					$rhost = "[{$rhost}]";
				}
				$rhost .= ":{$ikesa['remote-port']}";
			}
		}
?>
		<?= htmlspecialchars($rhost) ?>
<?php		if (isset($ikesa['nat-remote'])): ?>
		<?= htmlspecialchars(gettext("NAT-T")); ?>
<?php		endif; ?>
		<br/>
		<b>SPI:</b>
		<?= htmlspecialchars( ($ikesa['initiator'] == 'yes') ? $ikesa['responder-spi'] : $ikesa['initiator-spi'] ) ?>
	</td>
	<td>
		IKEv<?= htmlspecialchars($ikesa['version']) ?><br/>
<?php		if ($ikesa['initiator'] == 'yes'): ?>
		<?= htmlspecialchars(gettext("Initiator")); ?>
<?php		else: ?>
		<?= htmlspecialchars(gettext("Responder")); ?>
<?php		endif; ?>
	</td>
	<td>
<?php		if ($ikesa['version'] == 2): ?>
		<b><?= htmlspecialchars(gettext("Rekey:")) ?></b>
<?php			if (!empty($ikesa['rekey-time'])): ?>
		<?= htmlspecialchars($ikesa['rekey-time']) ?>s
		(<?= convert_seconds_to_dhms($ikesa['rekey-time']) ?>)
<?php			else: ?>
		<?= htmlspecialchars(gettext("Disabled")) ?>
<?php			endif; ?>
		<br/>
<?php		endif; ?>
		<b><?= htmlspecialchars(gettext("Reauth:")) ?></b>
<?php		if (!empty($ikesa['reauth-time'])): ?>
		<?= htmlspecialchars(htmlspecialchars($ikesa['reauth-time'])) ?>s
		(<?= convert_seconds_to_dhms($ikesa['reauth-time']) ?>)
<?php		else: ?>
		<?= htmlspecialchars(gettext("Disabled")) ?>
<?php		endif; ?>
	</td>
	<td>
		<?= htmlspecialchars($ikesa['encr-alg']) ?>
<?php		if (!empty($ikesa['encr-keysize'])): ?>
		(<?= htmlspecialchars($ikesa['encr-keysize']) ?>)
<?php		endif; ?>
		<br/>
		<?= htmlspecialchars($ikesa['integ-alg']) ?><br/>
		<?= htmlspecialchars($ikesa['prf-alg']) ?><br/>
		<?= htmlspecialchars($ikesa['dh-group']) ?><br/>
	</td>
	<td>
		<span<?= ($ikesa['state'] == 'ESTABLISHED') ? ' class="text-success"' : '' ; ?>>
		<?= htmlspecialchars(ucfirst(strtolower($ikesa['state']))) ?>
<?php		if ($ikesa['state'] == 'ESTABLISHED'): ?>
		<br/>
		<? printf(gettext('%1$s seconds (%2$s) ago'), htmlspecialchars($ikesa['established']), convert_seconds_to_dhms($ikesa['established'])) ?>
<?php		endif; ?>
		</span>
		<br/>
		<br/>
<?php		if (!in_array($ikesa['state'], array('ESTABLISHED', 'CONNECTING'))): ?>
		<?= ipsec_status_button('ajax', 'connect', 'all', $ikesa['con-id'], null, true) ?>
<?php		else: ?>
		<?= ipsec_status_button('ajax', 'disconnect', 'ike', $ikesa['con-id'], $ikesa['uniqueid'], true) ?>
<?php		endif; ?>
		<br>
<?php		if (empty($ikesa['child-sas']) && ($ikesa['state'] != 'CONNECTING')): ?>
		<br/>
		<?= ipsec_status_button('ajax', 'connect', 'all', $ikesa['con-id'], null, true) ?>
<?php			endif; ?>
	</td>
</tr>
<tr>
	<td colspan="10">
<?php		$child_key = "{$ikesa['con-id']}_{$ikesa['uniqueid']}_children"; ?>
	<div>
<?php		if ((count($ikesa['child-sas']) + count($p2disconnected)) > 0): ?>
		<a type="button" id="btnchildsa-<?= htmlspecialchars($child_key) ?>" class="btn btn-sm btn-info">
		<i class="fa fa-plus-circle icon-embed-btn"></i>
		<?= htmlspecialchars(gettext('Show child SA entries')) ?>
<?php
			$p2counts = count($ikesa['child-sas']) . " " . gettext("Connected");
			if (count($p2disconnected) > 0) {
				$p2counts .= ", " . count($p2disconnected) . " " . gettext("Disconnected");
			}
?>
		(<?= htmlspecialchars($p2counts) ?>)
<?php		endif; ?>
		</a>
	</div>
	<table class="table table-hover table-condensed" id="childsa-<?= htmlspecialchars($child_key) ?>" style="display:none">
	<thead>
	<tr class="bg-info">
		<th><?= htmlspecialchars(gettext("ID")) ?></th>
		<th><?= htmlspecialchars(gettext("Description")) ?></th>
		<th><?= htmlspecialchars(gettext("Local")) ?></th>
		<th><?= htmlspecialchars(gettext("SPI(s)")) ?></th>
		<th><?= htmlspecialchars(gettext("Remote")) ?></th>
		<th><?= htmlspecialchars(gettext("Times")) ?></th>
		<th><?= htmlspecialchars(gettext("Algo")) ?></th>
		<th><?= htmlspecialchars(gettext("Stats")) ?></th>
		<th><!-- Buttons --></th>
	</tr>
	</thead>
	<tbody>
<?php		if (is_array($ikesa['child-sas']) && (count($ikesa['child-sas']) > 0)) {
			foreach ($ikesa['child-sas'] as $childid => $childsa) {
				list($childikeid, $childreqid) = ipsec_id_by_conid($childsa['name']);
?>
	<tr>
		<td>
			<?= htmlspecialchars($childsa['name']) ?>:<br />
			#<?= htmlspecialchars($childsa['uniqueid']) ?>
		</td>
		<td>
<?php
				$p2descr = "";
				$p2uid = "";
				if (!empty($childreqid)) {
					/* IKEv1 or IKEv2+Split */
					$p2descr = $cmap[$childikeid]['p2'][$childreqid]['descr'];
					$p2uid = $cmap[$childikeid]['p2'][$childreqid]['uniqid'];
				} else {
					$childreqid = array_key_first($cmap[$childikeid]['p2']);
					$p2uid = $cmap[$childikeid]['p2'][$childreqid]['uniqid'];
					if (count($cmap[$childikeid]['p2']) > 1) {
						$p2descr = gettext("Multiple");
					} else {
						$p2descr = $cmap[$childikeid]['p2'][$childreqid]['descr'];
					}
				}
?>
			<?= htmlspecialchars($p2descr) ?>
<?php				if (!empty($p2uid) && ($p2descr != gettext("Multiple"))): ?>
			<br/>
			<a class="fa fa-pencil" href="vpn_ipsec_phase2.php?uniqid=<?= htmlspecialchars($p2uid) ?>"
				title="<?= gettext("Edit Phase 2 Entry") ?>">
			</a>
<?php				endif ?>
		</td>
		<td>
<?php
				$lnetlist = array();
				if (is_array($childsa['local-ts'])) {
					foreach ($childsa['local-ts'] as $lnets) {
						$lnetlist[] = htmlspecialchars(ipsec_fixup_network($lnets));
					}
				} else {
					$lnetlist[] = htmlspecialchars(gettext("Unknown"));
				}
?>
			<?= implode('<br/>', $lnetlist) ?>
		</td>
		<td>
<?php
				if (isset($childsa['spi-in'])) {
?>
			<b><?= htmlspecialchars(gettext("Local:")) ?></b>
			<?= htmlspecialchars($childsa['spi-in']) ?>
<?php
				}
				if (isset($childsa['spi-out'])) {
?>
			<br/>
			<b><?= htmlspecialchars(gettext("Remote:")) ?></b>
			<?= htmlspecialchars($childsa['spi-out']) ?>
<?php
				}
?>
		</td>
		<td>
<?php
				$rnetlist = array();
				if (is_array($childsa['remote-ts'])) {
					foreach ($childsa['remote-ts'] as $rnets) {
						$rnetlist[] = htmlspecialchars(ipsec_fixup_network($rnets));
					}
				} else {
					$rnetlist[] = htmlspecialchars(gettext("Unknown"));
				}
?>
			<?= implode('<br/>', $rnetlist) ?>
		</td>
		<td>
			<b><?= htmlspecialchars(gettext("Rekey:")) ?></b>
			<?= htmlspecialchars($childsa['rekey-time']) ?>s
			(<?= convert_seconds_to_dhms($childsa['rekey-time']) ?>)
			<br/>

			<b><?= htmlspecialchars(gettext("Life:")) ?></b>
			<?= htmlspecialchars($childsa['life-time']) ?>s
			(<?= convert_seconds_to_dhms($childsa['life-time']) ?>)
			<br/>

			<b><?= htmlspecialchars(gettext("Install:")) ?></b>
			<?= htmlspecialchars($childsa['install-time']) ?>s
			(<?= convert_seconds_to_dhms($childsa['install-time']) ?>)
		</td>
		<td>
			<?= htmlspecialchars($childsa['encr-alg']) ?>
<?php				if (!empty($childsa['encr-keysize'])): ?>
			(<?= htmlspecialchars($childsa['encr-keysize']) ?>)
<?php				endif; ?>
			<br/>
			<?= htmlspecialchars($childsa['integ-alg']) ?>
			<br/>
<?php				if (!empty($childsa['prf-alg'])): ?>
			<?= htmlspecialchars($childsa['prf-alg']) ?>
			<br/>
<?php				endif; ?>
<?php				if (!empty($childsa['dh-group'])): ?>
			<?= htmlspecialchars($childsa['dh-group']) ?>
			<br/>
<?php				endif; ?>
<?php				if (!empty($childsa['esn'])): ?>
			<?= htmlspecialchars($childsa['esn']) ?>
			<br/>
<?php				endif;
				$ipcomp = gettext('None');
				if (!empty($childsa['cpi-in']) || !empty($childsa['cpi-out'])) {
					$ipcomp = "{$childsa['cpi-in']} {$childsa['cpi-out']}";
				}
?>
			<?= htmlspecialchars(gettext("IPComp: ")) ?> <?= htmlspecialchars($ipcomp) ?>
		</td>
		<td>
			<b><?= htmlspecialchars(gettext("Bytes-In:")) ?></b>
			<?= htmlspecialchars(number_format($childsa['bytes-in'])) ?>
			(<?= htmlspecialchars(format_bytes($childsa['bytes-in'])) ?>)
			<br/>
			<b><?= htmlspecialchars(gettext("Packets-In:")) ?></b>
			<?= htmlspecialchars(number_format($childsa['packets-in'])) ?>
			<br/>
			<b><?= htmlspecialchars(gettext("Bytes-Out:")) ?></b>
			<?= htmlspecialchars(number_format($childsa['bytes-out'])) ?>
			(<?= htmlspecialchars(format_bytes($childsa['bytes-out'])) ?>)
			<br/>
			<b><?= htmlspecialchars(gettext("Packets-Out:")) ?></b>
			<?= htmlspecialchars(number_format($childsa['packets-out'])) ?>
			<br/>
		</td>
		<td>

			<?= htmlspecialchars(ucfirst(strtolower($childsa['state']))) ?><br/>
			<?= ipsec_status_button('ajax', 'disconnect', 'child', $childsa['name'], $childsa['uniqueid'], true) ?>
		</td>
<?php
			}
?>
	</tr>
<?php
		}
			foreach ($p2disconnected as $p2conid => $p2) {
?>
	<tr>
		<td><?= htmlspecialchars($p2conid) ?></td>
		<td>
			<?= htmlspecialchars($p2['descr']) ?>
			<br/>
			<a class="fa fa-pencil" href="vpn_ipsec_phase2.php?uniqid=<?= htmlspecialchars($p2['uniqid']) ?>"
				title="<?= htmlspecialchars(gettext("Edit Phase 2 Entry")) ?>">
			</a>
		</td>
		<td>
			<?= htmlspecialchars(ipsec_idinfo_to_cidr($p2['localid'], false, $p2['mode'])) ?>
		</td>
		<td><!-- SPI n/a --></td>
		<td>
			<?= htmlspecialchars(ipsec_idinfo_to_cidr($p2['remoteid'], false, $p2['mode'])) ?>
		</td>
		<td><!-- Times n/a --></td>
		<td><!-- Algo is too much here --></td>
		<td><!-- Stats n/a --></td>
		<td>
			<?= htmlspecialchars(gettext("Disconnected")) ?><br/>
			<?= ipsec_status_button('ajax', 'connect', 'child', $p2conid, null, true) ?>
		</td>
	</tr>
<?php
			}
?>
	</tbody>
	</table>
	</td>
</tr>
<?php
	}

	$rgmap = array();
	$gateways_status = return_gateways_status(true);

	foreach ($cmap as $p1) {
		if (!array_key_exists('p1', $p1) ||
		    isset($p1['p1']['disabled'])) {
			continue;
		}
		$ph1ent = &$p1['p1'];
		$rgmap[$ph1ent['remote-gateway']] = $ph1ent['remote-gateway'];
		if ($p1connected[$ph1ent['ikeid']]) {
			continue;
		}
?>
<tr>
	<td>
		<?= htmlspecialchars(ipsec_conid($ph1ent)) ?>
	</td>
	<td>
		<?= htmlspecialchars($ph1ent['descr']) ?>
	</td>
	<td>
		<b><?= htmlspecialchars(gettext("ID:")) ?></b>
<?php
		list ($myid_type, $myid_data) = ipsec_find_id($ph1ent, "local", array(), $gateways_status);
		if (empty($myid_data)) {
			$myid_data = gettext("Unknown");
		}
?>
		<?= htmlspecialchars($myid_data) ?>
		<br/>
		<b><?= htmlspecialchars(gettext("Host:")) ?></b>
<?php
		$ph1src = ipsec_get_phase1_src($ph1ent, $gateways_status);
		if (empty($ph1src)) {
			$ph1src = gettext("Unknown");
		} else {
			$ph1src = str_replace(',', ', ', $ph1src);
		}
?>
		<?= htmlspecialchars($ph1src) ?>
	</td>
	<td>
<?php		if (!isset($ph1ent['mobile'])): ?>
		<b><?= htmlspecialchars(gettext("ID:")) ?></b>
<?php
		list ($peerid_type, $peerid_data) = ipsec_find_id($ph1ent, "peer", $rgmap, $gateways_status);
		if (empty($peerid_data)) {
			$peerid_data = gettext("Unknown");
		}
?>
		<?= htmlspecialchars($peerid_data) ?>
		<br/>
		<b><?= htmlspecialchars(gettext("Host:")) ?></b>
<?php
		$ph1dst = ipsec_get_phase1_dst($ph1ent);
		if (empty($ph1dst)) {
			$ph1dst = print(gettext("Unknown"));
		}
?>
		<?= htmlspecialchars($ph1dst) ?>
<?php		else: ?>
		<?= htmlspecialchars(gettext("Mobile Clients")) ?>
<?php		endif; ?>
	</td>
	<td></td>
	<td></td>
	<td></td>
	<td>
<?php		if (isset($ph1ent['mobile'])): ?>
		<?= htmlspecialchars(gettext("Awaiting connections")) ?>
<?php		else: ?>
		<?= htmlspecialchars(gettext("Disconnected")) ?>
		<br/>
		<?= ipsec_status_button('ajax', 'connect', 'all', ipsec_conid($ph1ent), null, true) ?>
		<br/><br/>
		<?= ipsec_status_button('ajax', 'connect', 'ike', ipsec_conid($ph1ent), null, true) ?>

<?php		endif; ?>
	</td>
</tr>
<?php
	}
	unset($p1connected, $p2connected, $p2disconnected, $rgmap);
}

$pgtitle = array(gettext("Status"), gettext("IPsec"), gettext("Overview"));
$pglinks = array("", "@self", "@self");
$shortcut_section = "ipsec";

include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Overview"), true, "status_ipsec.php");
$tab_array[] = array(gettext("Leases"), false, "status_ipsec_leases.php");
$tab_array[] = array(gettext("SADs"), false, "status_ipsec_sad.php");
$tab_array[] = array(gettext("SPDs"), false, "status_ipsec_spd.php");
display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?= htmlspecialchars(gettext("IPsec Status")); ?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-condensed table-hover sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?= htmlspecialchars(gettext("ID")) ?></th>
					<th><?= htmlspecialchars(gettext("Description") )?></th>
					<th><?= htmlspecialchars(gettext("Local")) ?></th>
					<th><?= htmlspecialchars(gettext("Remote")) ?></th>
					<th><?= htmlspecialchars(gettext("Role")) ?></th>
					<th><?= htmlspecialchars(gettext("Timers")) ?></th>
					<th><?= htmlspecialchars(gettext("Algo")) ?></th>
					<th><?= htmlspecialchars(gettext("Status")) ?></th>
				</tr>
			</thead>
			<tbody id="ipsec-body">
				<tr>
					<td colspan="10">
						<?= print_info_box('<i class="fa fa-gear fa-spin"></i>&nbsp;&nbsp;' .
						   gettext("Collecting IPsec status information."), "warning", "") ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>

<?php
unset($status);

if (ipsec_enabled()) {
	print('<div class="infoblock">');
} else {
	print('<div class="infoblock blockopen">');
}

print_info_box(sprintf(gettext('IPsec is not enabled. %1$sConfigure IPsec%2$s.'), '<a href="vpn_ipsec.php">', '</a>'), 'info', false);
?>
</div>

<script type="text/javascript">
//<![CDATA[

events.push(function() {
	ajax_lock = false;		// Mutex so we don't make a call until the previous call is finished
	sa_open = new Array();	// Array in which to keep the child SA show/hide state
	tryCount = 3;
	// Fetch the tbody contents from the server
	function update_table() {
		if (ajax_lock) {
			return;
		}

		ajax_lock = true;

		ajaxRequest = $.ajax(
			{
				url: "/status_ipsec.php",
				type: "post",
				data: {
					ajax: 	"ajax"
				},
				error: function(xhr, textStatus, errorThrown){
					//alert("error.... retrying");
					if (tryCount > 0){
						tryCount --;
						ajax_lock = false;
						update_table();
					}
					return;
				}
			}
		);

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {
			if(textStatus === "success"){
				tryCount =3;
			}
			if (!response) {
				response = '<tr><td colspan="10"><?=print_info_box(addslashes(gettext("No IPsec status information available.")), "warning", "")?></td></tr>';
			}

			$('#ipsec-body').html(response);
			ajax_lock = false;

			// Update "Show child SA" handlers
			$('[id^=btnchildsa-]').click(function () {
				show_childsa($(this).prop("id").replace( 'btnchildsa-', ''));
			});

			// Check the sa_open array for child SAs that have been opened
			$('[id^=childsa-]').each(function(idx) {
				sa_idx = $(this).prop("id").replace( 'childsa-', '');

				if (sa_open[sa_idx]) {
					show_childsa(sa_idx);
				}
			});

			// re-attached the GET to POST handler
			interceptGET();

			// and do it again
			setTimeout(update_table, 5000);
		});
	}

	function show_childsa(said) {
		sa_open[said] = true;
		$('#childsa-' + said).show();
		$('#btnchildsa-' + said).hide();
	}

	// Populate the tbody on page load
	update_table();
});
//]]>
</script>

<?php
include("foot.inc"); ?>
