<?php
/*
 * status_graph.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

if (is_array($config["traffic_graphs"])){
	$pconfig = $config["traffic_graphs"];
}
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

$ifdescrs = array_merge($ifdescrs, interface_ipsec_vti_list_all());

if (!empty($_POST)) {
	// update view if settings are changed or saved
	$curif = $_POST['if'];
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
	$cursort = $_POST['sort'];
	$curfilter = $_POST['filter'];
	$curhostipformat = $_POST['hostipformat'];
	$curbackgroundupdate = $_POST['backgroundupdate'];
	$curinvert = $_POST['invert'];
	$cursmoothing = $_POST['smoothfactor'];
	$curmode = $_POST['mode'];
	
	// Save data to config
	if (isset($_POST['save'])) {
		$pconfig = array();
		$pconfig["if"] = $curif;
		$pconfig["sort"] = $cursort;
		$pconfig["filter"] = $curfilter;
		$pconfig["hostipformat"] = $curhostipformat;
		$pconfig["backgroundupdate"] = $curbackgroundupdate;
		$pconfig["smoothfactor"] = $cursmoothing;
		$pconfig["invert"] = $curinvert;
		$pconfig["mode"] = $curmode;
		$config["traffic_graphs"] = array();
		$config["traffic_graphs"] = $pconfig;
		write_config("Traffic Graphs settings updated");
	}
} else {
	// default settings from config
	if (is_array($pconfig)) {
		$curif = $pconfig['if'];
		$cursort = $pconfig['sort'];
		$curfilter = $pconfig['filter'];
		$curhostipformat = $pconfig['hostipformat'];
		$curbackgroundupdate = $pconfig['backgroundupdate'];
		$cursmoothing = $pconfig['smoothfactor'];
		$curinvert = $pconfig['invert'];
		$curmode = $pconfig['mode'];;
	} else {
		// initialize when no config details are present
		if (empty($ifdescrs["wan"])) {
			/* Handle the case when WAN has been disabled. Use the first key in ifdescrs. */
			reset($ifdescrs);
			$curif = key($ifdescrs);
		} else {
			$curif = "wan";
		}
		$cursort = "";
		$curfilter = "";
		$curhostipformat = "";
		$curbackgroundupdate = "";
		$cursmoothing = 0;
		$curinvert = "";
		$curmode = "";
	}
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

$form = new Form();
$form->addClass('auto-submit');

$section = new Form_Section('Graph Settings');

$group = new Form_Group('Traffic Graph');

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
    'mode',
    null,
    $curmode,
    array (
        'rate'		=> gettext('rate (standard)'),
        'iftop'	    => gettext('iftop (experimental)')
    )
))->setHelp('Mode');
    
$section->add($group);

$group2 = new Form_Group('Controls');

$group2->add(new Form_Select(
	'backgroundupdate',
	null,
	$curbackgroundupdate,
	array (
		'false'	=> gettext('Clear graphs when not visible.'),
		'true'	=> gettext('Keep graphs updated on inactive tab. (increases cpu usage)'),
	)
))->setHelp('Background updates');

$group2->add(new Form_Select(
	'invert',
	null,
	$curinvert,
	array (
		'true'	=> gettext('On'),
		'false'	=> gettext('Off'),
	)
))->setHelp('Invert in/out');

$group2->add(new Form_Input(
	'smoothfactor',
	null,
	'range',
	$cursmoothing,
	array (
		'min' => 0,
		'max' => 5,
		'step' => 1
		)

))->setHelp('Graph Smoothing');

$section->add($group2);

$form->add($section);
print $form;

$realif = get_real_interface($curif);
?>

<script src="/vendor/d3/d3.min.js?v=<?=filemtime('/usr/local/www/vendor/d3/d3.min.js')?>"></script>
<script src="/vendor/nvd3/nv.d3.js?v=<?=filemtime('/usr/local/www/vendor/nvd3/nv.d3.js')?>"></script>
<script src="/vendor/visibility/visibility-1.2.3.min.js?v=<?=filemtime('/usr/local/www/vendor/visibility/visibility-1.2.3.min.js')?>"></script>

<link href="/vendor/nvd3/nv.d3.css" media="screen, projection" rel="stylesheet" type="text/css">

<script type="text/javascript">


//<![CDATA[
events.push(function() {

	var InterfaceString = "<?=$curif?>";
	var RealInterfaceString = "<?=$realif?>";
    window.graph_backgroundupdate = $('#backgroundupdate').val() === "true";
	window.smoothing = $('#smoothfactor').val();
	window.interval = 1;
	window.invert = $('#invert').val() === "true";
	window.size = 8;
	window.interfaces = InterfaceString.split("|").filter(function(entry) { return entry.trim() != ''; });
	window.realinterfaces = RealInterfaceString.split("|").filter(function(entry) { return entry.trim() != ''; });

	graph_init();
	graph_visibilitycheck();

});
//]]>
</script>

<script src="/js/traffic-graphs.js?v=<?=filemtime('/usr/local/www/js/traffic-graphs.js')?>"></script>

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
