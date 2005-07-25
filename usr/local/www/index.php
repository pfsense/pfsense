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


$pgtitle = "pfSense webGUI";
/* include header and other code */
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<form>
<?php
include("fbegin.inc");
?>
<p class="pgtitle">System Overview</p>
<?
	if(!file_exists("/usr/local/www/themes/{$g['theme']}/no_big_logo"))
		echo "<center><img src=\"./themes/".$g['theme']."/images/logobig.jpg\"></center><br>";
?>

<div id="niftyOutter" width="650">
	<iframe src="index_sub.php" height="350px" width="770px"></iframe>
</div>

<?php include("fend.inc"); ?>
	    
<script type="text/javascript">
NiftyCheck();
Rounded("div#nifty","top","#FFF","#EEEEEE","smooth");
</script>
</form>

</body>
</html>