<?php
/*
 * ipsec.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2004-2005 T. Lechat <dev@lechat.org> (BSD 2 clause)
 * Copyright (c) 2007 Jonathan Watt <jwatt@jwatt.org> (BSD 2 clause)
 * Copyright (c) 2007 Scott Dale (BSD 2 clause)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
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

require_once("auth_check.inc");
require_once("functions.inc");
require_once("ipsec.inc");

// Compose the table contents and pass it back to the ajax caller
if ($_REQUEST && $_REQUEST['ajax']) {

	if (isset($config['ipsec']['phase1']) && is_array($config['ipsec']['phase1'])) {
		$spd = ipsec_dump_spd();
		$sad = ipsec_dump_sad();
		$mobile = ipsec_dump_mobile();
		$ipsec_status = ipsec_list_sa();

		$activecounter = 0;
		$inactivecounter = 0;

		$ipsec_detail_array = array();
		$ikenum = array();

		if (isset($config['ipsec']['phase2']) && is_array($config['ipsec']['phase2'])) {
			foreach ($config['ipsec']['phase2'] as $ph2ent) {
				if (!ipsec_lookup_phase1($ph2ent,$ph1ent)) {
					continue;
				}

				if ($ph2ent['remoteid']['type'] == "mobile" || isset($ph1ent['mobile'])) {
					continue;
				}

				if (isset($ph1ent['disabled']) || isset($ph2ent['disabled'])) {
					continue;
				}

				if (empty($ph1ent['iketype']) || $ph1ent['iketype'] == 'ikev1' || isset($ph1ent['splitconn'])) {
					if (!isset($ikenum[$ph1ent['ikeid']])) {
						$ikenum[$ph1ent['ikeid']] = 0;
					} else {
						$ikenum[$ph1ent['ikeid']]++;
					}

					$ikeid = "con{$ph1ent['ikeid']}00" . $ikenum[$ph1ent['ikeid']];
				} else {
					if (isset($ikenum[$ph1ent['ikeid']])) {
						continue;
					}

					$ikeid = "con{$ph1ent['ikeid']}000";
					$ikenum[$ph1ent['ikeid']] = true;
				}

				$found = false;
				if(is_array($ipsec_status) && !empty($ipsec_status)){
					foreach ($ipsec_status as $id => $ikesa) {
						if (isset($ikesa['child-sas'])) {
							foreach ($ikesa['child-sas'] as $childid => $childsa) {
								list($childcid, $childsid) = explode('-', $childid, 2);
								if ($ikeid == $childcid) {
									$found = true;
									break;
								}
							}
						} else if ($ikeid == $ikesa['con-id']) {
							$found = true;
						}

						if ($found === true) {
							if ($ikesa['state'] == 'ESTABLISHED') {
								/* tunnel is up */
								$iconfn = "true";
								$activecounter++;
							} else {
								/* tunnel is down */
								$iconfn = "false";
								$inactivecounter++;
							}
							break;
						}
					}
				}

				if ($found === false) {
					/* tunnel is down */
					$iconfn = "false";
					$inactivecounter++;
				}

				$ipsec_detail_array[] = array('src' => convert_friendly_interface_to_friendly_descr($ph1ent['interface']),
						'dest' => $ph1ent['remote-gateway'],
						'remote-subnet' => ipsec_idinfo_to_text($ph2ent['remoteid']),
						'descr' => $ph2ent['descr'],
						'status' => $iconfn);
			}
		}
		unset($ikenum);
	}

	// Generate JSON formatted data for the widget to update from
	$data = new stdClass();
	$data->overview = "<tr>";
	$data->overview .= "<td>" . $activecounter . "</td>";
	$data->overview .= "<td>" . $inactivecounter . "</td>";
	$mobileusage = 0;
	if (is_array($mobile['pool'])) {
		foreach ($mobile['pool'] as $pool) {
			$mobileusage += $pool['online'] + $pool['offline'];
		}
	}
	$data->overview .= "<td>" . htmlspecialchars($mobileusage) . "</td>";
	$data->overview .= "</tr>";

	$data->tunnel = "";
	if(is_array($ipsec_detail_array) && !empty($ipsec_detail_array)){
		foreach ($ipsec_detail_array as $ipsec) {
			$data->tunnel .= "<tr>";
			$data->tunnel .= "<td>" . htmlspecialchars($ipsec['src']) . "</td>";
			$data->tunnel .= "<td>" . $ipsec['remote-subnet'] . "<br />(" . htmlspecialchars($ipsec['dest']) . ")</td>";
			$data->tunnel .= "<td>" . htmlspecialchars($ipsec['descr']) . "</td>";
			if ($ipsec['status'] == "true") {
				$data->tunnel .= '<td><i class="fa fa-arrow-up text-success"></i></td>';
			} else {
				$data->tunnel .= '<td><i class="fa fa-arrow-down text-danger"></i></td>';
			}
			$data->tunnel .= "</tr>";
		}
	}
	
	$data->mobile = "";
	if (is_array($mobile['pool'])) {
		$mucount = 0;
		foreach ($mobile['pool'] as $pool) {
			if (!is_array($pool['lease'])) {
				continue;
			}
			if(is_array($pool['lease']) && !empty($pool['lease'])){
				foreach ($pool['lease'] as $muser) {
					$mucount++;
					if ($muser['status'] == 'online') {
						$data->mobile .= "<tr style='background-color: #c5e5bb'>";
					} else {
						$data->mobile .= "<tr>";
					}
					$data->mobile .= "<td>" . htmlspecialchars($muser['id']) . "</td>";
					$data->mobile .= "<td>" . htmlspecialchars($muser['host']) . "</td>";
					$data->mobile .= "<td>";
					if ($muser['status'] == 'online') {
						$data->mobile .= "<span class='fa fa-check'></span><span style='font-weight: bold'> ";
					} else {
						$data->mobile .= "<span>  ";
					}
					$data->mobile .= htmlspecialchars($muser['status']) . "</span></td>";
					$data->mobile .= "</tr>";
				}
			}
		}
		if ($mucount == 0) {
			$data->mobile .= '<tr><td colspan="3">' . gettext("No mobile leases") . '</tr>';
		}
	} else {
		$data->mobile .= '<tr><td colspan="3">' . gettext("No mobile pools configured") . '</tr>';
	}
	
	print(json_encode($data));
	exit;
}

$widgetkey_nodash = str_replace("-", "", $widgetkey);

if (isset($config['ipsec']['phase1'])) {
	$tab_array = array();
	$tab_array[] = array(gettext("Overview"), true, htmlspecialchars($widgetkey_nodash) . "-Overview");
	$tab_array[] = array(gettext("Tunnels"), false, htmlspecialchars($widgetkey_nodash) . "-tunnel");
	$tab_array[] = array(gettext("Mobile"), false, htmlspecialchars($widgetkey_nodash) . "-mobile");

	display_widget_tabs($tab_array);
}

$mobile = ipsec_dump_mobile();
$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;

if (isset($config['ipsec']['phase2'])): ?>
<div id="<?=htmlspecialchars($widgetkey_nodash)?>-Overview" style="display:block;"  class="table-responsive">
	<table class="table table-striped table-hover">
		<thead>
		<tr>
			<th><?=gettext("Active Tunnels")?></th>
			<th><?=gettext("Inactive Tunnels")?></th>
			<th><?=gettext("Mobile Users")?></th>
		</tr>
		</thead>
		<tbody>
			<tr><td colspan="3"><?=gettext("Retrieving overview data ")?><i class="fa fa-cog fa-spin"></i></td></tr>
		</tbody>
	</table>
</div>
<div class="table-responsive" id="<?=htmlspecialchars($widgetkey_nodash)?>-tunnel" style="display:none;">
	<table class="table table-striped table-hover">
	<thead>
	<tr>
		<th><?=gettext("Source")?></th>
		<th><?=gettext("Destination")?></th>
		<th><?=gettext("Description")?></th>
		<th><?=gettext("Status")?></th>
	</tr>
	</thead>
	<tbody>
		<tr><td colspan="4"><?=gettext("Retrieving tunnel data ")?><i class="fa fa-cog fa-spin"></i></td></tr>
	</tbody>
	</table>
</div>

	<div id="<?=htmlspecialchars($widgetkey_nodash)?>-mobile" style="display:none;" class="table-responsive">
		<table class="table table-striped table-hover">
<?php if (is_array($mobile['pool'])): ?>
		<thead>
		<tr>
			<th><?=gettext("User")?></th>
			<th><?=gettext("IP")?></th>
			<th><?=gettext("Status")?></th>
		</tr>
		</thead>
		<tbody>
			<tr><td colspan="3"><?=gettext("Retrieving mobile data ")?><i class="fa fa-cog fa-spin"></i></td></tr>
		</tbody>
<?php else:?>
		<thead>
			<tr>
				<th colspan="3" class="text-danger"><?=gettext("No mobile tunnels have been configured")?></th>
			</tr>
		</thead>
<?php endif;?>
		</table>
	</div>

<?php else: ?>
	<div>
		<h5 style="padding-left:10px;"><?=gettext("There are no configured IPsec Tunnels")?></h5>
		<p  style="padding-left:10px;"><?=gettext('IPsec can be configured <a href="vpn_ipsec.php">here</a>.')?></p>
	</div>
<?php endif;

?>
<script type="text/javascript">
//<![CDATA[

curtab = "Overview";

function changeTabDIV(selectedDiv) {
	var dashpos = selectedDiv.indexOf("-");
	var tabclass = selectedDiv.substring(0, dashpos);
	curtab = selectedDiv.substring(dashpos+1, 20);
	d = document;

	//get deactive tabs first
	tabclass = tabclass + "-class-tabdeactive";

	var tabs = document.getElementsByClassName(tabclass);
	var incTabSelected = selectedDiv + "-deactive";

	for (i = 0; i < tabs.length; i++) {
		var tab = tabs[i].id;
		dashpos = tab.lastIndexOf("-");
		var tab2 = tab.substring(0, dashpos) + "-deactive";

		if (tab2 == incTabSelected) {
			tablink = d.getElementById(tab2);
			tablink.style.display = "none";
			tab2 = tab.substring(0, dashpos) + "-active";
			tablink = d.getElementById(tab2);
			tablink.style.display = "table-cell";

			//now show main div associated with link clicked
			tabmain = d.getElementById(selectedDiv);
			tabmain.style.display = "block";
		} else {
			tab2 = tab.substring(0, dashpos) + "-deactive";
			tablink = d.getElementById(tab2);
			tablink.style.display = "table-cell";
			tab2 = tab.substring(0, dashpos) + "-active";
			tablink = d.getElementById(tab2);
			tablink.style.display = "none";

			//hide sections we don't want to see
			tab2 = tab.substring(0, dashpos);
			tabmain = d.getElementById(tab2);
			tabmain.style.display = "none";
		}
	}
}

events.push(function(){
	// --------------------- Centralized widget refresh system ------------------------------

	// Callback function called by refresh system when data is retrieved
	function ipsec_callback(s) {
		try{
			var obj = JSON.parse(s);

			$('tbody', '#<?= htmlspecialchars($widgetkey_nodash) ?>-Overview').html(obj.overview);
			$('tbody', '#<?= htmlspecialchars($widgetkey_nodash) ?>-tunnel').html(obj.tunnel);
			$('tbody', '#<?= htmlspecialchars($widgetkey_nodash) ?>-mobile').html(obj.mobile);
		}catch(e){

		}
	}

	// POST data to send via AJAX
	var postdata = {
		ajax: "ajax"
	 };

	// Create an object defining the widget refresh AJAX call
	var ipsecObject = new Object();
	ipsecObject.name = "IPsec";
	ipsecObject.url = "/widgets/widgets/ipsec.widget.php";
	ipsecObject.callback = ipsec_callback;
	ipsecObject.parms = postdata;
	ipsecObject.freq = 1;

	// Register the AJAX object
	register_ajax(ipsecObject);

	// ---------------------------------------------------------------------------------------------------
});
//]]>
</script>
