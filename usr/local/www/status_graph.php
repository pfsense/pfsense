<?php
/* $Id$ */
/*
	status_graph.php
	Part of pfSense
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
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-status-trafficgraph
##|*NAME=Status: Traffic Graph page
##|*DESCR=Allow access to the 'Status: Traffic Graph' page.
##|*MATCH=status_graph.php*
##|*MATCH=bandwidth_by_ip.php*
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

if ($_GET['if']) {
	$curif = $_GET['if'];
	$found = false;
	foreach($ifdescrs as $descr => $ifdescr) {
		if ($descr == $curif) {
			$found = true;
			break;
		}
	}
	if ($found === false) {
		Header("Location: status_graph.php");
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
if ($_GET['sort']) {
	$cursort = $_GET['sort'];
} else {
	$cursort = "";
}
if ($_GET['filter']) {
	$curfilter = $_GET['filter'];
} else {
	$curfilter = "";
}
if ($_GET['hostipformat']) {
	$curhostipformat = $_GET['hostipformat'];
} else {
	$curhostipformat = "";
}

$pgtitle = array(gettext("Status"),gettext("Traffic Graph"));

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<script language="javascript" type="text/javascript">
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

<?php include("fbegin.inc"); ?>
<?php

/* link the ipsec interface magically */
if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable'])) 
	$ifdescrs['enc0'] = "IPsec";

?>
<form name="form1" action="status_graph.php" method="get" style="padding-bottom: 10px; margin-bottom: 14px; border-bottom: 1px solid #999999">
<?=gettext("Interface"); ?>:
<select id="if" name="if" class="formselect" style="z-index: -10;" onchange="document.form1.submit()">
<?php
foreach ($ifdescrs as $ifn => $ifd) {
	echo "<option value=\"$ifn\"";
	if ($ifn == $curif) echo " selected=\"selected\"";
	echo ">" . htmlspecialchars($ifd) . "</option>\n";
}
?>
</select>
, Sort by: 
<select id="sort" name="sort" class="formselect" style="z-index: -10;" onchange="document.form1.submit()">
	<option value="">Bw In</option>
	<option value="out"<?php if ($cursort == "out") echo " selected=\"selected\"";?>>Bw Out</option>
</select>
, Filter: 
<select id="filter" name="filter" class="formselect" style="z-index: -10;" onchange="document.form1.submit()">
	<option value="local"<?php if ($curfilter == "local") echo " selected=\"selected\"";?>>Local</option>
	<option value="remote"<?php if ($curfilter == "remote") echo " selected=\"selected\"";?>>Remote</option>
	<option value="all"<?php if ($curfilter == "all") echo " selected=\"selected\"";?>>All</option>
</select>
, Display: 
<select id="hostipformat" name="hostipformat" class="formselect" style="z-index: -10;" onchange="document.form1.submit()">
	<option value="">IP Address</option>
	<option value="hostname"<?php if ($curhostipformat == "hostname") echo " selected=\"selected\"";?>>Host Name</option>
	<option value="fqdn"<?php if ($curhostipformat == "fqdn") echo " selected=\"selected\"";?>>FQDN</option>
</select>
</form>
<p/>
<div id="niftyOutter">
    <div id="col1" style="float: left; width: 46%; padding: 5px; position: relative;">
		<object
			data="graph.php?ifnum=<?=htmlspecialchars($curif);?>&amp;ifname=<?=rawurlencode($ifdescrs[htmlspecialchars($curif)]);?>" 
			type="image/svg+xml" 
			width="<?=$width;?>" height="<?=$height;?>" >
		</object>
    </div>
    <div id="col2" style="float: right; width: 48%; padding: 5px; position: relative;">
        <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tr>
                <td class="listtopic" valign="top"><?=(($curhostipformat=="") ? gettext("Host IP") : gettext("Host Name or IP")); ?></td>
                <td class="listtopic" valign="top"><?=gettext("Bandwidth In"); ?></td>
                <td class="listtopic" valign="top"><?=gettext("Bandwidth Out"); ?></td>
           </tr>
           <tr id="host0" style="display:none">
                <td id="hostip0" class="vncell">
                </td>
                <td id="bandwidthin0" class="listr">
                </td>
                <td id="bandwidthout0" class="listr">
                </td>
           </tr>
           <tr id="host1" style="display:none">
                <td id="hostip1" class="vncell">
                </td>
                <td id="bandwidthin1" class="listr">
                </td>
                <td id="bandwidthout1" class="listr">
                </td>
           </tr>
           <tr id="host2" style="display:none">
                <td id="hostip2" class="vncell">
                </td>
                <td id="bandwidthin2" class="listr">
                </td>
                <td id="bandwidthout2" class="listr">
                </td>
           </tr>
           <tr id="host3" style="display:none">
                <td id="hostip3" class="vncell">
                </td>
                <td id="bandwidthin3" class="listr">
                </td>
                <td id="bandwidthout3" class="listr">
                </td>
           </tr>
           <tr id="host4" style="display:none">
                <td id="hostip4" class="vncell">
                </td>
                <td id="bandwidthin4" class="listr">
                </td>
                <td id="bandwidthout4" class="listr">
                </td>
           </tr>
           <tr id="host5" style="display:none">
                <td id="hostip5" class="vncell">
                </td>
                <td id="bandwidthin5" class="listr">
                </td>
                <td id="bandwidthout5" class="listr">
                </td>
           </tr>
           <tr id="host6" style="display:none">
                <td id="hostip6" class="vncell">
                </td>
                <td id="bandwidthin6" class="listr">
                </td>
                <td id="bandwidthout6" class="listr">
                </td>
           </tr>
           <tr id="host7" style="display:none">
                <td id="hostip7" class="vncell">
                </td>
                <td id="bandwidthin7" class="listr">
                </td>
                <td id="bandwidthout7" class="listr">
                </td>
           </tr>
           <tr id="host8" style="display:none">
                <td id="hostip8" class="vncell">
                </td>
                <td id="bandwidthin8" class="listr">
                </td>
                <td id="bandwidthout8" class="listr">
                </td>
           </tr>
           <tr id="host9" style="display:none">
                <td id="hostip9" class="vncell">
                </td>
                <td id="bandwidthin9" class="listr">
                </td>
                <td id="bandwidthout9" class="listr">
                </td>
           </tr>
        </table>
	</div>
	<div style="clear: both;"></div>
</div>
<p/><span class="red"><strong><?=gettext("Note"); ?>:</strong></span> <?=gettext("the"); ?> <a href="http://www.adobe.com/svg/viewer/install/" target="_blank"><?=gettext("Adobe SVG Viewer"); ?></a>, <?=gettext("Firefox 1.5 or later or other browser supporting SVG is required to view the graph"); ?>.

<?php include("fend.inc"); ?>

<script type="text/javascript">
jQuery(document).ready(updateBandwidth);
</script>
</body>
</html>
