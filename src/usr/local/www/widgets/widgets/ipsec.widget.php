<?php
/*
 * ipsec.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
require_once("service-utils.inc");
require_once("ipsec.inc");

// Compose the table contents and pass it back to the ajax caller
if ($_REQUEST && $_REQUEST['ajax']) {

	init_config_arr(array('ipsec', 'phase1'));
	init_config_arr(array('ipsec', 'phase2'));

	if (ipsec_enabled() && get_service_status(array('name' => 'ipsec'))) {
		$cmap = ipsec_status();
		$mobile = ipsec_dump_mobile();
	} else {
		$cmap = array();
	}

	$mobileactive = 0;
	$mobileinactive = 0;
	if (is_array($mobile['pool'])) {
		foreach ($mobile['pool'] as $pool) {
			$mobileactive += $pool['online'];
			$mobileinactive += $pool['offline'];
		}
	}

	// Generate JSON formatted data for the widget to update from
	$data = new stdClass();
	$data->overview = "<tr>";
	$data->overview .= "<td>" . count($cmap['connected']['p1']) . " / ";
	$data->overview .= count($cmap['connected']['p1']) + count($cmap['disconnected']['p1']) . "</td>";
	$data->overview .= "<td>" . count($cmap['connected']['p2']) . " / ";
	$data->overview .= count($cmap['connected']['p2']) + count($cmap['disconnected']['p2']) . "</td>";
	$data->overview .= "<td>" . htmlspecialchars($mobileactive) . " / ";
	$data->overview .= htmlspecialchars($mobileactive + $mobileinactive) . "</td>";
	$data->overview .= "</tr>";

	$gateways_status = return_gateways_status(true);
	$data->tunnel = "";
	foreach ($cmap as $k => $tunnel) {
		if (in_array($k, array('connected', 'disconnected')) ||
		    (!array_key_exists('p1', $tunnel) ||
		    isset($tunnel['p1']['disabled'])) ||
		    isset($tunnel['p1']['mobile'])) {
			continue;
		}

		// convert_friendly_interface_to_friendly_descr($ph1ent['interface'])
		$p1src = ipsec_get_phase1_src($tunnel['p1'], $gateways_status);
		if (empty($p1src)) {
			$p1src = gettext("Unknown");
		} else {
			$p1src = str_replace(',', ', ', $p1src);
		}
		$p1dst = ipsec_get_phase1_dst($tunnel['p1']);
		$data->tunnel .= "<tr>";
		$data->tunnel .= "<td colspan=2>" . htmlspecialchars($p1src) . "</td>";
		$data->tunnel .= "<td colspan=2>" . htmlspecialchars($p1dst) . "</td>";
		$data->tunnel .= "<td colspan=2>" . htmlspecialchars($tunnel['p1']['descr']) . "</td>";
		$p1conid = ipsec_conid($tunnel['p1'], null);
		if (isset($tunnel['p1']['connected'])) {
			$data->tunnel .= '<td><i class="fa fa-arrow-up text-success"></i> ';
			$data->tunnel .= ipsec_status_button('ajax', 'disconnect', 'ike', $p1conid, null, false);
			$data->tunnel .= '</td>';
		} else {
			$data->tunnel .= '<td><i class="fa fa-arrow-down text-danger"></i> ';
			$data->tunnel .= ipsec_status_button('ajax', 'connect', 'all', $p1conid, null, false);
			$data->tunnel .= '</td>';
		}
		$data->tunnel .= "</tr>";

		foreach ($tunnel['p2'] as $p2) {
			if (isset($p2['mobile'])) {
				continue;
			}
			$p2src = ipsec_idinfo_to_text($p2['localid']);
			$p2dst = ipsec_idinfo_to_text($p2['remoteid']);

			if ($tunnel['p1']['iketype'] == 'ikev2' && !isset($tunnel['p1']['splitconn'])) {
				$p2conid = ipsec_conid($tunnel['p1']);
			} else {
				$p2conid = ipsec_conid($tunnel['p1'], $p2);
			}

			$data->tunnel .= "<tr>";
			$data->tunnel .= "<td>&nbsp;</td>";
			$data->tunnel .= "<td>" . htmlspecialchars($p2src) . "</td>";
			$data->tunnel .= "<td>&nbsp;</td>";
			$data->tunnel .= "<td>" . htmlspecialchars($p2dst) . "</td>";
			$data->tunnel .= "<td>&nbsp;</td>";
			$data->tunnel .= "<td>" . htmlspecialchars($p2['descr']) . "</td>";
			if (isset($p2['connected'])) {
				$data->tunnel .= '<td><i class="fa fa-arrow-up text-success"></i> ';
				$data->tunnel .= ipsec_status_button('ajax', 'disconnect', 'child', $p2conid, null, false);
				$data->tunnel .= '</td>';
			} else {
				$data->tunnel .= '<td><i class="fa fa-arrow-down text-danger"></i> ';
				$data->tunnel .= ipsec_status_button('ajax', 'connect', 'child', $p2conid, null, false);
				$data->tunnel .= '</td>';
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

if (count($config['ipsec']['phase2'])): ?>
<div id="<?=htmlspecialchars($widgetkey_nodash)?>-Overview" style="display:block;"  class="table-responsive">
	<table class="table table-striped table-hover">
		<thead>
		<tr>
			<th><?= htmlspecialchars(gettext("P1 Active/Total")) ?></th>
			<th><?= htmlspecialchars(gettext("P2 Active/Total")) ?></th>
			<th><?= htmlspecialchars(gettext("Mobile Active/Total")) ?></th>
		</tr>
		</thead>
		<tbody>
			<tr><td colspan="5"><?= htmlspecialchars(gettext("Retrieving overview data")) ?> <i class="fa fa-cog fa-spin"></i></td></tr>
		</tbody>
	</table>
</div>
<div class="table-responsive" id="<?=htmlspecialchars($widgetkey_nodash)?>-tunnel" style="display:none;">
	<table class="table table-striped table-hover">
	<thead>
	<tr>
		<th colspan="2"><?= htmlspecialchars(gettext("Source")) ?></th>
		<th colspan="2"><?= htmlspecialchars(gettext("Destination")) ?></th>
		<th colspan="2"><?= htmlspecialchars(gettext("Description")) ?></th>
		<th colspan="2"><?= htmlspecialchars(gettext("Status")) ?></th>
	</tr>
	</thead>
	<tbody>
		<tr><td colspan="4"><?= htmlspecialchars(gettext("Retrieving tunnel data"))?> <i class="fa fa-cog fa-spin"></i></td></tr>
	</tbody>
	</table>
</div>

	<div id="<?=htmlspecialchars($widgetkey_nodash)?>-mobile" style="display:none;" class="table-responsive">
		<table class="table table-striped table-hover">
<?php if (is_array($mobile['pool'])): ?>
		<thead>
		<tr>
			<th><?= htmlspecialchars(gettext("User")) ?></th>
			<th><?= htmlspecialchars(gettext("IP")) ?></th>
			<th><?= htmlspecialchars(gettext("Status")) ?></th>
		</tr>
		</thead>
		<tbody>
			<tr><td colspan="3"><?= htmlspecialchars(gettext("Retrieving mobile data")) ?> <i class="fa fa-cog fa-spin"></i></td></tr>
		</tbody>
<?php else:?>
		<thead>
			<tr>
				<th colspan="3" class="text-danger"><?=htmlspecialchars(gettext("No mobile tunnels have been configured")) ?></th>
			</tr>
		</thead>
<?php endif;?>
		</table>
	</div>

<?php else: ?>
	<div>
		<h5 style="padding-left:10px;"><?= htmlspecialchars(gettext("There are no configured IPsec Tunnels")) ?></h5>
		<p  style="padding-left:10px;"><?= htmlspecialchars(gettext('IPsec can be configured <a href="vpn_ipsec.php">here</a>.')) ?></p>
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
