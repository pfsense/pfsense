<?php
/*
	installer.php
	part of pfSense (http://www.pfsense.com/)
	Copyright (C) 2010 Scott Ullrich <sullrich@gmail.com>
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

if($g['platform'] == "pfSense" or $g['platform'] == "nanobsd") {
	Header("Location: /index.php");
	exit;
}

// Main switch dispatcher
switch ($_REQUEST['state']) {
	case "quickeasyinstall":
		quickeasyinstall_gui();
		break;
	case "update_installer_status":
		update_installer_status();
		exit;
	case "quickeasyinstall":
		begin_quick_easy_install();
	default:
		installer_main();	
}

function write_out_pc_sysinstaller_config($disk) {
	$fd = fopen("/PCBSD/pc-sysinstall/examples/pfSense-install.cfg", "w");
	if(!$fd) {
		return true;
	}
	$config = <<<EOF
# Sample configuration file for an installation using pc-sysinstall

installMode=fresh
installInteractive=yes
installType=FreeBSD
installMedium=LiveCD

# Set the disk parameters
disk0={$disk}
partition=all
bootManager=bsd
commitDiskPart

# Setup the disk label
# All sizes are expressed in MB
# Avail FS Types, UFS, UFS+S, UFS+J, ZFS, SWAP
# Size 0 means use the rest of the slice size
disk0-part=UFS+S 0 / 
# Do it now!
commitDiskLabel

# Set if we are installing via optical, USB, or FTP
installType=FreeBSD

packageType=cpdup

# Optional Components
cpdupPaths=boot,COPYRIGHT,bin,conf,conf.default,dev,etc,home,kernels,libexec,lib,root,sbin,sys,usr,var

runExtCommand=chmod a+rx /usr/local/bin/after_installation_routines.sh && cd / && /usr/local/bin/after_installation_routines.sh
EOF;
	fwrite($fd, $config);
	fclose($fd);
	return;
}

function start_installation() {
	$fd = fopen("/tmp/installer.sh", "w");
	if(!$fd) {
		die(gettext("Could not open /tmp/installer.sh for writing"));
		exit;
	}	
	fwrite($fd, "/PCBSD/pc-sysinstall/pc-sysinstall -c /PCBSD/pc-sysinstall/examples/pfSense-install.cfg && touch /tmp/install_complete");
	fclose($fd);
	exec("chmod a+rx /tmp/installer.sh");
	mwexec_bg("sh /tmp/installer.sh");
}

function installer_find_first_disk() {
	$disk = `/PCBSD/pc-sysinstall/pc-sysinstall disk-list | head -n1 | cut -d':' -f1`;
	return $disk;
}

function update_installer_status() {
	if(!file_exists("/tmp/.pc-sysinstall/pc-sysinstall.log")) 
		return;
	echo `tail -n20 /tmp/.pc-sysinstall/pc-sysinstall.log`;
	if(file_exists("/tmp/install_complete")) {
		echo gettext("Installation completed.");
		unlink_if_exists("/tmp/installer.sh");
	}
}

function update_installer_status_win($status) {
	echo "<script type=\"text/javascript\">\n";
	echo "\$('installeroutput').value = '" . str_replace(htmlentities($status), "\n", "") . "';\n";
	echo "installeroutput.scroll = installeroutput.maxScroll;\n";
	echo "</script>";
}

function begin_quick_easy_install() {
	unlink_if_exists("/tmp/install_complete");
	$disk = installer_find_first_disk();
	if(!$disk) {
		// XXX: hide progress bar
		$savemsg = gettext("Could not find a suitable disk for installation");
		update_installer_status_win(gettext("Could not find a suitable disk for installation."));
		return;
	}
	write_out_pc_sysinstaller_config($disk);
	update_installer_status_win(sprintf(gettext("Beginning installation on disk %s."),$disk));
	start_installation();
}

function body_html() {
	$pfSversion = str_replace("\n", "", file_get_contents("/etc/version"));
	if(strstr($pfSversion, "1.2"))
		$one_two = true;
	$pgtitle = gettext("pfSense: Installer");
	include("head.inc");
	echo <<<EOF
	<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<script src="/javascript/scriptaculous/prototype.js" type="text/javascript"></script>
		<script type="text/javascript">
			function getinstallerprogress() {
				url = 'installer.php';
				pars = 'state=update_installer_status';
				callajax(url, pars, installcallback);
			}
			function callajax(url, pars, activitycallback) {
				var myAjax = new Ajax.Request(
					url,
					{
						method: 'post',
						parameters: pars,
						onComplete: activitycallback
					});
			}
			function installcallback(transport) {
				this.document.forms[0].installeroutput.value=transport.responseText;
				setTimeout('getinstallerprogress()', 1000);		
			}
	</script>
EOF;
	include("fbegin.inc");

	if($one_two)
		echo "<p class=\"pgtitle\">{$pgtitle}</font></p>";

	if ($savemsg) print_info_box($savemsg); 
}

function end_html() {
	echo "</form>";
	include("fend.inc");
	echo "</body>";
	echo "</html>";
}

function template() {
	body_html();
	echo <<<EOF
	<div id="mainlevel">
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
	 		<tr>
	    		<td>
					<div id="mainarea">
						<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
							<tr>
	     						<td class="tabcont" >
	      							<form action="installer.php" method="post">
									<div id="pfsensetemplate">


									</div>
	     						</td>
							</tr>
						</table>
					</div>
				</td>
			</tr>
		</table>
	</div>
EOF;
	end_html();
}

function quickeasyinstall_gui() {
	body_html();
	echo <<<EOF
	<div id="mainlevel">
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
	 		<tr>
	    		<td>
					<div id="mainarea">
						<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
							<tr>
	     						<td class="tabcont" >
	      							<form action="installer.php" method="post" state="step1_post">
									<div id="pfsenseinstaller">
										<?=gettext("Starting Installer"); ?>...  <?=gettext("Please wait"); ?>...<p/>
										{{ Insert progressbar here }}<p/>
										<textarea name='installeroutput' id='installeroutput' rows="20" cols="80">
										</textarea>
									</div>
	     						</td>
							</tr>
						</table>
					</div>
				</td>
			</tr>
		</table>
	</div>
	<script type="text/javascript">setTimeout('getinstallerprogress()', 250);</script>
EOF;
	end_html();
}

function installer_main() {
	body_html();
	$disk = installer_find_first_disk();
	if(!$disk) 
		echo gettext("WARNING: Could not find any suitable disks for installation.");
	echo <<<EOF
	<div id="mainlevel">
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
	 		<tr>
	    		<td>
					<div id="mainarea">
						<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
							<tr>
	     						<td class="tabcont" >
	      							<form action="installer.php" method="post" state="step1_post">
									<div id="pfsenseinstaller">
										<a onclick="return confirm('<?=gettext("Are you sure you want to install pfSense to $disk"); ?>?')"> href='installer.php?state=quickeasyinstall'><?=gettext("Quick/Easy installation"); ?></a> 
										</p>
									</div>
	     						</td>
							</tr>
						</table>
					</div>
				</td>
			</tr>
		</table>
	</div>
EOF;
	end_html();
}

?>
