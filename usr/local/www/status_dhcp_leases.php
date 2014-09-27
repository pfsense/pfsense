<?php
/* $Id$ */
/*
	status_dhcp_leases.php
	Copyright (C) 2004-2009 Scott Ullrich
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
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
	pfSense_BUILDER_BINARIES:	/usr/bin/awk	/bin/cat	/usr/sbin/arp	/usr/bin/wc	/usr/bin/grep
	pfSense_MODULE:	dhcpserver
*/

##|+PRIV
##|*IDENT=page-status-dhcpleases
##|*NAME=Status: DHCP leases page
##|*DESCR=Allow access to the 'Status: DHCP leases' page.
##|*MATCH=status_dhcp_leases.php*
##|-PRIV

require("guiconfig.inc");
require_once("config.inc");

$pgtitle = array(gettext("Status"),gettext("DHCP leases"));
$shortcut_section = "dhcp";

$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";

if (($_GET['deleteip']) && (is_ipaddr($_GET['deleteip']))) {
	/* Stop DHCPD */
	killbyname("dhcpd");

	/* Read existing leases */
	/* $leases_contents has the lines of the file, including the newline char at the end of each line. */
	$leases_contents = file($leasesfile);
	$newleases_contents = array();
	$i=0;
	while ($i < count($leases_contents)) {
		/* Find the lease(s) we want to delete */
		if ($leases_contents[$i] == "lease {$_GET['deleteip']} {\n") {
			/* Skip to the end of the lease declaration */
			do {
				$i++;
			} while ($leases_contents[$i] != "}\n");
		} else {
			/* It's a line we want to keep, copy it over. */
			$newleases_contents[] = $leases_contents[$i];
		}
		$i++;
	}

	/* Write out the new leases file */
	$fd = fopen($leasesfile, 'w');
	fwrite($fd, implode("\n", $newleases_contents));
	fclose($fd);

	/* Restart DHCP Service */
	services_dhcpd_configure();
	header("Location: status_dhcp_leases.php?all={$_GET['all']}");
}

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php

function leasecmp($a, $b) {
        return strcmp($a[$_GET['order']], $b[$_GET['order']]);
}

function adjust_gmt($dt) {
	global $config; 
	$dhcpd = $config['dhcpd'];
	foreach ($dhcpd as $dhcpditem) {
		$dhcpleaseinlocaltime = $dhcpditem['dhcpleaseinlocaltime'];
		if ($dhcpleaseinlocaltime == "yes") 
			break;
	}
	if ($dhcpleaseinlocaltime == "yes") {
		$ts = strtotime($dt . " GMT");
		return strftime("%Y/%m/%d %I:%M:%S%p", $ts);
	} else
		return $dt;
}

function remove_duplicate($array, $field)
{
  foreach ($array as $sub)
   $cmp[] = $sub[$field];
  $unique = array_unique(array_reverse($cmp,true));
  foreach ($unique as $k => $rien)
   $new[] = $array[$k];
  return $new;
}

$awk = "/usr/bin/awk";
/* this pattern sticks comments into a single array item */
$cleanpattern = "'{ gsub(\"#.*\", \"\");} { gsub(\";\", \"\"); print;}'";
/* We then split the leases file by } */
$splitpattern = "'BEGIN { RS=\"}\";} {for (i=1; i<=NF; i++) printf \"%s \", \$i; printf \"}\\n\";}'";

/* stuff the leases file in a proper format into a array by line */
exec("/bin/cat {$leasesfile} | {$awk} {$cleanpattern} | {$awk} {$splitpattern}", $leases_content);
$leases_count = count($leases_content);
exec("/usr/sbin/arp -an", $rawdata);
$arpdata_ip = array();
$arpdata_mac = array();
foreach ($rawdata as $line) {
	$elements = explode(' ',$line);
	if ($elements[3] != "(incomplete)") {
		$arpent = array();
		$arpdata_ip[] = trim(str_replace(array('(',')'),'',$elements[1]));
		$arpdata_mac[] = strtolower(trim($elements[3]));
	}
}
unset($rawdata);
$pools = array();
$leases = array();
$i = 0;
$l = 0;
$p = 0;

// Put everything together again
foreach($leases_content as $lease) {
	/* split the line by space */
	$data = explode(" ", $lease);
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
				$pools[$p]['name'] = trim($data[$f+2], '"');
				$pools[$p]['name'] = "{$pools[$p]['name']} (" . convert_friendly_interface_to_friendly_descr(substr($pools[$p]['name'], 5)) . ")";
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
			case "rewind":
				/* skip the rewind binding statement */
				$f = $f+3;
				break;
			case "hardware":
				$leases[$l]['mac'] = $data[$f+2];
				/* check if it's online and the lease is active */
				if (in_array($leases[$l]['ip'], $arpdata_ip)) {
					$leases[$l]['online'] = 'online';
				} else {
					$leases[$l]['online'] = 'offline';
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
	/* slowly chisel away at the source array */
	array_shift($leases_content);
}
/* remove the old array */
unset($lease_content);

/* remove duplicate items by mac address */
if(count($leases) > 0) {
	$leases = remove_duplicate($leases,"ip");
}

if(count($pools) > 0) {
	$pools = remove_duplicate($pools,"name");
	asort($pools);
}

foreach($config['interfaces'] as $ifname => $ifarr) {
	if (is_array($config['dhcpd'][$ifname]) && 
		is_array($config['dhcpd'][$ifname]['staticmap'])) {
		foreach($config['dhcpd'][$ifname]['staticmap'] as $static) {
			$slease = array();
			$slease['ip'] = $static['ipaddr'];
			$slease['type'] = "static";
			$slease['mac'] = $static['mac'];
			$slease['start'] = "";
			$slease['end'] = "";
			$slease['hostname'] = htmlentities($static['hostname']);
			$slease['act'] = "static";
			$slease['online'] = in_array(strtolower($slease['mac']), $arpdata_mac) ? 'online' : 'offline';
			$leases[] = $slease;
		}
	}
}

if ($_GET['order'])
	usort($leases, "leasecmp");

/* only print pool status when we have one */
if(count($pools) > 0) {
?>
<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0" summary="dhcp leases">
  <tr>
    <td class="listhdrr"><?=gettext("Failover Group"); ?></a></td>
    <td class="listhdrr"><?=gettext("My State"); ?></a></td>
    <td class="listhdrr"><?=gettext("Since"); ?></a></td>
    <td class="listhdrr"><?=gettext("Peer State"); ?></a></td>
    <td class="listhdrr"><?=gettext("Since"); ?></a></td>
  </tr>
<?php
foreach ($pools as $data) {
	echo "<tr>\n";
	echo "<td class=\"listlr\">{$fspans}{$data['name']}{$fspane}</td>\n";
	echo "<td class=\"listr\">{$fspans}{$data['mystate']}{$fspane}</td>\n";
	echo "<td class=\"listr\">{$fspans}" . adjust_gmt($data['mydate']) . "{$fspane}</td>\n";
	echo "<td class=\"listr\">{$fspans}{$data['peerstate']}{$fspane}</td>\n";
	echo "<td class=\"listr\">{$fspans}" . adjust_gmt($data['peerdate']) . "{$fspane}</td>\n";
	echo "<td class=\"list\" valign=\"middle\" width=\"17\">&nbsp;</td>\n";
	echo "<td class=\"list\" valign=\"middle\" width=\"17\">&nbsp;</td>\n";
	echo "</tr>\n";
}

?>
</table>

<?php
/* only print pool status when we have one */
}
?>

<br/>

<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0" summary="dhcp leases">
  <tr>
    <td class="listhdrr"><a href="#"><?=gettext("IP address"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("MAC address"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("Hostname"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("Start"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("End"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("Online"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("Lease Type"); ?></a></td>
	</tr>
<?php
// Load MAC-Manufacturer table
$mac_man = load_mac_manufacturer_table();
foreach ($leases as $data) {
	if (($data['act'] == "active") || ($data['act'] == "static") || ($_GET['all'] == 1)) {
		if ($data['act'] != "active" && $data['act'] != "static") {
			$fspans = "<span class=\"gray\">";
			$fspane = "&nbsp;</span>";
		} else {
			$fspans = "";
			$fspane = "&nbsp;";
		}
                $lip = ip2ulong($data['ip']);
		if ($data['act'] == "static") {
			foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
				if(is_array($dhcpifconf['staticmap'])) {
					foreach ($dhcpifconf['staticmap'] as $staticent) {
						if ($data['ip'] == $staticent['ipaddr']) {
							$data['if'] = $dhcpif;
							break;
						}
					}
				}
				/* exit as soon as we have an interface */
				if ($data['if'] != "")
					break;
			}
		} else {
			foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
				if (!is_array($dhcpifconf['range']))
					continue;
                        	if (($lip >= ip2ulong($dhcpifconf['range']['from'])) && ($lip <= ip2ulong($dhcpifconf['range']['to']))) {
                                	$data['if'] = $dhcpif;
                                	break;
                        	}
			}
                }		
		echo "<tr>\n";
                echo "<td class=\"listlr\">{$fspans}{$data['ip']}{$fspane}</td>\n";
		$mac=$data['mac']; 
		$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
                if ($data['online'] != "online") {
			if(isset($mac_man[$mac_hi])){ // Manufacturer for this MAC is defined
	                        echo "<td class=\"listr\">{$fspans}<a href=\"services_wol.php?if={$data['if']}&amp;mac=$mac\" title=\"" . gettext("$mac - send Wake on LAN packet to this MAC address") ."\">{$mac}</a><br /><font size=\"-2\"><i>{$mac_man[$mac_hi]}</i></font>{$fspane}</td>\n";
			}else{	
                        	echo "<td class=\"listr\">{$fspans}<a href=\"services_wol.php?if={$data['if']}&amp;mac={$data['mac']}\" title=\"" . gettext("send Wake on LAN packet to this MAC address") ."\">{$data['mac']}</a>{$fspane}</td>\n";
			}
                }else{
			if(isset($mac_man[$mac_hi])){ // Manufacturer for this MAC is defined
				echo "<td class=\"listr\">{$fspans}{$mac}<br /><font size=\"-2\"><i>{$mac_man[$mac_hi]}</i></font>{$fspane}</td>\n";
	                }else{
                		echo "<td class=\"listr\">{$fspans}{$data['mac']}{$fspane}</td>\n";
			}
                }
                echo "<td class=\"listr\">{$fspans}"  . htmlentities($data['hostname']) . "{$fspane}</td>\n";
				if ($data['type'] != "static") {
					echo "<td class=\"listr\">{$fspans}" . adjust_gmt($data['start']) . "{$fspane}</td>\n";
					echo "<td class=\"listr\">{$fspans}" . adjust_gmt($data['end']) . "{$fspane}</td>\n";
				} else {
					echo "<td class=\"listr\">{$fspans} n/a {$fspane}</td>\n";
					echo "<td class=\"listr\">{$fspans} n/a {$fspane}</td>\n";
				}
                echo "<td class=\"listr\">{$fspans}{$data['online']}{$fspane}</td>\n";
                echo "<td class=\"listr\">{$fspans}{$data['act']}{$fspane}</td>\n";
                echo "<td valign=\"middle\">&nbsp;";
		if ($data['type'] == "dynamic") {
			echo "<a href=\"services_dhcp_edit.php?if={$data['if']}&amp;mac={$data['mac']}&amp;hostname={$data['hostname']}\">";
			echo "<img src=\"/themes/{$g['theme']}/images/icons/icon_plus.gif\" width=\"17\" height=\"17\" border=\"0\" title=\"" . gettext("add a static mapping for this MAC address") ."\" alt=\"add\" /></a>&nbsp;\n";
		} else {
			echo "<img src=\"/themes/{$g['theme']}/images/icons/icon_plus_mo.gif\" width=\"17\" height=\"17\" border=\"0\" alt=\"add\" />&nbsp;\n";
		}

                echo "<a href=\"services_wol_edit.php?if={$data['if']}&amp;mac={$data['mac']}&amp;descr={$data['hostname']}\">";
		echo "<img src=\"/themes/{$g['theme']}/images/icons/icon_wol_all.gif\" width=\"17\" height=\"17\" border=\"0\" title=\"" . gettext("add a Wake on LAN mapping for this MAC address") ."\" alt=\"add\" /></a>&nbsp;\n";

		/* Only show the button for offline dynamic leases */
		if (($data['type'] == "dynamic") && ($data['online'] != "online")) {
			echo "<a href=\"status_dhcp_leases.php?deleteip={$data['ip']}&amp;all=" . htmlspecialchars($_GET['all']) . "\">";
			echo "<img src=\"/themes/{$g['theme']}/images/icons/icon_x.gif\" width=\"17\" height=\"17\" border=\"0\" title=\"" . gettext("delete this DHCP lease") . "\" alt=\"delete\" /></a>&nbsp;\n";
		}
                echo "</td></tr>\n";
	}
}

?>
</table>
<br/>
<form action="status_dhcp_leases.php" method="get">
<input type="hidden" name="order" value="<?=htmlspecialchars($_GET['order']);?>" />
<?php if ($_GET['all']): ?>
<input type="hidden" name="all" value="0" />
<input type="submit" class="formbtn" value="<?=gettext("Show active and static leases only"); ?>" />
<?php else: ?>
<input type="hidden" name="all" value="1" />
<input type="submit" class="formbtn" value="<?=gettext("Show all configured leases"); ?>" />
<?php endif; ?>
</form>
<?php if($leases == 0): ?>
<p><strong><?=gettext("No leases file found. Is the DHCP server active"); ?>?</strong></p>
<?php endif; ?>

<?php include("fend.inc"); ?>
</body>
</html>
