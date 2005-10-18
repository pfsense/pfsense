#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	diag_dhcp_leases.php
	Copyright (C) 2004 Scott Ullrich
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

require("guiconfig.inc");

$pgtitle = "Diagnostics: DHCP leases";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php

flush();

function leasecmp($a, $b) {
        return strcmp($a[$_GET['order']], $b[$_GET['order']]);
}

function adjust_gmt($dt) {
        $ts = strtotime($dt . " GMT");
        return strftime("%Y/%m/%d %H:%M:%S", $ts);
}

$fp = @fopen("{$g['vardb_path']}/dhcpd.leases","r");

if ($fp):

$return = array();

while ($line = fgets($fp)) {
	$matches = "";

	// Sort out comments
	// C-style comments not supported!
	if (preg_match("/^\s*[\r|\n]/", $line, $matches[0]) ||
				preg_match("/^([^\"#]*)#.*$/", $line, $matches[1]) ||
				preg_match("/^([^\"]*)\/\/.*$/", $line, $matches[2]) ||
				preg_match("/\s*#(.*)/", $line, $matches[3]) ||
				preg_match("/\\\"\176/", $line, $matches[4])
		) {
		$line = "";
		continue;
	}

	if (preg_match("/(.*)#(.*)/", $line, $matches))
		$line = $matches[0];

	// Tokenize lines
	do {
		if (preg_match("/^\s*\"([^\"]*)\"(.*)$/", $line, $matches)) {
			$line = $matches[2];
			$return[] = array($matches[1], 0);
		} else if (preg_match("/^\s*([{};])(.*)$/", $line, $matches)) {
			$line = $matches[2];
			$return[] = array($matches[0], 1);
		} else if (preg_match("/^\s*([^{}; \t]+)(.*)$/", $line, $matches)) {
			$line = $matches[2];
			$return[] = array($matches[1], 0);
		} else
			break;

	} while($line);

	$lines++;
}

fclose($fp);

$leases = array();
$i = 0;

// Put everything together again
while ($data = array_shift($return)) {
	if ($data[0] == "next") {
		$d = array_shift($return);
	}
	if ($data[0] == "lease") {
		$d = array_shift($return);
		$leases[$i]['ip'] = $d[0];
		$leases[$i]['type'] = "dynamic";
	}
	if ($data[0] == "client-hostname") {
		$d = array_shift($return);
		$leases[$i]['hostname'] = $d[0];
	}
	if ($data[0] == "hardware") {
		$d = array_shift($return);
		if ($d[0] == "ethernet") {
			$d = array_shift($return);
			$leases[$i]['mac'] = $d[0];
			$online = exec("/usr/sbin/arp -an |/usr/bin/grep {$d[0]}| /usr/bin/wc -l|/usr/bin/awk '{print $1;}'");
			if ($online == 1) {
				$leases[$i]['online'] = 'online';
			} else {
				$leases[$i]['online'] = 'offline';
			}
		}
	} else if ($data[0] == "starts") {
		$d = array_shift($return);
		$d = array_shift($return);
		$leases[$i]['start'] = $d[0];
		$d = array_shift($return);
		$leases[$i]['start'] .= " " . $d[0];
	} else if ($data[0] == "ends") {
		$d = array_shift($return);
		$d = array_shift($return);
		$leases[$i]['end'] = $d[0];
		$d = array_shift($return);
		$leases[$i]['end'] .= " " . $d[0];
	} else if ($data[0] == "binding") {
		$d = array_shift($return);
		if ($d[0] == "state") {
			$d = array_shift($return);
			switch($d[0]) {
				case 'active':
					$leases[$i]['act'] = 'active';
					break;
				case 'free':
					$leases[$i]['act'] = 'expired';
					break;
				case 'backup':
					$leases[$i]['act'] = 'reserved';	
					$leases[$i]['online'] = 'offline';
					break;
			}
		}
	} else if (($data[0] == "}") && ($data[1] == 1))		// End of group
		$i++;
}
foreach($config['interfaces'] as $ifname => $ifarr) {
	if (is_array($config['dhcpd'][$ifname]['staticmap'])) {
		foreach($config['dhcpd'][$ifname]['staticmap'] as $static) {
			$slease = array();
			$slease['ip'] = $static['ipaddr'];
			$slease['type'] = "static";
			$slease['mac'] = $static['mac'];
			$slease['start'] = gmdate("M d Y H:i:s", time());
			$slease['end'] = gmdate("M d Y H:i:s", time());
			$slease['end'] = gmdate("M d Y H:i:s", strtotime('+5 minutes'));
			$slease['hostname'] = $static['descr'];
			$slease['act'] = "static";
			$online = exec("/usr/sbin/arp -an |/usr/bin/grep {$slease['mac']}| /usr/bin/wc -l|/usr/bin/awk '{print $1;}'");
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
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td class="listhdrr"><a href="?all=<?=$_GET['all'];?>&order=ip">IP address</a></td>
    <td class="listhdrr"><a href="?all=<?=$_GET['all'];?>&order=mac">MAC address</a></td>
    <td class="listhdrr"><a href="?all=<?=$_GET['all'];?>&order=hostname">Hostname</a></td>
    <td class="listhdrr"><a href="?all=<?=$_GET['all'];?>&order=start">Start</a></td>
    <td class="listhdrr"><a href="?all=<?=$_GET['all'];?>&order=end">End</a></td>
    <td class="listhdr"><a href="?all=<?=$_GET['all'];?>&order=online">Online</a></td>
    <td class="listhdr"><a href="?all=<?=$_GET['all'];?>&order=act">Lease Type</a></td>
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
                $lip = ip2long($data['ip']);
                foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
                        if (($lip >= ip2long($dhcpifconf['range']['from'])) && ($lip <= ip2long($dhcpifconf['range']['to']))) {
                                $data['if'] = $dhcpif;
                                break;
                        }
                }		
		echo "<tr>\n";
                echo "<td class=\"listlr\">{$fspans}{$data['ip']}{$fspane}&nbsp;</td>\n";
                if ($data['online'] != "online") {
                        echo "<td class=\"listr\">{$fspans}<a href=\"services_wol.php?if={$data['if']}&mac={$data['mac']}\" title=\"send Wake on Lan packet to mac\">{$data['mac']}</a>{$fspane}&nbsp;</td>\n";
                } else {
                	echo "<td class=\"listr\">{$fspans}{$data['mac']}{$fspane}&nbsp;</td>\n";
                }
                echo "<td class=\"listr\">{$fspans}{$data['hostname']}{$fspane}&nbsp;</td>\n";
                echo "<td class=\"listr\">{$fspans}" . adjust_gmt($data['start']) . "{$fspane}&nbsp;</td>\n";
                echo "<td class=\"listr\">{$fspans}" . adjust_gmt($data['end']) . "{$fspane}&nbsp;</td>\n";
                echo "<td class=\"listr\">{$fspans}{$data['online']}{$fspane}&nbsp;</td>\n";
                echo "<td class=\"listr\">{$fspans}{$data['act']}{$fspane}&nbsp;</td>\n";
		
		if ($data['type'] == "dynamic") {
                	echo "<td class=\"list\" valign=\"middle\"><a href=\"services_dhcp_edit.php?if={$data['if']}&mac={$data['mac']}&descr={$data['hostname']}\">";
			echo "<img src=\"/themes/{$g['theme']}/images/icons/icon_plus.gif\" width=\"17\" height=\"17\" border=\"0\" title=\"add a static mapping for this MAC address\"></a></td>\n";
		} else {
                	echo "<td class=\"list\" valign=\"middle\">";
			echo "<img src=\"/themes/{$g['theme']}/images/icons/icon_plus_mo.gif\" width=\"17\" height=\"17\" border=\"0\"</td>\n";
		}

                echo "<td valign=\"middle\"><a href=\"services_wol_edit.php?if={$data['if']}&mac={$data['mac']}&descr={$data['hostname']}\">";
		echo "<img src=\"/themes/{$g['theme']}/images/icons/icon_wol_all.gif\" width=\"17\" height=\"17\" border=\"0\" title=\"add a Wake on Lan mapping for this MAC address\"></a></td>\n";
                echo "</tr>\n";
	}
}
?>
</table>
<p>
<form action="diag_dhcp_leases.php" method="GET">
<input type="hidden" name="order" value="<?=$_GET['order'];?>">
<?php if ($_GET['all']): ?>
<input type="hidden" name="all" value="0">
<input type="submit" class="formbtn" value="Show active and static leases only">
<?php else: ?>
<input type="hidden" name="all" value="1">
<input type="submit" class="formbtn" value="Show all configured leases">
<?php endif; ?>
</form>
<?php else: ?>
<p><strong>No leases file found. Is the DHCP server active?</strong></p>
<?php endif; ?>

<meta http-equiv="refresh" content="120;url=<?php print $_SERVER['PHP_SELF']; ?>">

<?php include("fend.inc"); ?>
</body>
</html>
