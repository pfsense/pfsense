#!/usr/local/bin/php
<?php
/*
    index.php
    Copyright (C) 2004, 2005 Scott Ullrich
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

if(file_exists("/usr/local/www/trigger_initial_wizard")) {
	unlink("/usr/local/www/trigger_initial_wizard");
	header("Location:  wizard.php?xml=setup_wizard.xml");
}

require("guiconfig.inc");

/* find out whether there's hardware encryption (hifn) */
unset($hwcrypto);
$fd = @fopen("{$g['varlog_path']}/dmesg.boot", "r");
if ($fd) {
	while (!feof($fd)) {
		$dmesgl = fgets($fd);
		if (preg_match("/^hifn.: (.*?),/", $dmesgl, $matches)) {
			$hwcrypto = $matches[1];
			break;
		}
	}
	fclose($fd);
}

function get_uptime() {
	exec("/sbin/sysctl -n kern.boottime", $boottime);
	preg_match("/sec = (\d+)/", $boottime[0], $matches);
	$boottime = $matches[1];
	$uptime = time() - $boottime;

	if ($uptime > 60)
		$uptime += 30;
	$updays = (int)($uptime / 86400);
	$uptime %= 86400;
	$uphours = (int)($uptime / 3600);
	$uptime %= 3600;
	$upmins = (int)($uptime / 60);

	$uptimestr = "";
	if ($updays > 1)
		$uptimestr .= "$updays days, ";
	else if ($updays > 0)
		$uptimestr .= "1 day, ";
	$uptimestr .= sprintf("%02d:%02d", $uphours, $upmins);
	return $uptimestr;
}

function get_cputicks() {
	$cputicks = explode(" ", `/sbin/sysctl -n kern.cp_time`);
	return $cputicks;
}

function get_cpuusage($cpuTicks, $cpuTicks2) {

$diff = array();
$diff['user'] = ($cpuTicks2[0] - $cpuTicks[0])+1;
$diff['nice'] = ($cpuTicks2[1] - $cpuTicks[1])+1;
$diff['sys'] = ($cpuTicks2[2] - $cpuTicks[2])+1;
$diff['intr'] = ($cpuTicks2[3] - $cpuTicks[3])+1;
$diff['idle'] = ($cpuTicks2[4] - $cpuTicks[4])+1;

//echo "<!-- user: {$diff['user']}  nice {$diff['nice']}  sys {$diff['sys']}  intr {$diff['intr']}  idle {$diff['idle']} -->";

$totalDiff = $diff['user'] + $diff['nice'] + $diff['sys'] + $diff['intr'] + $diff['idle'];
$cpuUsage = round(100 * (1 - $diff['idle'] / $totalDiff), 0);

return $cpuUsage;
}


?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("pfSense webGUI");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<form>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
              <tr align="center" valign="top">
                <td height="10" colspan="2">&nbsp;</td>
              </tr>
              <tr align="center" valign="top">
                <td colspan="2"><img src="logobig.jpg"></td>
              </tr>
	      <tr><td>&nbsp;</td></tr>
              <tr>
                <td colspan="2" class="listtopic">System information</td>
              </tr>
              <tr>
                <td width="25%" class="vncellt">Name</td>
                <td width="75%" class="listr">
                  <?php echo $config['system']['hostname'] . "." . $config['system']['domain']; ?>
                </td>
              </tr>
              <tr>
                <td width="25%" valign="top" class="vncellt">Version</td>
                <td width="75%" class="listr"> <strong>
                  <?php readfile("/etc/version"); ?>
                  </strong><br>
                  built on
                  <?php readfile("/etc/version.buildtime"); ?>
                </td>
              </tr>
              <tr>
                <td width="25%" class="vncellt">Platform</td>
                <td width="75%" class="listr">
                  <?=htmlspecialchars($g['platform']);?>
                </td>
              </tr><?php if ($hwcrypto): ?>
              <tr>
                <td width="25%" class="vncellt">Hardware crypto</td>
                <td width="75%" class="listr">
                  <?=htmlspecialchars($hwcrypto);?>
                </td>
              </tr><?php endif; ?>
              <tr>
                <td width="25%" class="vncellt">Uptime</td>
                <td width="75%" class="listr">
                  <?php
                    echo "<input style='border: 0px solid white;' size='30' name='uptime' id='uptime' value='"  .htmlspecialchars(get_uptime()) . "'>";
				  ?>
                </td>
              </tr><?php if ($config['lastchange']): ?>
              <tr>
                <td width="25%" class="vncellt">Last config change</td>
                <td width="75%" class="listr">
                  <?=htmlspecialchars(date("D M j G:i:s T Y", $config['lastchange']));?>
                </td>
              </tr><?php endif; ?>
			  <tr>
                <td width="25%" class="vncellt">CPU usage</td>
                <td width="75%" class="listr">
<?php
$cpuUsage = get_cpuusage(get_cputicks(), get_cputicks());

echo "<img src='bar_left.gif' height='15' width='4' border='0' align='absmiddle'>";
echo "<img src='bar_blue.gif' height='15' name='cpuwidtha' id='cpuwidtha' width='" . $cpuUsage . "' border='0' align='absmiddle'>";
echo "<img src='bar_gray.gif' height='15' name='cpuwidthb' id='cpuwidthb' width='" . (100 - $cpuUsage) . "' border='0' align='absmiddle'>";
echo "<img src='bar_right.gif' height='15' width='5' border='0' align='absmiddle'> ";
echo "<input style='border: 0px solid white;' size='30' name='cpumeter' id='cpumeter' value='{$cpuUsage}% (Updating in 3 seconds)'>";
//echo $cpuUsage . "%";
?>
                </td>
              </tr>
			  <tr>
                <td width="25%" class="vncellt">Memory usage</td>
                <td width="75%" class="listr">
<?php

exec("/sbin/sysctl -n vm.stats.vm.v_active_count vm.stats.vm.v_inactive_count " .
	"vm.stats.vm.v_wire_count vm.stats.vm.v_cache_count vm.stats.vm.v_free_count", $memory);

$totalMem = $memory[0] + $memory[1] + $memory[2] + $memory[3] + $memory[4];
$freeMem = $memory[4];
$usedMem = $totalMem - $freeMem;
$memUsage = round(($usedMem * 100) / $totalMem, 0);

echo " <img src='bar_left.gif' height='15' width='4' border='0' align='absmiddle'>";
echo "<img src='bar_blue.gif' height='15' name='memwidtha' id='memwidtha' width='" . $memUsage . "' border='0' align='absmiddle'>";
echo "<img src='bar_gray.gif' height='15' name='memwidthb' id='memwidthb' width='" . (100 - $memUsage) . "' border='0' align='absmiddle'>";
echo "<img src='bar_right.gif' height='15' width='5' border='0' align='absmiddle'> ";
echo "<input style='border: 0px solid white;' size='30' name='memusagemeter' id='memusagemeter' value='{$memUsage}%'>";
//echo $memUsage . "%";
?>
                </td>
              </tr>
			  <tr>
                <td width="25%" class="vncellt">SWAP usage</td>
                <td width="75%" class="listr">

<?php

$swapUsage = `/usr/sbin/swapinfo | cut -c45-55 | grep "%"`;
$swapUsage = ereg_replace('%', "", $swapUsage);
$swapUsage = ereg_replace(' ', "", $swapUsage);
$swapUsage = rtrim($swapUsage);

echo "<img src='bar_left.gif' height='15' width='4' border='0' align='absmiddle'>";
echo "<img src='bar_blue.gif' height='15' width='" . $swapUsage . "' border='0' align='absmiddle'>";
echo "<img src='bar_gray.gif' height='15' width='" . (100 - $swapUsage) . "' border='0' align='absmiddle'>";
echo "<img src='bar_right.gif' height='15' width='5' border='0' align='absmiddle'> ";
echo "<input style='border: 0px solid white;' size='30' name='swapusagemeter' id='swapusagemeter' value='{$swapUsage}%'>";
//echo $swapUsage . "%";

?>
                </td>
              </tr>
<?php
	/* XXX - Stub in the HW monitor for net4801 - needs to use platform var's once we start using them */
	if (file_exists("/etc/48xx")) {
echo "			  <tr>";
echo "                <td width='25%' class='vncellt'>Temperature </td>";
echo "                <td width='75%' class='listr'>";
// Initialize hw monitor
exec("/usr/local/sbin/env4801 -i");
$Temp = rtrim(`/usr/local/sbin/env4801 | grep Temp |cut -c24-25`);
echo "<img src='bar_left.gif' height='15' width='4' border='0' align='absmiddle'>";
echo "<img src='bar_blue.gif' height='15' name='Tempwidtha' id='tempwidtha' width='" . $Temp . "' border='0' align='absmiddle'>";
echo "<img src='bar_gray.gif' height='15' name='Tempwidthb' id='tempwidthb' width='" . (100 - $Temp) . "' border='0' align='absmiddle'>";
echo "<img src='bar_right.gif' height='15' width='5' border='0' align='absmiddle'> ";
echo "<input style='border: 0px solid white;' size='30' name='Tempmeter' id='Tempmeter' value='{$Temp}C'>";
echo "                </td>";
echo "              </tr>";
	}
?>


            </table>
            <?php include("fend.inc"); ?>
</body>
</html>
<?php

$counter = 0;

While(!Connection_Aborted()) {

    /* Update CPU meter */
    sleep(1);
    $cpuTicks = get_cputicks();
    sleep(2);
    $cpuTicks2 = get_cputicks();
    $cpuUsage = get_cpuusage($cpuTicks, $cpuTicks2);

    /* Update memory usage */
    exec("/sbin/sysctl -n vm.stats.vm.v_active_count vm.stats.vm.v_inactive_count " .
        "vm.stats.vm.v_wire_count vm.stats.vm.v_cache_count vm.stats.vm.v_free_count", $memory);

    $totalMem = $memory[0] + $memory[1] + $memory[2] + $memory[3] + $memory[4];
    $freeMem = $memory[4];
    $usedMem = $totalMem - $freeMem;
    $memUsage = round(($usedMem * 100) / $totalMem, 0);

    echo "<script language='javascript'>\n";
    echo "document.forms[0].uptime.value = '" . get_uptime() . "';\n";
    
    echo "document.cpuwidtha.style.width='" . $cpuUsage . "';\n";
    echo "document.cpuwidthb.style.width='" . (100 - $cpuUsage) . "';\n";
    echo "document.forms[0].cpumeter.value = '" . $cpuUsage . "%';\n";

    echo "document.memwidtha.style.width='" . $memUsage . "';\n";
    echo "document.memwidthb.style.width='" . (100 - $memUsage) . "';\n";
    echo "document.forms[0].memusagemeter.value = '" . $memUsage . "%';\n";

    if (file_exists("/etc/48xx")) {
      /* Update temp. meter */
      $Temp = rtrim(`/usr/local/sbin/env4801 | grep Temp |cut -c24-25`);
      echo "document.Tempwidtha.style.width='" . $Temp . "';\n";
      echo "document.Tempwidthb.style.width='" . (100 - $Temp) . "';\n";
      echo "document.forms[0].Tempmeter.value = '" . $Temp . "C';\n";
    }

    echo "</script>\n";

    /*
     *   prevent user from running out of ram.
     *   firefox and ie can be a bear on ram usage!
     */
    $counter++;
    if($counter > 120) {
	    echo "Redirecting to <a href=\"index.php\">Main Status</a>.<p>";
	    echo "<meta http-equiv=\"refresh\" content=\"1;url=index.php\">";
	    exit;
    }

}

?>

