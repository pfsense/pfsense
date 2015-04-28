<?php
/* $Id$ */
/*
	status_graph.php
	Part of pfSense
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	Originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_MODULE: routing
*/

##|+PRIV
##|*IDENT=page-status-trafficgraph
##|*NAME=Status: Traffic Graph page
##|*DESCR=Allow access to the 'Status: Traffic Graph' page.
##|*MATCH=status_graph.php*
##|*MATCH=bandwidth_by_ip.php*
##|*MATCH=graph.php*
##|*MATCH=ifstats.php*
##|-PRIV

require("guiconfig.inc");

if ($_POST['width'])
	$width = $_POST['width'];
else
	$width = "100%";

if ($_POST['height'])
	$height = $_POST['height'];
else
	$height = "200";

// Get configured interface list
$ifdescrs = get_configured_interface_with_descr();
if (isset($config['ipsec']['enable']))
	$ifdescrs['enc0'] = "IPsec";
foreach (array('server', 'client') as $mode) {
	if (is_array($config['openvpn']["openvpn-{$mode}"])) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
			if (!isset($setting['disable'])) {
				$ifdescrs['ovpn' . substr($mode, 0, 1) . $setting['vpnid']] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
			}
		}
	}
}

if ($_POST['if']) {
	$curif = $_POST['if'];
	$found = false;
	foreach($ifdescrs as $descr => $ifdescr) {
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
	}
	else {
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

$pgtitle = array(gettext("Status"),gettext("Traffic Graph"));

include("head.inc");

require('classes/Form.class.php');

$form = new Form(false);
$form->addClass('auto-submit');

$section = new Form_Section('Graph settings');

$group = new Form_Group('Options');

$group->add(new Form_Select(
	'if',
	'',
	$curif,
	iflist()
))->setHelp('Interface');

$group->add(new Form_Select(
	'sort',
	'',
	$cursort,
	array (
		'in'  => 'Bw In',
		'out' => 'BW Out'
	)
))->setHelp('Sort by');

$group->add(new Form_Select(
	'filter',
	'',
	$curfilter,
	array (
		'local'	 => 'Local',
		'remote' => 'Remote',
		'all'	 => 'All'
	)
))->setHelp('Filter');

$group->add(new Form_Select(
	'hostipformat',
	' ',
	$curhostipformat,
	array (
		''		   => 'IP Address',
		'hostname' => 'Host Name',
		'fqdn'	   => 'FQDN'
	)
))->setHelp('Display');

$section->add($group);

$form->add($section);
print $form;

?>

<script type="text/javascript">
//<![CDATA[
function updateBandwidth(){
	var hostinterface = jQuery("#if").val();
	var sorting = jQuery("#sort").val();
	var filter = jQuery("#filter").val();
	var hostipformat = jQuery("#hostipformat").val();
	bandwidthAjax(hostinterface, sorting, filter, hostipformat);
}

function bandwidthAjax(hostinterface, sorting, filter, hostipformat) {
	uri = "bandwidth_by_ip.php?if=" + hostinterface + "&sort=" + sorting + "&filter=" + filter + "&hostipformat=" + hostipformat;

	var opt = {
		// Use GET
		type: 'get',
		error: function(req) {
			/* XXX: Leave this for debugging purposes: Handle 404
			if(req.status == 404)
				alert('Error 404: location "' + uri + '" was not found.');
		*/
			/* Handle other errors
			else
				alert('Error ' + req.status + ' -- ' + req.statusText + ' -- ' + uri);
		*/
		},
		success: function(data) {
			updateBandwidthHosts(data);
		}
	}
	jQuery.ajax(uri, opt);
}

function updateBandwidthHosts(data){
	var hosts_split = data.split("|");
	d = document;
	//parse top ten bandwidth abuser hosts
	for (var y=0; y<10; y++){
		if ((y < hosts_split.length) && (hosts_split[y] != "") && (hosts_split[y] != "no info")) {
			hostinfo = hosts_split[y].split(";");

			//update host ip info
			var HostIpID = "hostip" + y;
			var hostip = d.getElementById(HostIpID);
			hostip.innerHTML = hostinfo[0];

			//update bandwidth inbound to host
			var hostbandwidthInID = "bandwidthin" + y;
			var hostbandwidthin = d.getElementById(hostbandwidthInID);
			hostbandwidthin.innerHTML = hostinfo[1] + " Bits/sec";

			//update bandwidth outbound from host
			var hostbandwidthOutID = "bandwidthout" + y;
			var hostbandwidthOut = d.getElementById(hostbandwidthOutID);
			hostbandwidthOut.innerHTML = hostinfo[2] + " Bits/sec";

			//make the row appear if hidden
			var rowid = "#host" + y;
			if (jQuery(rowid).css('display') == "none"){
				//hide rows that contain no data
				jQuery(rowid).show(1000);
			}
		}
		else
		{
			var rowid = "#host" + y;
			if (jQuery(rowid).css('display') != "none"){
				//hide rows that contain no data
				jQuery(rowid).fadeOut(2000);
			}
		}
	}

	setTimeout('updateBandwidth()', 1000);
}
//]]>
</script>

<?php

/* link the ipsec interface magically */
if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable']))
	$ifdescrs['enc0'] = "IPsec";

?>

<div id="niftyOutter" class="panel panel-default">
	<div id="col1" style="float: left; width: 46%; padding: 5px; position: relative;">
		<object data="graph.php?ifnum=<?=htmlspecialchars($curif);?>&amp;ifname=<?=rawurlencode($ifdescrs[htmlspecialchars($curif)]);?>">
		  <param name="id" value="graph" />
		  <param name="type" value="image/svg+xml" />
		  <param name="width" value="<? echo $width; ?>" />
		  <param name="height" value="<? echo $height; ?>" />
		  <param name="pluginspage" value="http://www.adobe.com/svg/viewer/install/auto" />
		</object>
	</div>
	<div id="col2" style="float: right; width: 48%; padding: 5px; position: relative;">
		<table width="100%" border="0" cellspacing="0" cellpadding="0" summary="status">
			<tr>
				<td ><?=(($curhostipformat=="") ? gettext("Host IP") : gettext("Host Name or IP")); ?></td>
				<td><?=gettext("Bandwidth In"); ?></td>
				<td><?=gettext("Bandwidth Out"); ?></td>
		   </tr>

<?php
			for($idx=0; $idx<10; $idx++) { ?>
				<tr id="host<?=$idx?>" >
					<td id="hostip<?=$idx?>">
					</td>
					<td id="bandwidthin<?=$idx?>">
					</td>
					<td id="bandwidthout<?=$idx?>">
					</td>
				</tr>
<?php
			}
?>

		</table>
	</div>
	<div style="clear: both;"></div>
</div>

<div class="alert alert-warning">
	<strong><?=gettext("Note: "); ?>:</strong><?=gettext("the "); ?><a href="http://www.adobe.com/svg/viewer/install/" target="_blank"><?=gettext("Adobe SVG Viewer"); ?></a>, <?=gettext("Firefox 1.5 or later or other browser supporting SVG is required to view the graph"); ?>.</p>

<script>
events.push(function(){
	$('.auto-submit').on('change', function(){
	$(this).submit();
	});
});

events.push(function(){
	jQuery(document).ready(updateBandwidth);
});
</script>

<?php include("foot.inc"); ?>