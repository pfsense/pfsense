<?php
/*
	ipsec.widget.php
*/
/* ====================================================================
 * Copyright (c) 2004-2015 Electric Sheep Fencing, LLC. All rights reserved.
 * Copyright (c) 2004-2005 T. Lechat <dev@lechat.org> (BSD 2 clause)
 * Copyright (c) 2007 Jonathan Watt <jwatt@jwatt.org> (BSD 2 clause)
 * Copyright (c) 2007 Scott Dale (BSD 2 clause)
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
					print('<td><i class="fa fa-chevron-up"></i></td>' . "\n");
				} else {
					print('<td><i class="fa fa-chevron-down"></i></td>' . "\n");
				}

				print(	"</tr>\n");
			}
		break;

		case "mobile" :
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
	$tab_array[] = array("Overview", true, "ipsec-Overview");
	$tab_array[] = array("Tunnels", false, "ipsec-tunnel");
	$tab_array[] = array("Mobile", false, "ipsec-mobile");

	display_widget_tabs($tab_array);
}

$mobile = ipsec_dump_mobile();

if (isset($config['ipsec']['phase2'])): ?>
<div id="ipsec-Overview" style="display:block;"  class="table-responsive">
	<table class="table table-striped table-hover">
		<thead>
		<tr>
			<th>Active Tunnels</th>
			<th>Inactive Tunnels</th>
			<th>Mobile Users</th>
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
		<th>Source</th>
		<th>Destination</th>
		<th>Description</th>
		<th>Status</th>
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
			<th>User</th>
			<th>IP</th>
			<th>Status</th>
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
		<h5 style="padding-left:10px;">There are no configured IPsec Tunnels</h5>
		<p  style="padding-left:10px;">You can configure your IPsec <a href="vpn_ipsec.php">here</a>.</p>
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
