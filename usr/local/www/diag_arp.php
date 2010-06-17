<?php
/*
	diag_arp.php
	part of the pfSense project	(http://www.pfsense.org)
	Copyright (C) 2004-2009 Scott Ullrich <sullrich@gmail.com>

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2005 Paul Taylor (paultaylor@winndixie.com) and Manuel Kasper <mk@neon1.net>.
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
	pfSense_BUILDER_BINARIES:	/bin/cat		/usr/sbin/arp
	pfSense_MODULE:	arp
*/

##|+PRIV
##|*IDENT=page-diagnostics-arptable
##|*NAME=Diagnostics: ARP Table page
##|*DESCR=Allow access to the 'Diagnostics: ARP Table' page.
##|*MATCH=diag_arp.php*
##|-PRIV

@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);

require("guiconfig.inc");

function leasecmp($a, $b) {
	return strcmp($a[$_GET['order']], $b[$_GET['order']]);
}

function adjust_gmt($dt) {
	$ts = strtotime($dt . " GMT");
	return strftime("%Y/%m/%d %H:%M:%S", $ts);
}

function remove_duplicate($array, $field) {
	foreach ($array as $sub)
		$cmp[] = $sub[$field];
	$unique = array_unique($cmp);
	foreach ($unique as $k => $rien)
		$new[] = $array[$k];
	return $new;
}

// Define path to AWK
$awk = "/usr/bin/awk";

// Read in leases file
$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";

/* this pattern sticks comments into a single array item */
$cleanpattern = "'{ gsub(\"#.*\", \"\");} { gsub(\";\", \"\"); print;}'";

/* We then split the leases file by } */
$splitpattern = "'BEGIN { RS=\"}\";} {for (i=1; i<=NF; i++) printf \"%s \", \$i; printf \"}\\n\";}'";

/* stuff the leases file in a proper format into a array by line */
exec("cat {$leasesfile} | {$awk} {$cleanpattern} | {$awk} {$splitpattern}", $leases_content);
$leases_count = count($leases_content);

$pools = array();
$leases = array();
$i = 0;
$l = 0;
$p = 0;
// Put everything together again
while($i < $leases_count) {
        /* split the line by space */
        $data = explode(" ", $leases_content[$i]);
        /* walk the fields */
        $f = 0;
        $fcount = count($data);
        /* with less then 20 fields there is nothing useful */
        if($fcount < 20) {
                $i++;
                continue;
        }
        while($f < $fcount) {
                switch($data[$f]) {
                        case "failover":
                                $pools[$p]['name'] = $data[$f+2];
                                $pools[$p]['mystate'] = $data[$f+7];
                                $pools[$p]['peerstate'] = $data[$f+14];
                                $pools[$p]['mydate'] = $data[$f+10];
                                $pools[$p]['mydate'] .= " " . $data[$f+11];
                                $pools[$p]['peerdate'] = $data[$f+17];
                                $pools[$p]['peerdate'] .= " " . $data[$f+18];
                                $p++;
                                $i++;
                                continue 3;
                        case "lease":
                                $leases[$l]['ip'] = $data[$f+1];
                                $leases[$l]['type'] = "dynamic";
                                $f = $f+2;
                                break;
                        case "starts":
                                $leases[$l]['start'] = $data[$f+2];
                                $leases[$l]['start'] .= " " . $data[$f+3];
                                $f = $f+3;
                                break;
                        case "ends":
                                $leases[$l]['end'] = $data[$f+2];
                                $leases[$l]['end'] .= " " . $data[$f+3];
                                $f = $f+3;
                                break;
                        case "tstp":
                                $f = $f+3;
                                break;
                        case "tsfp":
                                $f = $f+3;
                                break;
                        case "atsfp":
                                $f = $f+3;
                                break;
                        case "cltt":
                                $f = $f+3;
                                break;
                        case "binding":
                                switch($data[$f+2]) {
                                        case "active":
                                                $leases[$l]['act'] = "active";
                                                break;
                                        case "free":
                                                $leases[$l]['act'] = "expired";
                                                $leases[$l]['online'] = "offline";
                                                break;
                                        case "backup":
                                                $leases[$l]['act'] = "reserved";
                                                $leases[$l]['online'] = "offline";
                                                break;
                                }
                                $f = $f+1;
                                break;
                        case "next":
                                /* skip the next binding statement */
                                $f = $f+3;
                                break;
                        case "hardware":
                                $leases[$l]['mac'] = $data[$f+2];
                                /* check if it's online and the lease is active */
                                if($leases[$l]['act'] == "active") {
                                        $online = exec("/usr/sbin/arp -an |/usr/bin/awk '/{$leases[$l]['ip']}/ {print}'|wc -l");
                                        if ($online == 1) {
                                                $leases[$l]['online'] = 'online';
                                        } else {
                                                $leases[$l]['online'] = 'offline';
                                        }
                                }
                                $f = $f+2;
                                break;
                        case "client-hostname":
                                if($data[$f+1] <> "") {
                                        $leases[$l]['hostname'] = preg_replace('/"/','',$data[$f+1]);
                                } else {
                                        $hostname = gethostbyaddr($leases[$l]['ip']);
                                        if($hostname <> "") {
                                                $leases[$l]['hostname'] = $hostname;
                                        }
                                }
                                $f = $f+1;
                                break;
                        case "uid":
                                $f = $f+1;
                                break;
                }
                $f++;
        }
        $l++;
        $i++;
}

/* remove duplicate items by mac address */
if(count($leases) > 0) {
        $leases = remove_duplicate($leases,"ip");
}

if(count($pools) > 0) {
        $pools = remove_duplicate($pools,"name");
        asort($pools);
}

// Put this in an easy to use form
$dhcpmac = array();
$dhcpip = array();
	
foreach ($leases as $value) {
	$dhcpmac[$value['mac']] = $value['hostname'];	
	$dhcpip[$value['ip']] = $value['hostname'];	
}

exec("/usr/sbin/arp -an",$rawdata);

$i = 0; 

/* if list */
$ifdescrs = get_configured_interface_with_descr();

foreach ($ifdescrs as $key =>$interface) {
	$hwif[$config['interfaces'][$key]['if']] = $interface;
}

$data = array();
foreach ($rawdata as $line) {
	$elements = explode(' ',$line);
	
	if ($elements[3] != "(incomplete)") {
		$arpent = array();
		$arpent['ip'] = trim(str_replace(array('(',')'),'',$elements[1]));
		$arpent['mac'] = trim($elements[3]);
		$arpent['interface'] = trim($elements[5]);
		$data[] = $arpent;
	}
}

function getHostName($mac,$ip)
{
	global $dhcpmac, $dhcpip;
	
	if ($dhcpmac[$mac])
		return $dhcpmac[$mac];
	else if ($dhcpip[$ip])
		return $dhcpip[$ip];
	else if(gethostbyaddr($ip) <> "" and gethostbyaddr($ip) <> $ip)
		return gethostbyaddr($ip);
	else 
		return "";	
}

$pgtitle = array(gettext("Diagnostics"),gettext("ARP Table"));
include("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000">
	
<?php include("fbegin.inc"); ?>

<div id="loading">
	<img src="/themes/{$g['theme']}/images/misc/loader.gif"><?= gettext("Loading, please wait..."); ?>
	<p/>&nbsp;
</div>

<?php

// Flush buffers out to client so that they see Loading, please wait....
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);

// Resolve hostnames and replace Z_ with "".  The intention
// is to sort the list by hostnames, alpha and then the non
// resolvable addresses will appear last in the list.
foreach ($data as &$entry) {
	$dns = trim(getHostName($entry['mac'], $entry['ip']));
	if(trim($dns))
		$entry['dnsresolve'] = "$dns";
	else 
		$entry['dnsresolve'] = "Z_ ";
}

// Sort the data alpha first
$data = msort($data, "dnsresolve");

?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
			<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
				<tr>
					<td class="listhdrr"><?= gettext("IP address"); ?></td>
					<td class="listhdrr"><?= gettext("MAC address"); ?></td>
					<td class="listhdrr"><?= gettext("Hostname"); ?></td>
					<td class="listhdr"><?= gettext("Interface"); ?></td>
					<td class="list"></td>
				</tr>
				<?php foreach ($data as $entry): ?>
					<tr>
						<td class="listlr"><?=$entry['ip'];?></td>
						<td class="listr"><?=$entry['mac'];?></td>
						<td class="listr">
							<?php
							echo str_replace("Z_ ", "", $entry['dnsresolve']);
							?>
						</td>
						<td class="listr"><?=$hwif[$entry['interface']];?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
</table>

<?php include("fend.inc"); ?>

<script type="text/javascript">
	$('loading').innerHTML = '';
</script>
