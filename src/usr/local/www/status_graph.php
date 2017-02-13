<?php
/*
 * status_graph.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-status-trafficgraph
##|*NAME=Status: Traffic Graph
##|*DESCR=Allow access to the 'Status: Traffic Graph' page.
##|*MATCH=status_graph.php*
##|*MATCH=bandwidth_by_ip.php*
##|*MATCH=graph.php*
##|*MATCH=ifstats.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("ipsec.inc");

// Get configured interface list
$ifdescrs = get_configured_interface_with_descr();
if (ipsec_enabled()) {
	$ifdescrs['enc0'] = gettext("IPsec");
}

foreach (array('server', 'client') as $mode) {
	if (is_array($config['openvpn']["openvpn-{$mode}"])) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
			if (!isset($setting['disable'])) {
				$ifdescrs['ovpn' . substr($mode, 0, 1) . $setting['vpnid']] = gettext("OpenVPN") . " " . $mode . ": ".htmlspecialchars($setting['description']);
			}
		}
	}
}

if ($_REQUEST['if']) {
	$curif = $_REQUEST['if'];
	$found = false;
	foreach ($ifdescrs as $descr => $ifdescr) {
		if ($descr == $curif) {
			$found = true;
			break;
		}
	}
	if ($found === false) {
		header("Location: status_graph.php");
		exit;
	}
} else {
	if (empty($ifdescrs["wan"])) {
		/* Handle the case when WAN has been disabled. Use the first key in ifdescrs. */
		reset($ifdescrs);
		$curif = key($ifdescrs);
	} else {
		$curif = "wan";
	}
}
if ($_REQUEST['sort']) {
	$cursort = $_REQUEST['sort'];
} else {
	$cursort = "";
}
if ($_REQUEST['filter']) {
	$curfilter = $_REQUEST['filter'];
} else {
	$curfilter = "";
}
if ($_REQUEST['hostipformat']) {
	$curhostipformat = $_REQUEST['hostipformat'];
} else {
	$curhostipformat = "";
}
if ($_REQUEST['backgroundupdate']) {
	$curbackgroundupdate = $_REQUEST['backgroundupdate'];
} else {
	$curbackgroundupdate = "";
}

function iflist() {
	global $ifdescrs;

	$iflist = array();

	foreach ($ifdescrs as $ifn => $ifd) {
		$iflist[$ifn] = $ifd;
	}

	return($iflist);
}

$pgtitle = array(gettext("Status"), gettext("Traffic Graph"));

include("head.inc");

$form = new Form(false);
$form->addClass('auto-submit');

$section = new Form_Section('Graph Settings');

$group = new Form_Group('');

$group->add(new Form_Select(
	'if',
	null,
	$curif,
	iflist()
))->setHelp('Interface');

$group->add(new Form_Select(
	'sort',
	null,
	$cursort,
	array (
		'in'	=> gettext('Bandwidth In'),
		'out'	=> gettext('Bandwidth Out')
	)
))->setHelp('Sort by');

$group->add(new Form_Select(
	'filter',
	null,
	$curfilter,
	array (
		'local'	=> gettext('Local'),
		'remote'=> gettext('Remote'),
		'all'	=> gettext('All')
	)
))->setHelp('Filter');

$group->add(new Form_Select(
	'hostipformat',
	null,
	$curhostipformat,
	array (
		''			=> gettext('IP Address'),
		'hostname'	=> gettext('Host Name'),
		'descr'		=> gettext('Description'),
		'fqdn'		=> gettext('FQDN')
	)
))->setHelp('Display');

$group->add(new Form_Select(
	'backgroundupdate',
	null,
	$curbackgroundupdate,
	array (
		'false'	=> gettext('Clear graphs when not visible.'),
		'true'	=> gettext('Keep graphs updated on inactive tab. (increases cpu usage)'),
	)
))->setHelp('Background updates');

$section->add($group);

$form->add($section);
print $form;

?>

<script src="/vendor/d3/d3.min.js"></script>
<script src="/vendor/nvd3/nv.d3.js"></script>
<script src="/vendor/visibility/visibility-1.2.3.min.js"></script>

<link href="/vendor/nvd3/nv.d3.css" media="screen, projection" rel="stylesheet" type="text/css">

<script type="text/javascript">

//<![CDATA[
events.push(function() {

	var InterfaceString = "<?=$curif?>";

	//store saved settings in a fresh localstorage
	localStorage.clear();
	localStorage.setItem('interval', 1);
	localStorage.setItem('invert', "true");
	localStorage.setItem('size', 1);
	window.interfaces = InterfaceString.split("|");
	window.charts = {};
    window.myData = {};
    window.updateIds = 0;
    window.updateTimerIds = 0;
    window.latest = [];
    var refreshInterval = localStorage.getItem('interval');

    //TODO make it fall on a second value so it increments better
    var now = then = new Date(Date.now());

    var nowTime = now.getTime();

	$.each( window.interfaces, function( key, value ) {

		myData[value] = [];
		updateIds = 0;
		updateTimerIds = 0;

		var itemIn = new Object();
		var itemOut = new Object();

		itemIn.key = value + " (in)";
		if(localStorage.getItem('invert') === "true") { itemIn.area = true; }
		itemIn.first = true;
		itemIn.values = [{x: nowTime, y: 0}];
		myData[value].push(itemIn);

		itemOut.key = value + " (out)";
		if(localStorage.getItem('invert') === "true") { itemOut.area = true; }
		itemOut.first = true;
		itemOut.values = [{x: nowTime, y: 0}];
		myData[value].push(itemOut);

	});

    var backgroundupdate = $('#backgroundupdate').val() === "true";
	draw_graph(refreshInterval, then, backgroundupdate);

	//re-draw graph when the page goes from inactive (in it's window) to active
	Visibility.change(function (e, state) {
		if($('#backgroundupdate').val() === "true"){
			return;
		}
		if(state === "visible") {

			now = then = new Date(Date.now());

			var nowTime = now.getTime();

			$.each( window.interfaces, function( key, value ) {

				Visibility.stop(updateIds);
				clearInterval(updateTimerIds);

				myData[value] = [];

				var itemIn = new Object();
				var itemOut = new Object();

				itemIn.key = value + " (in)";
				if(localStorage.getItem('invert') === "true") { itemIn.area = true; }
				itemIn.first = true;
				itemIn.values = [{x: nowTime, y: 0}];
				myData[value].push(itemIn);

				itemOut.key = value + " (out)";
				if(localStorage.getItem('invert') === "true") { itemOut.area = true; }
				itemOut.first = true;
				itemOut.values = [{x: nowTime, y: 0}];
				myData[value].push(itemOut);

			});

			draw_graph(refreshInterval, then, false);

		}
	});

});
//]]>
</script>

<script src="/js/traffic-graphs.js"></script>

<script type="text/javascript">
//<![CDATA[

var graph_interfacenames = <?php
	foreach ($ifdescrs as $ifname => $ifdescr) {
		$iflist[$ifname] = $ifdescr;
	}
	echo json_encode($iflist);
?>;
function updateBandwidth() {
	$.ajax(
		'/bandwidth_by_ip.php',
		{
			type: 'get',
			data: $(document.forms[0]).serialize(),
			success: function (data) {
				var hosts_split = data.split("|");

				$('#top10-hosts').empty();

				//parse top ten bandwidth abuser hosts
				for (var y=0; y<10; y++) {
					if ((y < hosts_split.length) && (hosts_split[y] != "") && (hosts_split[y] != "no info")) {
						hostinfo = hosts_split[y].split(";");

						$('#top10-hosts').append('<tr>'+
							'<td>'+ hostinfo[0] +'</td>'+
							'<td>'+ hostinfo[1] +' <?=gettext("Bits/sec");?></td>'+
							'<td>'+ hostinfo[2] +' <?=gettext("Bits/sec");?></td>'+
						'</tr>');
					}
				}
			},
	});
}

events.push(function() {
	$('form.auto-submit').on('change', function() {
		$(this).submit();
	});

	setInterval('updateBandwidth()', 3000);

	updateBandwidth();
});
//]]>
</script>
<?php

/* link the ipsec interface magically */
if (ipsec_enabled()) {
	$ifdescrs['enc0'] = gettext("IPsec");
}

?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("Traffic Graph");?></h2>
	</div>
	<div class="panel-body">
		<div class="col-sm-6">
			<div id="traffic-chart-<?=$curif?>" class="d3-chart traffic-widget-chart">
				<svg></svg>
			</div>
		</div>
		<div class="col-sm-6">
			<table class="table table-striped table-condensed">
				<thead>
					<tr>
						<th><?=(($curhostipformat == "") ? gettext("Host IP") : gettext("Host Name or IP")); ?></th>
						<th><?=gettext("Bandwidth In"); ?></th>
						<th><?=gettext("Bandwidth Out"); ?></th>
					</tr>
				</thead>
				<tbody id="top10-hosts">
					<!-- to be added by javascript -->
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php include("foot.inc");
