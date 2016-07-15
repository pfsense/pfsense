<?php
/*
 * status_graph.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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

if ($_POST['width']) {
	$width = $_POST['width'];
} else {
	$width = "100%";
}

if ($_POST['height']) {
	$height = $_POST['height'];
} else {
	$height = "200";
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

// Compatiblity to restore GET parameters used pre-2.3
// Useful to save a URL for a given graph configuration
if (isset($_GET['if']) && !isset($_POST['if'])) {
	$_POST['if'] = $_GET['if'];
}
if (isset($_GET['sort']) && !isset($_POST['sort'])) {
	$_POST['sort'] = $_GET['sort'];
}
if (isset($_GET['filter']) && !isset($_POST['filter'])) {
	$_POST['filter'] = $_GET['filter'];
}
if (isset($_GET['hostipformat']) && !isset($_POST['hostipformat'])) {
	$_POST['hostipformat'] = $_GET['hostipformat'];
}

if ($_POST['if']) {
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
} else {
	if (empty($ifdescrs["wan"])) {
		/* Handle the case when WAN has been disabled. Use the first key in ifdescrs. */
		reset($ifdescrs);
		$curif = key($ifdescrs);
	} else {
		$curif = "wan";
	}
}
if ($_POST['sort']) {
	$cursort = $_POST['sort'];
} else {
	$cursort = "";
}
if ($_POST['filter']) {
	$curfilter = $_POST['filter'];
} else {
	$curfilter = "";
}
if ($_POST['hostipformat']) {
	$curhostipformat = $_POST['hostipformat'];
} else {
	$curhostipformat = "";
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

$section->add($group);

$form->add($section);
print $form;

?>
<script type="text/javascript">
//<![CDATA[

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
			<object data="graph.php?ifnum=<?=htmlspecialchars($curif);?>&amp;ifname=<?=rawurlencode($ifdescrs[htmlspecialchars($curif)]);?>">
				<param name="id" value="graph" />
				<param name="type" value="image/svg+xml" />
				<param name="width" value="<?=$width;?>" />
				<param name="height" value="<?=$height;?>" />
				<param name="pluginspage" value="http://www.adobe.com/svg/viewer/install/auto" />
			</object>
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
