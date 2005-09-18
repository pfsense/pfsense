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

	## Load Essential Includes
	require_once('guiconfig.inc');
	require_once('notices.inc');


	## Load Functions Files
	require_once('includes/functions.inc.php');


	## Load AJAX, Initiate Class ###############################################
	require_once('includes/sajax.class.php');

	## Initiate Class and Set location of ajax file containing 
	## the information that we need for this page. Also set functions
	## that SAJAX will be using.
	$oSajax = new sajax();
	$oSajax->sajax_remote_uri = 'sajax/index.sajax.php';
	$oSajax->sajax_request_type = 'POST';
	$oSajax->sajax_export("mem_usage","cpu_usage","get_uptime","get_pfstate", "get_temp");
	$oSajax->sajax_handle_client_request();
	############################################################################


	## Check to see if we have a swap space,
	## if true, display, if false, hide it ...
	$swapinfo = `/usr/sbin/swapinfo`;
	if(stristr($swapinfo,'%') == true) $showswap=true;


	## User recently restored his config.
	## If packages are installed lets resync
	if(file_exists('/needs_package_sync')) {
		if($config['installedpackages'] <> '') {
			conf_mount_rw();
			unlink('/needs_package_sync');
			header('Location: pkg_mgr_install.php?mode=reinstallall');
			exit;
		}
	}


	## If it is the first time webGUI has been
	## accessed since initial install show this stuff.
	if(file_exists('/trigger_initial_wizard')) {
		conf_mount_rw();
		unlink('/trigger_initial_wizard');
		conf_mount_ro();

		$pgtitle = 'pfSense first time setup';
		include('head.inc');

		echo "<body link=\"#0000CC\" vlink=\"#0000CC\" alink=\"#0000CC\">\n";
		echo "<form>\n";
		echo "<center>\n";
		echo "<img src=\"/themes/{$g['theme']}/images/logo.gif\" border=\"0\"><p>\n";
		echo "<div \" style=\"width:700px;background-color:#ffffff\" id=\"nifty\">\n";
		echo "Welcome to pfSense!<p>\n";
		echo "One moment while we start the initial setup wizard.<p>\n";
		echo "Embedded platform users: Please be patient, the wizard takes a little longer to run than the normal gui.<p>\n";
		echo "To bypass the wizard, click on the pfSense wizard on the initial page.\n";
		echo "</div>\n";
		echo "<meta http-equiv=\"refresh\" content=\"1;url=wizard.php?xml=setup_wizard.xml\">\n";
		echo "<script type=\"text/javascript\">\n";
		echo "NiftyCheck();\n";
		echo "Rounded(\"div#nifty\",\"all\",\"#000\",\"#FFFFFF\",\"smooth\");\n";
		echo "</script>\n";
		exit;
	}


	## Find out whether there's hardware encryption or not
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


	## Set Page Title and Include Header
	$pgtitle = "pfSense webGUI";
	include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php
include("fbegin.inc");
	if(!file_exists("/usr/local/www/themes/{$g['theme']}/no_big_logo"))
		echo "<center><img src=\"./themes/".$g['theme']."/images/logobig.jpg\"></center><br>";
?>
<p class="pgtitle">System Overview</p>

<div id="niftyOutter">
<form action="index.php" method="post">
<table bgcolor="#990000" width="100%" border="0" cellspacing="0" cellpadding="0">
	<tbody>
		<tr>
			<td colspan="2" class="listtopic">System information</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt">Name</td>
			<td width="75%" class="listr"><?php echo $config['system']['hostname'] . "." . $config['system']['domain']; ?></td>
		</tr>
		<tr>
			<td width="25%" valign="top" class="vncellt">Version</td>
			<td width="75%" class="listr">
				<strong><?php readfile("/etc/version"); ?></strong>
				<br />
				built on <?php readfile("/etc/version.buildtime"); ?>
			</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt">Platform</td>
			<td width="75%" class="listr"><?=htmlspecialchars($g['platform']);?></td>
		</tr>
		<?php if ($hwcrypto): ?>
		<tr>
			<td width="25%" class="vncellt">Hardware crypto</td>
			<td width="75%" class="listr"><?=htmlspecialchars($hwcrypto);?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt">Uptime</td>
			<td width="75%" class="listr"><input style="border: 0px solid white;" size="30" name="uptime" id="uptime" value="<?= htmlspecialchars(get_uptime()); ?>" /></td>
		</tr>
		<?php if ($config['lastchange']): ?>
		<tr>
			<td width="25%" class="vncellt">Last config change</td>
			<td width="75%" class="listr"><?= htmlspecialchars(date("D M j G:i:s T Y", $config['revision']['time']));?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt">State table size</td>
			<td width="75%" class="listr">
				<input style="border: 0px solid white;" size="30" name="pfstate" id="pfstate" value="<?= htmlspecialchars(get_pfstate()); ?>" />
		    	<br />
		    	<a href="diag_dump_states.php">Show states</a>
			</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt">CPU usage</td>
			<td width="75%" class="listr">
				<?php $cpuUsage = "0"; ?>
				<img src="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_blue.gif" height="15" name="cpuwidtha" id="cpuwidtha" width="<?= $cpuUsage; ?>" border="0" align="middle" alt="blue bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif" height="15" name="cpuwidthb" id="cpuwidthb" width="<?= (100 - $cpuUsage); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
				&nbsp;
				<input style="border: 0px solid white;" size="30" name="cpumeter" id="cpumeter" value="(Updating in 5 seconds)" />
			</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt">Memory usage</td>
			<td width="75%" class="listr">
				<?php $memUsage = mem_usage(); ?>
				<img src="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_blue.gif" height="15" name="memwidtha" id="memwidtha" width="<?= $memUsage; ?>" border="0" align="middle" alt="blue bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif" height="15" name="memwidthb" id="memwidthb" width="<?= (100 - $memUsage); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
				&nbsp;
				<input style="border: 0px solid white;" size="30" name="memusagemeter" id="memusagemeter" value="<?= $memUsage.'%'; ?>" />
			</td>
		</tr>
		<?php if($showswap == true): ?>
		<tr>
			<td width="25%" class="vncellt">SWAP usage</td>
			<td width="75%" class="listr">
				<?php $swapusage = swap_usage(); ?>
				<img src="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_blue.gif" height="15" width="<?= $swapUsage; ?>" border="0" align="middle" alt="blue bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif" height="15" width="<?= (100 - $swapUsage); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
				&nbsp;
				<input style="border: 0px solid white;" size="30" name="swapusagemeter" id="swapusagemeter" value="<?= $swapusage.'%'; ?>" />
			</td>
		</tr>
		<?php endif; ?>
<?php
		if(has_temp()):
?>
		<tr>
			<td width='25%' class='vncellt'>Temperature</td>
			<td width='75%' class='listr'>
				<?php $temp = get_temp(); ?>
				<img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_blue.gif" height="15" name="tempwidtha" id="tempwidtha" width="<?= $temp; ?>" border="0" align="middle" alt="blue bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_gray.gif" height="15" name="tempwidthb" id="tempwidthb" width="<?= (100 - $temp); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
				&nbsp;
				<input style="border: 0px solid white;" size="30" name="tempmeter" id="tempmeter" value="<?= $temp."C"; ?>" />
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt">Disk usage</td>
			<td width="75%" class="listr">
				<?php $diskusage = disk_usage(); ?>
				<img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_blue.gif" height="15" width="<?= $diskusage; ?>" border="0" align="middle" alt="blue bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_gray.gif" height="15" width="<?= (100 - $diskusage); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
				&nbsp;
				<input style="border: 0px solid white;" size="30" name="diskusagemeter" id="diskusagemeter" value="<?= $diskusage.'%'; ?>" />
			</td>
		</tr>
	</tbody>
</table>
</form>
</div>

<?php include("fend.inc"); ?>
	    
<script type="text/javascript">
	NiftyCheck();
	Rounded("div#nifty","top","#FFF","#EEEEEE","smooth");
</script>


</body>
</html>
