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

require("globals.inc");
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
	global $g;
	$ps_running = exec("ps awwwux | grep -v grep | grep 'sh /tmp/installer.sh'");
	if($ps_running)	
		return;
	$fd = fopen("/tmp/installer.sh", "w");
	if(!$fd) {
		die("Could not open /tmp/installer.sh for writing");
		exit;
	}	
	fwrite($fd, "/PCBSD/pc-sysinstall/pc-sysinstall -c /PCBSD/pc-sysinstall/examples/pfSense-install.cfg && touch /tmp/install_complete");
	fclose($fd);
	exec("chmod a+rx /tmp/installer.sh");
	mwexec_bg("sh /tmp/installer.sh");
}

function installer_find_first_disk() {
	global $g;
	$disk = `/PCBSD/pc-sysinstall/pc-sysinstall disk-list | head -n1 | cut -d':' -f1`;
	return $disk;
}

function update_installer_status() {
	global $g;
	if(!file_exists("/tmp/.pc-sysinstall/pc-sysinstall.log")) 
		return;
	// Ensure status files exist
	if(!file_exists("/tmp/installer_installer_running"))
		touch("/tmp/installer_installer_running");
	$status = `tail -n30 /tmp/.pc-sysinstall/pc-sysinstall.log`;
	$status = str_replace("\n", "\\n", $status);
	$status = str_replace("\n", "\\r", $status);
	echo "this.document.forms[0].installeroutput.value='$status';\n";
	// Find out installer progress
	$progress = "5";
	if(strstr($status, "Running: dd")) 
		$progress = "6";
	if(strstr($status, "Running: gpart create -s GPT")) 
		$progress = "7";
	if(strstr($status, "Running: gpart bootcode")) 
		$progress = "7";
	if(strstr($status, "Running: newfs -U")) 
		$progress = "8";
	if(strstr($status, "Running: sync")) 
		$progress = "9";
	if(strstr($status, "/boot /mnt/boot")) 
		$progress = "10";
	if(strstr($status, "/COPYRIGHT /mnt/COPYRIGHT"))
		$progress = "20";
	if(strstr($status, "/bin /mnt/bin"))
		$progress = "25";
	if(strstr($status, "/conf /mnt/conf"))
		$progress = "30";
	if(strstr($status, "/conf.default /mnt/conf.default"))
		$progress = "35";
	if(strstr($status, "/dev /mnt/dev"))
		$progress = "40";
	if(strstr($status, "/etc /mnt/etc"))
		$progress = "45";
	if(strstr($status, "/home /mnt/home"))
		$progress = "50";
	if(strstr($status, "/kernels /mnt/kernels"))
		$progress = "55";
	if(strstr($status, "/libexec /mnt/libexec"))
		$progress = "60";
	if(strstr($status, "/lib /mnt/lib"))
		$progress = "65";
	if(strstr($status, "/root /mnt/root"))
		$progress = "70";
	if(strstr($status, "/sbin /mnt/sbin"))
		$progress = "75";
	if(strstr($status, "/sys /mnt/sys"))
		$progress = "80";
	if(strstr($status, "/usr /mnt/usr"))
		$progress = "95";
	if(strstr($status, "/usr /mnt/usr"))
		$progress = "90";
	if(strstr($status, "/var /mnt/var"))
		$progress = "95";
	if(strstr($status, "cap_mkdb /etc/login.conf"))
		$progress = "96";
	if(strstr($status, "Setting hostname"))
		$progress = "97";
	if(strstr($status, "umount -f /mnt"))
		$progress = "98";
	if(strstr($status, "umount -f /mnt"))
		$progress = "99";
	if(strstr($status, "Installation finished"))
		$progress = "100";
	$running_old = trim(file_get_contents("/tmp/installer_installer_running"));
	if($installer_running <> "running") {
		$ps_running = exec("ps awwwux | grep -v grep | grep 'sh /tmp/installer.sh'");
		if($ps_running)	{
			$running = "\$('installerrunning').innerHTML='<table><tr><td valign=\"middle\"><img src=\"/themes/{$g['theme']}/images/misc/loader.gif\"></td><td valign=\"middle\">&nbsp;<font size=\"2\"><b>Installer running ({$progress}% completed)...</td></tr></table>'; ";
			if($running_old <> $running) {
				echo $running;
				file_put_contents("/tmp/installer_installer_running", "$running");			
			}
		}
	}
	if($progress) 
		echo "\$('progressbar').style.width='{$progress}%';\n";
	if($progress == "100") {
		echo "\$('installerrunning').innerHTML='Installation completed.  Please <a href=\"reboot.php\">reboot</a> to continue';\n";
		unlink_if_exists("/tmp/installer.sh");
		file_put_contents("/tmp/installer_installer_running", "finished");
	}
}

function update_installer_status_win($status) {
	global $g;
	echo "<script type=\"text/javascript\">\n";
	echo "	\$('installeroutput').value = '" . str_replace(htmlentities($status), "\n", "") . "';\n";
	echo "</script>";
}

function begin_quick_easy_install() {
	global $g;
	unlink_if_exists("/tmp/install_complete");
	$disk = installer_find_first_disk();
	if(!$disk) {
		// XXX: hide progress bar
		$savemsg = "Could not find a suitable disk for installation";
		update_installer_status_win("Could not find a suitable disk for installation.");
		return;
	}
	write_out_pc_sysinstaller_config($disk);
	update_installer_status_win("Beginning installation on disk {$disk}.");
	start_installation();
}

function body_html() {
	global $g;
	$pfSversion = str_replace("\n", "", file_get_contents("/etc/version"));
	if(strstr($pfSversion, "1.2"))
		$one_two = true;
	$pgtitle = "pfSense: Installer";
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
			setTimeout('getinstallerprogress()', 2000);
			eval(transport.responseText);
		}
	</script>
EOF;

	if($one_two)
		echo "<p class=\"pgtitle\">{$pgtitle}</font></p>";

	if ($savemsg) print_info_box($savemsg); 
}

function end_html() {
	global $g;
	echo "</form>";
	echo "</body>";
	echo "</html>";
}

function template() {
	global $g;
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
	global $g;
	body_html();
	echo "<form action=\"installer.php\" method=\"post\" state=\"step1_post\">";
	page_table_start();
	echo <<<EOF
	<center>
		<table width="100%">
		<tr><td>
			<div id="mainlevel">
				<table width="100%" border="0" cellpadding="0" cellspacing="0">
			 		<tr>
			    		<td>
							<div id="mainarea">
								<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
									<tr>
			     						<td class="tabcont" >
											<div id="pfsenseinstaller" width="100%">
												<div id='installerrunning' width='100%' style="padding:1em; border:1px dashed #000000">
													<table>
														<tr>
															<td valign="middle">
																<img src="/themes/{$g['theme']}/images/misc/loader.gif">
															</td>
															<td valign="middle">
																&nbsp;<font size="2"><b>Starting Installer...  Please wait...
															</td>
														</tr>
													</table>
												</div>
												<br/>
												<table height='15' width='640' border='0' colspacing='0' cellpadding='0' cellspacing='0'>
													<tr>
														<td background="./themes/the_wall/images/misc/bar_left.gif" height='15' width='5'>
														</td>
														<td>
															<table id="progholder" name="progholder" height='15' width='630' border='0' colspacing='0' cellpadding='0' cellspacing='0'>
																<td background="./themes/the_wall/images/misc/bar_gray.gif" valign="top" align="left">
																	<img src='./themes/the_wall/images/misc/bar_blue.gif' width='0' height='15' name='progressbar' id='progressbar'>
																</td>
															</table>
														</td>
														<td background="./themes/the_wall/images/misc/bar_right.gif" height='15' width='5'>
														</td>
													</tr>
												</table>
												<br/>
												<textarea name='installeroutput' id='installeroutput' rows="31" cols="80">
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
		</td></tr>
		</table>
	</center>
	<script type="text/javascript">setTimeout('getinstallerprogress()', 250);</script>

EOF;
	page_table_end();
	end_html();
	begin_quick_easy_install();
}

function page_table_start() {
	global $g;
		echo <<<EOF
	<center>
		<img border="0" src="./themes/{$g['theme']}/images/logo.gif"></a>
		<table cellpadding="5" cellspacing="0" width="640" height="480" style="border:1px solid #000000">
		<tr height="10" bgcolor="#990000">
			<td style="border-bottom:1px solid #000000">
				<font color='white'>
					<b>
						pfSense installer
					</b>
				</font>
			</td>
		</tr>
		<tr>
			<td>

EOF;

}

function page_table_end() {
	global $g;
	echo <<<EOF
			</td>
		</tr>
		</table>
	</center>

EOF;
	
}

function installer_main() {
	global $g;
	if(file_exists("/tmp/.pc-sysinstall/pc-sysinstall.log")) 
		unlink("/tmp/.pc-sysinstall/pc-sysinstall.log");
	body_html();
	$disk = installer_find_first_disk();
	if(!$disk) 
		echo "WARNING: Could not find any suitable disks for installation.";
	page_table_start();
	echo <<<EOF
		<form action="installer.php" method="post" state="step1_post">
			<div id="mainlevel">
				<center>
				<b><font face="arial" size="+2">Welcome to the pfSense PCSysInstaller!</b></font><p/>
				<font face="arial" size="+1">This utility will install pfSense to a hard disk, flash drive, etc.</font>
				<table width="100%" border="0" cellpadding="5" cellspacing="0">
			 		<tr>
			    		<td>
							<center>
							<div id="mainarea">
								<br/>
								<center>
								Please select an installer option to begin:
								<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
									<tr>
			     						<td class="tabcont" >
											<div id="pfsenseinstaller">
												<center>
												<a href="installer.php?state=quickeasyinstall" onClick="return confirm('Are you sure you want to install pfSense to $disk?')">Quick/Easy installation</a> 
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
	page_table_end();
	end_html();
}

?>