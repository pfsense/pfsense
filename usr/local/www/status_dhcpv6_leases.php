<?php
/* $Id$ */
/*
	status_dhcpv6_leases.php
	Copyright (C) 2004-2009 Scott Ullrich
	Copyright (C) 2011 Seth Mos
	All rights reserved.

	originially part of m0n0wall (http://m0n0.ch/wall)
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
##|*IDENT=page-status-dhcpv6leases
##|*NAME=Status: DHCPv6 leases page
##|*DESCR=Allow access to the 'Status: DHCPv6 leases' page.
##|*MATCH=status_dhcpv6_leases.php*
##|-PRIV

require("guiconfig.inc");

$pgtitle = array(gettext("Status"),gettext("DHCPv6 leases"));

$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases";

if (($_GET['deleteip']) && (is_ipaddr($_GET['deleteip']))) {
	/* Stop DHCPD */
	killbyname("dhcpd");

	/* Read existing leases */
	$leases_contents = explode("\n", file_get_contents($leasesfile));
	$newleases_contents = array();
	$i=0;
	while ($i < count($leases_contents)) {
		/* Find the lease(s) we want to delete */
		if ($leases_contents[$i] == "lease {$_GET['deleteip']} {") {
			/* Skip to the end of the lease declaration */
			do {
				$i++;
			} while ($leases_contents[$i] != "}");
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
	header("Location: status_dhcpv6_leases.php?all={$_GET['all']}");
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
	$ts = strtotime($dt . " GMT");
	return strftime("%Y/%m/%d %H:%M:%S", $ts);
}

function remove_duplicate($array, $field) {
	foreach ($array as $sub)
		$cmp[] = $sub[$field];
	$unique = array_unique(array_reverse($cmp,true));
	foreach ($unique as $k => $rien)
		$new[] = $array[$k];
	return $new;
}

function parse_duid($duid_string) {
	$parsed_duid = array();
	for ($i=0; $i < strlen($duid_string); $i++) {
		$s = substr($duid_string, $i, 1);
		if ($s == '\\') {
			$n = substr($duid_string, $i+1, 1);
			if (($n == '\\') || ($n == '"')) {
				$parsed_duid[] = sprintf("%02x", ord($n));
			} elseif (is_numeric($n)) {
				$parsed_duid[] = sprintf("%02x", octdec(substr($duid_string, $i+1, 3)));
				$i += 3;
			}
		} else {
			$parsed_duid[] = sprintf("%02x", ord($s));
		}
	}
	$iaid = array_slice($parsed_duid, 0, 4);
	$duid = array_slice($parsed_duid, 4);
	return array($iaid, $duid);
}

$awk = "/usr/bin/awk";

/* this pattern sticks comments into a single array item */
$cleanpattern = "'{ gsub(\"^#.*\", \"\");} { gsub(\"^server-duid.*\", \"\");} { gsub(\";\", \"\"); print;}'";
/* We then split the leases file by } */
$splitpattern = "'BEGIN { RS=\"}\";} {for (i=1; i<=NF; i++) printf \"%s \", \$i; printf \"}\\n\";}'";

/* stuff the leases file in a proper format into a array by line */
exec("/bin/cat {$leasesfile} | {$awk} {$cleanpattern} | {$awk} {$splitpattern} | /usr/bin/grep '^ia-na'", $leases_content);
$leases_count = count($leases_content);
exec("/usr/sbin/ndp -an", $rawdata);
$arpdata = array();
foreach ($rawdata as $line) {
	$elements = preg_split('/\s+/ ',$line);
	if ($elements[1] != "(incomplete)") {
		$arpent = array();
		$ip = trim(str_replace(array('(',')'),'',$elements[0]));
		$arpent['mac'] = trim($elements[1]);
		$arpent['interface'] = trim($elements[2]);
		$arpdata[$ip] = $arpent;
	}
}

$pools = array();
$leases = array();
$i = 0;
$l = 0;
$p = 0;

// Put everything together again
while($i < $leases_count) {
	/* split the line by space */
	$duid_split = array();
	preg_match('/ia-na "(.*)" { (.*)/ ', $leases_content[$i], $duid_split);
	if (!empty($duid_split[1])) {
		$iaid_duid = parse_duid($duid_split[1]);
		$leases[$l]['iaid'] = hexdec(implode("", array_reverse($iaid_duid[0])));
		$leases[$l]['duid'] = implode(":", $iaid_duid[1]);
		$data = explode(" ", $duid_split[2]);
	} else {
		$data = explode(" ", $leases_content[$i]);
	}
	/* walk the fields */
	$f = 0;
	$fcount = count($data);
	/* with less then 12 fields there is nothing useful */
	if($fcount < 12) {
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
			case "ia-na":
				if ($data[$f+1][0] == '"') {
					$duid = "";
					/* FIXME: This needs a safety belt to prevent an infinite loop */
					while ($data[$f][strlen($data[$f])-1] != '"') {
						$duid .= " " . $data[$f+1];
						$f++;
					}
					$leases[$l]['duid'] = $duid;
				} else {
					$leases[$l]['duid'] = $data[$f+1];
				}
				$leases[$l]['type'] = "dynamic";
				$f = $f+2;
				break;
			case "iaaddr":
				$leases[$l]['ip'] = $data[$f+1];
				if (in_array($leases[$l]['ip'], array_keys($arpdata))) {
					$leases[$l]['online'] = 'online';
				} else {
					$leases[$l]['online'] = 'offline';
				}
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
				$leases[$l]['start'] = $data[$f+2];
				$leases[$l]['start'] .= " " . $data[$f+3];
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

if(count($leases) > 0) {
	$leases = remove_duplicate($leases,"ip");
}

if(count($pools) > 0) {
	$pools = remove_duplicate($pools,"name");
	asort($pools);
}

foreach($config['interfaces'] as $ifname => $ifarr) {
	if (is_array($config['dhcpdv6'][$ifname]) && 
		is_array($config['dhcpdv6'][$ifname]['staticmap'])) {
		foreach($config['dhcpdv6'][$ifname]['staticmap'] as $static) {
			$slease = array();
			$slease['ip'] = $static['ipaddrv6'];
			$slease['type'] = "static";
			$slease['duid'] = $static['duid'];
			$slease['start'] = "";
			$slease['end'] = "";
			$slease['hostname'] = htmlentities($static['hostname']);
			$slease['act'] = "static";
			$online = exec("/usr/sbin/ndp -an |/usr/bin/grep {$slease['ip']}| /usr/bin/wc -l|/usr/bin/awk '{print $1;}'");
			if ($online == 1) {
				$slease['online'] = 'online';
			} else {
				$slease['online'] = 'offline';
			}
			$leases[] = $slease;
		}
	}
}

if ($_GET['order'])
	usort($leases, "leasecmp");

/* only print pool status when we have one */
if(count($pools) > 0) {
?>
<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
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
	echo "<td class=\"listlr\">{$fspans}{$data['name']}{$fspane}&nbsp;</td>\n";
	echo "<td class=\"listr\">{$fspans}{$data['mystate']}{$fspane}&nbsp;</td>\n";
	echo "<td class=\"listr\">{$fspans}" . adjust_gmt($data['mydate']) . "{$fspane}&nbsp;</td>\n";
	echo "<td class=\"listr\">{$fspans}{$data['peerstate']}{$fspane}&nbsp;</td>\n";
	echo "<td class=\"listr\">{$fspans}" . adjust_gmt($data['peerdate']) . "{$fspane}&nbsp;</td>\n";
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

<p>

<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td class="listhdrr"><a href="#"><?=gettext("IPv6 address"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("IAID"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("DUID"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("Hostname/MAC"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("Start"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("End"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("Online"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("Lease Type"); ?></a></td>
	</tr>
<?php
foreach ($leases as $data) {
	if (($data['act'] == "active") || ($data['act'] == "static") || ($_GET['all'] == 1)) {
		if ($data['act'] != "active" && $data['act'] != "static") {
			$fspans = "<span class=\"gray\">";
			$fspane = "</span>";
		} else {
			$fspans = $fspane = "";
		}
                // $lip = ip2ulong($data['ip']);
		if ($data['act'] == "static") {
			foreach ($config['dhcpdv6'] as $dhcpif => $dhcpifconf) {
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
                	foreach ($config['dhcpdv6'] as $dhcpif => $dhcpifconf) {	
                        	if (($lip >= ip2ulong($dhcpifconf['range']['from'])) && ($lip <= ip2ulong($dhcpifconf['range']['to']))) {
                                	$data['if'] = $dhcpif;
                                	break;
                        	}
			}
                }		
		echo "<tr>\n";
                echo "<td class=\"listlr\">{$fspans}{$data['ip']}{$fspane}&nbsp;</td>\n";
		echo "<td class=\"listr\">{$fspans}{$data['iaid']}{$fspane}&nbsp;</td>\n";
		echo "<td class=\"listr\">{$fspans}{$data['duid']}{$fspane}&nbsp;</td>\n";
		echo "<td class=\"listr\">{$fspans}";
		if (!empty($data['hostname'])) {
			echo htmlentities($data['hostname']) . "<br/>";
		}
		echo htmlentities($arpdata[$data['ip']]['mac']);
		echo "{$fspane}&nbsp;</td>\n";
				if ($data['type'] != "static") {
					echo "<td class=\"listr\">{$fspans}" . adjust_gmt($data['start']) . "{$fspane}&nbsp;</td>\n";
					echo "<td class=\"listr\">{$fspans}" . adjust_gmt($data['end']) . "{$fspane}&nbsp;</td>\n";
				} else {
					echo "<td class=\"listr\">{$fspans} n/a {$fspane}&nbsp;</td>\n";
					echo "<td class=\"listr\">{$fspans} n/a {$fspane}&nbsp;</td>\n";
				}
                echo "<td class=\"listr\">{$fspans}{$data['online']}{$fspane}&nbsp;</td>\n";
                echo "<td class=\"listr\">{$fspans}{$data['act']}{$fspane}&nbsp;</td>\n";
		
		if ($data['type'] == "dynamic") {
			echo "<td valign=\"middle\"><a href=\"services_dhcpv6_edit.php?if={$data['if']}&duid={$data['duid']}&hostname={$data['hostname']}\">";
			echo "<img src=\"/themes/{$g['theme']}/images/icons/icon_plus.gif\" width=\"17\" height=\"17\" border=\"0\" title=\"" . gettext("add a static mapping for this MAC address") ."\"></a></td>\n";
		} else {
                	echo "<td class=\"list\" valign=\"middle\">";
			echo "<img src=\"/themes/{$g['theme']}/images/icons/icon_plus_mo.gif\" width=\"17\" height=\"17\" border=\"0\"></td>\n";
		}

		/* Only show the button for offline dynamic leases */
		if (($data['type'] == "dynamic") && ($data['online'] != "online")) {
			echo "<td class=\"list\" valign=\"middle\"><a href=\"status_dhcpv6_leases.php?deleteip={$data['ip']}&all=" . htmlspecialchars($_GET['all']) . "\">";
			echo "<img src=\"/themes/{$g['theme']}/images/icons/icon_x.gif\" width=\"17\" height=\"17\" border=\"0\" title=\"" . gettext("delete this DHCP lease") . "\"></a></td>\n";
		}
                echo "</tr>\n";
	}
}

?>
</table>
<p>
<form action="status_dhcpv6_leases.php" method="GET">
<input type="hidden" name="order" value="<?=htmlspecialchars($_GET['order']);?>">
<?php if ($_GET['all']): ?>
<input type="hidden" name="all" value="0">
<input type="submit" class="formbtn" value="<?=gettext("Show active and static leases only"); ?>">
<?php else: ?>
<input type="hidden" name="all" value="1">
<input type="submit" class="formbtn" value="<?=gettext("Show all configured leases"); ?>">
<?php endif; ?>
</form>
<?php if($leases == 0): ?>
<p><strong><?=gettext("No leases file found. Is the DHCP server active"); ?>?</strong></p>
<?php endif; ?>

<?php include("fend.inc"); ?>
</body>
</html>
