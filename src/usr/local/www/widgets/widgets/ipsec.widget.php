<?php
/*
 * gmirror_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("ipsec.inc");

// Compose the table contents and pass it back to the ajax caller
if ($_REQUEST && $_REQUEST['ajax']) {

	if (isset($config['ipsec']['phase1'])) {
		$spd = ipsec_dump_spd();
		$sad = ipsec_dump_sad();
		$mobile = ipsec_dump_mobile();
		$ipsec_status = ipsec_list_sa();

		$activecounter = 0;
		$inactivecounter = 0;

		$ipsec_detail_array = array();
		$ikenum = array();
		if (isset($config['ipsec']['phase2'])) {
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

				if (empty($ph1ent['iketype']) || $ph1ent['iketype'] == 'ikev1') {
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

					$ikeid = "con{$ph1ent['ikeid']}";
					$ikenum[$ph1ent['ikeid']] = true;
				}

				$found = false;
				foreach ($ipsec_status as $id => $ikesa) {
					if (isset($ikesa['child-sas'])) {
						foreach ($ikesa['child-sas'] as $childid => $childsa) {
							if ($ikeid == $childid) {
								$found = true;
								break;
							}
						}
					} else if ($ikeid == $id) {
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

	// Only generate the data for the tab that is currently being viewed
	switch ($_REQUEST['tab']) {
		case "Overview" :
			print("	<tr>\n");
			print(		"<td>" . $activecounter . "</td>\n");
			print(		"<td>" . $inactivecounter . "</td>\n");
			print(		"<td>" . (is_array($mobile['pool']) ? htmlspecialchars($mobile['pool'][0]['usage']) : '0') . "</td>\n");
			print(	"</tr>\n");
		break;

		case "tunnel" :
			foreach ($ipsec_detail_array as $ipsec) {
				print("	<tr>\n");
				print(		"<td>" . htmlspecialchars($ipsec['src']) . "</td>\n");
				print(		"<td>" . $ipsec['remote-subnet'] . "<br />(" . htmlspecialchars($ipsec['dest']) . ")</td>\n");
				print(		"<td>" . htmlspecialchars($ipsec['descr']) . "</td>\n");

				if ($ipsec['status'] == "true") {
					print('<td><i class="fa fa-arrow-up text-success"></i></td>' . "\n");
				} else {
					print('<td><i class="fa fa-arrow-down text-danger"></i></td>' . "\n");
				}

				print(	"</tr>\n");
			}
		break;

		case "mobile" :
			if (!is_array($mobile['pool'])) {
				break;
			}
			foreach ($mobile['pool'] as $pool) {
				if (!is_array($pool['lease'])) {
					continue;
				}

				foreach ($pool['lease'] as $muser) {
					print("	<tr>\n");
					print(		"<td>" . htmlspecialchars($muser['id']) . "</td>\n");
					print(		"<td>" . htmlspecialchars($muser['host']) . "</td>\n");
					print(		"<td>" . htmlspecialchars($muser['status']) . "</td>\n");
					print("	</tr>\n");
				}
			}
		break;
	}

	exit;
}

if (isset($config['ipsec']['phase1'])) {
	$tab_array = array();
	$tab_array[] = array(gettext("Overview"), true, "ipsec-Overview");
	$tab_array[] = array(gettext("Tunnels"), false, "ipsec-tunnel");
	$tab_array[] = array(gettext("Mobile"), false, "ipsec-mobile");

	display_widget_tabs($tab_array);
}

$mobile = ipsec_dump_mobile();

if (isset($config['ipsec']['phase2'])): ?>
<div id="ipsec-Overview" style="display:block;"  class="table-responsive">
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
<div class="table-responsive" id="ipsec-tunnel" style="display:none;">
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

	<?php if (is_array($mobile['pool'])): ?>
<div id="ipsec-mobile" style="display:none;" class="table-responsive">
		<table class="table table-striped table-hover">
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
		</table>
	</div>
	<?php endif;?>
<?php else: ?>
	<div>
		<h5 style="padding-left:10px;"><?=gettext("There are no configured IPsec Tunnels")?></h5>
		<p  style="padding-left:10px;"><?=gettext('IPsec can be configured <a href="vpn_ipsec.php">here</a>.')?></p>
	</div>
<?php endif;

// This function was in index.php It seems that the ipsec widget is the only place it is used
// so now it lives here. It wouldn't hurt to update this function and the tab display, but it
// looks OK for now. The display_widget_tabs() function in guiconfig.inc would need to be updated to match
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

function get_ipsec_stats() {
	var ajaxRequest;

	ajaxRequest = $.ajax({
			url: "/widgets/widgets/ipsec.widget.php",
			type: "post",
			data: {
					ajax: "ajax",
					tab:  curtab
				  }
		});

	// Deal with the results of the above ajax call
	ajaxRequest.done(function (response, textStatus, jqXHR) {

		$('tbody', '#ipsec-' + curtab).html(response);

		// and do it again
		setTimeout(get_ipsec_stats, 6000);
	});
}

events.push(function(){
	get_ipsec_stats();
});
//]]>
</script>
