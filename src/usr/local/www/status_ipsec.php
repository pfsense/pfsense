<?php
/*
 * status_ipsec.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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

global $g;

init_config_arr(array('ipsec', 'phase1'));

// If this is just an AJAX call to update the table body, just generate the body and quit
if ($_REQUEST['ajax']) {
	print_ipsec_body();
	exit;
}

if (($_POST['act'] == 'connect') || ($_POST['act'] == 'childconnect')) {
	if (ctype_digit($_POST['ikeid'])) {
		$ph1ent = ipsec_get_phase1($_POST['ikeid']);
		if (!empty($ph1ent)) {
			if (empty($ph1ent['iketype']) || ($ph1ent['iketype'] == 'ikev1') || isset($ph1ent['splitconn'])) {
				$ph2entries = ipsec_get_number_of_phase2($_POST['ikeid']);
				for ($i = 0; $i < $ph2entries; $i++) {
					$connid = escapeshellarg("con{$_POST['ikeid']}00{$i}");
					if ($_POST['act'] != 'childconnect') {
						mwexec_bg("/usr/local/sbin/swanctl --terminate --child {$connid}");
					}
					mwexec_bg("/usr/local/sbin/swanctl --initiate --child {$connid}");
				}
			} else {
				if ($_POST['act'] != 'childconnect') {
					mwexec_bg("/usr/local/sbin/swanctl --terminate --ike con" . escapeshellarg($_POST['ikeid'] . '000'));
				}
				mwexec_bg("/usr/local/sbin/swanctl --initiate --child con" . escapeshellarg($_POST['ikeid'] . '000'));
			}
		}
	}
} else if ($_POST['act'] == 'ikedisconnect') {

	if (!empty($_POST['ikesaid']) && ctype_digit($_POST['ikesaid'])) {
		mwexec_bg("/usr/local/sbin/swanctl --terminate --ike " . escapeshellarg($_POST['ikeid']) . " --ike-id " .escapeshellarg($_POST['ikesaid']));
	} else {
		mwexec_bg("/usr/local/sbin/swanctl --terminate --ike " . escapeshellarg($_POST['ikeid']));
	}
} else if ($_POST['act'] == 'childdisconnect') {
	//pull out number from id
		if (!empty($_POST['ikesaid']) && ctype_digit($_POST['ikesaid'])) {
			mwexec_bg("/usr/local/sbin/swanctl --terminate --child " . escapeshellarg($_POST['ikeid']) . " --child-id " . escapeshellarg($_POST['ikesaid']));
		}
}

// Table body is composed here so that it can be more easily updated via AJAX
function print_ipsec_body() {
	global $config;
	$a_phase1 = &$config['ipsec']['phase1'];
	$status = ipsec_list_sa();
	$ipsecconnected = array();
	if (is_array($status)) {
		foreach ($status as $ikeid => $ikesa) {
			//check which array format
			if(isset($ikesa['con-id'])){
				$con_id = substr($ikesa['con-id'],3);
			}else{
				$con_id = filter_var($ikeid, FILTER_SANITIZE_NUMBER_INT);
			}
			if ($ikesa['version'] == 1) {
				$ph1idx = substr($con_id, 0, strrpos(substr($con_id, 0, -1), '00'));
				$ipsecconnected[$ph1idx] = $ph1idx;
			} else {
				if (!ipsec_ikeid_used($con_id)) {
					// probably a v2 with split connection then
					$ph1idx = substr($con_id, 0, strrpos(substr($con_id, 0, -1), '00'));
					$ipsecconnected[$ph1idx] = $ph1idx;
				} else {
					$ipsecconnected[$con_id] = $ph1idx = $con_id;
				}
			}

			print("<tr>\n");

			print("<td>\n");
			print(htmlspecialchars($ikesa['con-id'])) . ":\n";
			print('#' . htmlspecialchars($ikesa['uniqueid']));
			print("</td>\n");

			print("<td>\n");
			if (is_array($a_phase1) && htmlspecialchars(ipsec_get_descr($ph1idx)) == "") {
				foreach ($a_phase1 as $ph1) {
					if($con_id == $ph1['ikeid'] && isset($ph1['mobile']) ){
						print(htmlspecialchars($ph1['descr']));
						break;
					}
				}
			}
			print(htmlspecialchars(ipsec_get_descr($ph1idx)));
			print("</td>\n");

			print("<td>\n");

			if (!empty($ikesa['local-id'])) {
				if ($ikesa['local-id'] == '%any') {
					print(gettext('Any identifier'));
				} else {
					print(htmlspecialchars($ikesa['local-id']));
				}
			} else {
				print(gettext("Unknown"));
			}

			print("</td>\n");
			print("<td>\n");

			if (!empty($ikesa['local-host'])) {
				print(htmlspecialchars($ikesa['local-host']));
			} else {
				print(gettext("Unknown"));
			}

			/*
			 * XXX: local-nat-t was defined by pfSense
			 * When strongswan team accepted the change, they changed it to
			 * nat-local. Keep both for a while and remove local-nat-t in
			 * the future
			 */
			if (isset($ikesa['local-nat-t']) || isset($ikesa['nat-local'])) {
				print(" NAT-T");
			}

			print("</td>\n");
			print("<td>\n");

			$identity = "";
			if (!empty($ikesa['remote-id'])) {
				if ($ikesa['remote-id'] == '%any') {
					$identity = htmlspecialchars(gettext('Any identifier'));
				} else {
					$identity = htmlspecialchars($ikesa['remote-id']);
				}
			}

			if (!empty($ikesa['remote-xauth-id'])) {
				echo htmlspecialchars($ikesa['remote-xauth-id']);
				echo "<br/>{$identity}";
			} elseif (!empty($ikesa['remote-eap-id'])) {
				echo htmlspecialchars($ikesa['remote-eap-id']);
				echo "<br/>{$identity}";
			} else {
				if (empty($identity)) {
					print(gettext("Unknown"));
				} else {
					print($identity);
				}
			}

			print("</td>\n");
			print("<td>\n");

			if (!empty($ikesa['remote-host'])) {
				print(htmlspecialchars($ikesa['remote-host']));
			} else {
				print(gettext("Unknown"));
			}
			/*
			 * XXX: remote-nat-t was defined by pfSense
			 * When strongswan team accepted the change, they changed it to
			 * nat-remote. Keep both for a while and remove remote-nat-t in
			 * the future
			 */
			if (isset($ikesa['remote-nat-t']) || isset($ikesa['nat-remote'])) {
				print(" NAT-T");
			}

			print("</td>\n");
			print("<td>\n");
			print("IKEv" . htmlspecialchars($ikesa['version']));
			print("<br/>\n");

			if ($ikesa['initiator'] == 'yes') {
				print("initiator");
			} else {
				print("responder");
			}

			print("</td>\n");
			print("<td>\n");
			print(htmlspecialchars($ikesa['reauth-time']) . gettext(" seconds (") . convert_seconds_to_dhms($ikesa['reauth-time']) . ")");
			print("</td>\n");
			print("<td>\n");
			print(htmlspecialchars($ikesa['encr-alg']));
			print("<br/>");
			print(htmlspecialchars($ikesa['integ-alg']));
			print("<br/>");
			print(htmlspecialchars($ikesa['prf-alg']));
			print("<br/>\n");
			print(htmlspecialchars($ikesa['dh-group']));
			print("</td>\n");
			print("<td>\n");

			if ($ikesa['state'] == 'ESTABLISHED') {
				print('<span class="text-success">');
			} else {
				print('<span>');
			}

			print(ucfirst(htmlspecialchars($ikesa['state'])));

			if ($ikesa['state'] == 'ESTABLISHED') {
				print("<br/>");
				printf(gettext('%1$s seconds (%2$s) ago'), htmlspecialchars($ikesa['established']), convert_seconds_to_dhms($ikesa['established']));
			}

			print("</span><br /><br />");

			if ($ikesa['state'] != 'ESTABLISHED') {

				print('<a href="status_ipsec.php?act=connect&amp;ikeid=' . $con_id . '&amp;ikesaid=' .$ikesa['uniqueid'] . '" class="btn btn-xs btn-success" data-toggle="tooltip" title="' . gettext("Connect VPN"). '" usepost>');
				print('<i class="fa fa-sign-in icon-embed-btn"></i>');
				print(gettext("Connect VPN"));
				print("</a>\n");

			} else {

				print('<a href="status_ipsec.php?act=ikedisconnect&amp;ikeid=' . $ikesa['con-id']. '&amp;ikesaid=' .$ikesa['uniqueid'] . '"class="btn btn-xs btn-danger" data-toggle="tooltip" title="' . gettext("Disconnect VPN") . '" usepost>');
				print('<i class="fa fa-trash icon-embed-btn"></i>');
				print(gettext("Disconnect"));
				print("</a><br />\n");

			}
			if (empty($ikesa['child-sas'])) {
				print('<br/><a href="status_ipsec.php?act=childconnect&amp;ikeid=' . substr($con_id, 0, -3) . '" class="btn btn-xs btn-success" data-toggle="tooltip" title="' . gettext("Connect Children"). '" usepost>');
				print('<i class="fa fa-sign-in icon-embed-btn"></i>');
				print(gettext("Connect Children"));
				print("</a>\n");
			}

			print("</td>\n");
			print("</tr>\n");
			print("<tr>\n");
			print("<td colspan = 10>\n");

			if (is_array($ikesa['child-sas']) && (count($ikesa['child-sas']) > 0)) {
				$child_key = "";
				foreach ($ikesa['child-sas'] as $key => $val){
					$child_key = $key;
					break;
				}

				print('<div>');
				print('<a type="button" id="btnchildsa-'. $child_key .  '" class="btn btn-sm btn-info">');
				print('<i class="fa fa-plus-circle icon-embed-btn"></i>');
				print(sprintf(gettext('Show child SA entries (%d)'), count($ikesa['child-sas'])));
				print("</a>\n");
				print("	</div>\n");

				print('<table class="table table-hover table-condensed" id="childsa-'.$child_key . '" style="display:none">');
				print("<thead>\n");
				print('<tr class="bg-info">');
				print('<th>' . gettext("IPsec ID") . '</th>');
				print('<th>' . gettext("Local subnets") . '</th>');
				print('<th>' . gettext("Local SPI(s)") . '</th>');
				print('<th>' . gettext("Remote subnets") . '</th>');
				print('<th>' . gettext("Times") . '</th>');
				print('<th>' . gettext("Algo") . '</th>');
				print('<th>' . gettext("Stats") . '</th>');
				print('<th><!-- Buttons --></th>');
				print("</tr\n");
				print("</thead>\n");
				print("<tbody>\n");

				foreach ($ikesa['child-sas'] as $childid => $childsa) {
					print("<tr>");
					print("<td>\n");
					print($childsa['name'] . ":<br />");
					print("#" . $childsa['uniqueid']);
					print("</td>\n");
					print("<td>\n");

					if (is_array($childsa['local-ts'])) {
						foreach ($childsa['local-ts'] as $lnets) {
							print(htmlspecialchars(ipsec_fixup_network($lnets)) . "<br />");
						}
					} else {
						print(gettext("Unknown"));
					}

					print("</td>\n");
					print("<td>\n");

					if (isset($childsa['spi-in'])) {
						print(gettext("Local: ") . htmlspecialchars($childsa['spi-in']));
					}

					if (isset($childsa['spi-out'])) {
						print('<br/>' . gettext('Remote: ') . htmlspecialchars($childsa['spi-out']));
					}

					print("</td>\n");
					print("<td>\n");

					if (is_array($childsa['remote-ts'])) {
						foreach ($childsa['remote-ts'] as $rnets) {
							print(htmlspecialchars(ipsec_fixup_network($rnets)) . '<br />');
						}
					} else {
						print(gettext("Unknown"));
					}

					print("</td>\n");
					print("<td>\n");

					printf(gettext('Rekey: %1$s seconds (%2$s)'), htmlspecialchars($childsa['rekey-time']), convert_seconds_to_dhms($childsa['rekey-time']));
					print('<br/>');
					printf(gettext('Life: %1$s seconds (%2$s)'), htmlspecialchars($childsa['life-time']), convert_seconds_to_dhms($childsa['life-time']));
					print('<br/>');
					printf(gettext('Install: %1$s seconds (%2$s)'), htmlspecialchars($childsa['install-time']), convert_seconds_to_dhms($childsa['install-time']));


					print("</td>\n");
					print("<td>\n");

					print(htmlspecialchars($childsa['encr-alg']) . '<br/>');
					print(htmlspecialchars($childsa['integ-alg']) . '<br/>');

					if (!empty($childsa['prf-alg'])) {
						print(htmlspecialchars($childsa['prf-alg']) . '<br/>');
					}

					if (!empty($childsa['dh-group'])) {
						print(htmlspecialchars($childsa['dh-group']) . '<br/>');
					}

					if (!empty($childsa['esn'])) {
						print(htmlspecialchars($childsa['esn']) . '<br/>');
					}

					print(gettext("IPComp: "));
					if (!empty($childsa['cpi-in']) || !empty($childsa['cpi-out'])) {
						print(htmlspecialchars($childsa['cpi-in']) . " " . htmlspecialchars($childsa['cpi-out']));
					} else {
						print(gettext('none'));
					}

					print("</td>\n");
					print("<td>\n");

					print(gettext("Bytes-In: ") . htmlspecialchars(number_format($childsa['bytes-in'])) . ' (' . htmlspecialchars(format_bytes($childsa['bytes-in'])) . ')<br/>');
					print(gettext("Packets-In: ") . htmlspecialchars(number_format($childsa['packets-in'])) . '<br/>');
					print(gettext("Bytes-Out: ") . htmlspecialchars(number_format($childsa['bytes-out'])) . ' (' . htmlspecialchars(format_bytes($childsa['bytes-out'])) . ')<br/>');
					print(gettext("Packets-Out: ") . htmlspecialchars(number_format($childsa['packets-out'])) . '<br/>');

					print("</td>\n");
					print("<td>\n");
					print('<a href="status_ipsec.php?act=childdisconnect&amp;ikeid=' . $childsa['name'] . '&amp;ikesaid=' . $childsa['uniqueid'] . '" class="btn btn-xs btn-warning" data-toggle="tooltip" title="' . gettext('Disconnect Child SA') . '" usepost>');
					print('<i class="fa fa-trash icon-embed-btn"></i>');
					print(gettext("Disconnect"));
					print("</a>\n");
					print("</td>\n");
					print("</tr>\n");

				}

				print("</tbody>\n");
				print("	</table>\n");
				print("</td>\n");
				print("</tr>\n");

			}

			unset($con_id);
		}

	}

	$rgmap = array();
	if (is_array($a_phase1)) {
		foreach ($a_phase1 as $ph1ent) {
			if (isset($ph1ent['disabled'])) {
				continue;
			}

			$rgmap[$ph1ent['remote-gateway']] = $ph1ent['remote-gateway'];

			if ($ipsecconnected[$ph1ent['ikeid']]) {
				continue;
			}

			print("<tr>\n");
			print("<td></td>\n");
			print("<td>\n");
			print(htmlspecialchars($ph1ent['descr']));
			print("</td>\n");
			print("<td>\n");
			list ($myid_type, $myid_data) = ipsec_find_id($ph1ent, "local");

			if (empty($myid_data)) {
				print(gettext("Unknown"));
			} else {
				print(htmlspecialchars($myid_data));
			}

			print("</td>\n");
			print("<td>\n");
			$ph1src = ipsec_get_phase1_src($ph1ent);

			if (empty($ph1src)) {
				print(gettext("Unknown"));
			} else {
				print(htmlspecialchars(str_replace(',', ', ', $ph1src)));
			}

			print("</td>\n");
			print("<td>\n");

			list ($peerid_type, $peerid_data) = ipsec_find_id($ph1ent, "peer", $rgmap);

			if (empty($peerid_data)) {
				print(gettext("Unknown"));
			} else {
				print(htmlspecialchars($peerid_data));
			}
			print("			</td>\n");
			print("			<td>\n");
			$ph1src = ipsec_get_phase1_dst($ph1ent);

			if (empty($ph1src)) {
				print(gettext("Unknown"));
			} else {
				print(htmlspecialchars($ph1src));
			}

			print("</td>\n");
			print("<td>\n");
			print("</td>\n");
			print("<td>\n");
			print("</td>\n");
			print("<td>\n");
			print("</td>\n");

			if (isset($ph1ent['mobile'])) {

				print("<td>\n");
				print(gettext("Awaiting connections"));
				print("</td>\n");
				print("<td>\n");
				print("</td>\n");
				print("</td>\n");
			} else {

				print("<td>\n");
				print(gettext("Disconnected"));
				print("<br/>\n");
				print('<a href="status_ipsec.php?act=connect&amp;ikeid=' . $ph1ent['ikeid'] . '" class="btn btn-xs btn-success" usepost>');
				print('<i class="fa fa-sign-in icon-embed-btn"></i>');
				print(gettext("Connect VPN"));
				print("</a>\n");
				print("</td>\n");

			}
			print("</tr>\n");
		}
	}

	unset($ipsecconnected, $phase1, $rgmap);
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
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("IPsec Status");?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-condensed table-hover sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("IPsec ID")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Local ID")?></th>
					<th><?=gettext("Local IP")?></th>
					<th><?=gettext("Remote ID")?></th>
					<th><?=gettext("Remote IP")?></th>
					<th><?=gettext("Role")?></th>
					<th><?=gettext("Reauth")?></th>
					<th><?=gettext("Algo")?></th>
					<th><?=gettext("Status")?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="ipsec-body">
				<tr>
					<td colspan="10">
						<?=print_info_box(gettext("Collecting IPsec status information."), "warning", "")?>
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

print_info_box(sprintf(gettext('IPsec can be configured %1$shere%2$s.'), '<a href="vpn_ipsec.php">', '</a>'), 'info', false);
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
