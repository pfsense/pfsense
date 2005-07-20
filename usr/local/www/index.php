#!/usr/local/bin/php
<?php
/* $Id$ */
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
    oR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

require_once("guiconfig.inc");
require_once("notices.inc");

$swapinfo = `/usr/sbin/swapinfo`;
if(stristr($swapinfo,"%") == true) $showswap=true;

/* User recently restored his config.
   If packages are installed lets resync
*/
if(file_exists("/needs_package_sync")) {
	if($config['installedpackages'] <> "") {
		conf_mount_rw();
		unlink("/needs_package_sync");
		header("Location: pkg_mgr_install.php?mode=reinstallall");
		exit;
	}
}

if(file_exists("/trigger_initial_wizard")) {
	conf_mount_rw();
	unlink("/trigger_initial_wizard");
	conf_mount_ro();

$pgtitle = "pfSense first time setup";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<form>
<?php
	echo "<center>";
	echo "<a href=\"/\"><img src=\"/logo.gif\" border=\"0\"></a><p>";
	echo "Welcome to pfSense!<p>";
	echo "One moment while we start the initial setup wizard.<p>";
	echo "Embedded platform users: Please be patient, the wizard takes a little longer to run than the normal gui.<p>";
	echo "To bypass the wizard, click on the pfSense wizard on the initial page.";
	echo "<meta http-equiv=\"refresh\" content=\"1;url=wizard.php?xml=setup_wizard.xml\">";
	exit;
}

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

function get_pfstate() {
	global $config;
        if (isset($config['system']['maximumstates']) and $config['system']['maximumstates'] > 0)
                $maxstates="/{$config['system']['maximumstates']}";
        else
                $maxstates="/10000";
        $curentries = `/sbin/pfctl -si |grep current`;
        if (preg_match("/([0-9]+)/", $curentries, $matches)) {
             $curentries = $matches[1];
        }
        return $curentries . $maxstates;
}

$pgtitle = "pfSense webGUI";
/* include header and other code */
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<form>
<?php

include("fbegin.inc");
	if(!file_exists("/usr/local/www/themes/{$g['theme']}/no_big_logo"))
		echo "<center><img src=\"logobig.jpg\"></center><br>";
?>
<p class="pgtitle">System Overview</p>

	    <div id="niftyOutter" width="650">
            <table bgcolor="#990000" width="100%" border="0" cellspacing="0" cellpadding="0">
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
                  <?=htmlspecialchars(date("D M j G:i:s T Y", $config['revision']['time']));?>
                </td>
              </tr><?php endif; ?>
              <tr>
                <td width="25%" class="vncellt">State table size</td>
                <td width="75%" class="listr">
                  <?php
                    echo "<input style='border: 0px solid white;' size='30' name='pfstate' id='pfstate' value='"  .htmlspecialchars(get_pfstate()) . "'>";
                  ?>
		    <br>
		    <a href="diag_dump_states.php">Show states</a>
                </td>
              </tr>
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
	      
<?php if($showswap == true): ?>
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
<?php endif; ?>

<?php
	/* XXX - Stub in the HW monitor for net4801 - needs to use platform var's once we start using them */
	$is4801 = `/sbin/dmesg -a | grep NET4801`;
	if($is4801 <> "") {
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
	<tr>
		<td width="25%" class="vncellt">Disk usage</td>
			<td width="75%" class="listr">
<?php
exec("df -h | grep -w '/' | awk '{ print $5 }' | cut -d '%' -f 1", $dfout);
$diskusage = trim($dfout[0]);

echo "<img src='bar_left.gif' height='15' width='4' border='0' align='absmiddle'>";
echo "<img src='bar_blue.gif' height='15' width='" . $diskusage . "' border='0' align='absmiddle'>";
echo "<img src='bar_gray.gif' height='15' width='" . (100 - $diskusage) . "' border='0' align='absmiddle'>";
echo "<img src='bar_right.gif' height='15' width='5' border='0' align='absmiddle'> ";
echo $diskusage . "%";
?>
			</td>
	</tr>


            </table>
	    </div>
            <?php include("fend.inc"); ?>
	    
<script type="text/javascript">
NiftyCheck();
Rounded("div#nifty","top","#FFF","#EEEEEE","smooth");
</script>
</form>

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
    echo "document.forms[0].pfstate.value = '" . get_pfstate() . "';\n";

    echo "document.cpuwidtha.style.width='" . $cpuUsage . "px';\n";
    echo "document.cpuwidthb.style.width='" . (100 - $cpuUsage) . "px';\n";
    echo "document.forms[0].cpumeter.value = '" . $cpuUsage . "%';\n";

    echo "document.memwidtha.style.width='" . $memUsage . "px';\n";
    echo "document.memwidthb.style.width='" . (100 - $memUsage) . "px';\n";
    echo "document.forms[0].memusagemeter.value = '" . $memUsage . "%';\n";

    if (file_exists("/etc/48xx")) {
      /* Update temp. meter */
      $Temp = rtrim(`/usr/local/sbin/env4801 | grep Temp |cut -c24-25`);
      echo "document.Tempwidtha.style.width='" . $Temp . "px';\n";
      echo "document.Tempwidthb.style.width='" . (100 - $Temp) . "px';\n";
      echo "document.forms[0].Tempmeter.value = '" . $Temp . "C';\n";
    }

/*
    exec("df -h | grep -w '/' | awk '{ print $5 }' | cut -d '%' -f 1", $dfout);
    $diskusage = trim($dfout[0]);

    echo "document.Diskwidtha.style.width='" . $diskusage . "px';\n";
    echo "document.Diskwidthb.style.width='" . (100 - $diskusage) . "px';\n";
    echo "document.forms[0].Diskmeter.value = '" . $diskusage . "%';\n";
*/

    echo "</script>\n";

     if(are_notices_pending() == true and $found_notices == false) {
	/* found a notice, lets redirect so they can see the notice */
	$counter = 500;
     }
     
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
