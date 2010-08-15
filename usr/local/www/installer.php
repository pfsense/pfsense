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

define('PC_SYSINSTALL', '/usr/sbin/pc-sysinstall/pc-sysinstall');

if($g['platform'] == "pfSense" or $g['platform'] == "nanobsd") {
	Header("Location: /index.php");
	exit;
}

// Main switch dispatcher
switch ($_REQUEST['state']) {
	case "update_installer_status":
		update_installer_status();
		exit;
	case "custominstall":
		installer_custom();
		exit;
	case "begin_install":
		installing_gui();
		begin_install();
		exit;
	case "verify_before_install":
		verify_before_install();
		exit;
	default:
		installer_main();	
}

function write_out_pc_sysinstaller_config($disk, $fstype = "UFS+S") {
	$fd = fopen("/usr/sbin/pc-sysinstall/examples/pfSense-install.cfg", "w");
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
disk0-part={$fstype} 0 / 
# Do it now!
commitDiskLabel

# Set if we are installing via optical, USB, or FTP
installType=FreeBSD

packageType=cpdup

# Optional Components
cpdupPaths=boot,COPYRIGHT,bin,conf,conf.default,dev,etc,home,kernels,libexec,lib,root,sbin,sys,usr,var

# runExtCommand=chmod a+rx /usr/local/bin/after_installation_routines.sh ; cd / ; /usr/local/bin/after_installation_routines.sh
EOF;
	fwrite($fd, $config);
	fclose($fd);
	return;
}

function start_installation() {
	global $g, $fstype;
	if(file_exists("/tmp/install_complete"))
		return;
	$ps_running = exec("ps awwwux | grep -v grep | grep 'sh /tmp/installer.sh'");
	if($ps_running)	
		return;
	$fd = fopen("/tmp/installer.sh", "w");
	if(!$fd) {
		die(gettext("Could not open /tmp/installer.sh for writing"));
		exit;
	}
	fwrite($fd, "rm /tmp/.pc-sysinstall/pc-sysinstall.log 2>/dev/null\n");
	fwrite($fd, "/usr/sbin/pc-sysinstall/pc-sysinstall -c /usr/sbin/pc-sysinstall/examples/pfSense-install.cfg \n");
	fwrite($fd, "chmod a+rx /usr/local/bin/after_installation_routines.sh\n");
	fwrite($fd, "cd / && /usr/local/bin/after_installation_routines.sh\n");
	fwrite($fd, "mkdir /mnt/tmp\n");
//	fwrite($fd, "umount /mnt\n");
	fwrite($fd, "touch /tmp/install_complete\n");
	fclose($fd);
	exec("chmod a+rx /tmp/installer.sh");
	mwexec_bg("sh /tmp/installer.sh");
}

function installer_find_first_disk() {
	global $g, $fstype;
	$disk = `/usr/sbin/pc-sysinstall/pc-sysinstall disk-list | head -n1 | cut -d':' -f1`;
	return $disk;
}

function pcsysinstall_get_disk_info($diskname) {
	global $g, $fstype;
	$disk = split("\n", `/usr/sbin/pc-sysinstall/pc-sysinstall disk-list`);
	$disks_array = array();
	foreach($disk as $d) {
		if(!$d) 
			continue;
		$disks_info = split(":", $d);
		$tmp_array = array();
		if($disks_info[0] == $diskname) {
			$disk_info = split("\n", `/usr/sbin/pc-sysinstall/pc-sysinstall disk-info {$disks_info[0]}`);
			foreach($disk_info as $di) { 
				$di_s = split("=", $di);
				if($di_s[0])
					$tmp_array[$di_s[0]] = $di_s[1];
			}
			$tmp_array['disk'] = trim($disks_info[0]);
			$tmp_array['desc'] = trim(htmlentities($disks_info[1]));
			return $tmp_array;
		}
	}
}

// Return an array with all disks information.
function installer_find_all_disks() {
	global $g, $fstype;
	$disk = split("\n", `/usr/sbin/pc-sysinstall/pc-sysinstall disk-list`);
	$disks_array = array();
	foreach($disk as $d) {
		if(!$d) 
			continue;
		$disks_info = split(":", $d);
		$tmp_array = array();
		$disk_info = split("\n", `/usr/sbin/pc-sysinstall/pc-sysinstall disk-info {$disks_info[0]}`);
		foreach($disk_info as $di) { 
			$di_s = split("=", $di);
			if($di_s[0])
				$tmp_array[$di_s[0]] = $di_s[1];
		}
		$tmp_array['disk'] = trim($disks_info[0]);
		$tmp_array['desc'] = trim(htmlentities($disks_info[1]));
		$disks_array[] = $tmp_array;
	}
	return $disks_array;
}

function update_installer_status() {
	global $g, $fstype;
	// Ensure status files exist
	if(!file_exists("/tmp/installer_installer_running"))
		touch("/tmp/installer_installer_running");
	$status = `cat /tmp/.pc-sysinstall/pc-sysinstall.log`;
	$status = str_replace("\n", "\\n", $status);
	$status = str_replace("\n", "\\r", $status);
	echo "this.document.forms[0].installeroutput.value='$status';\n";
	echo "this.document.forms[0].installeroutput.scrollTop = this.document.forms[0].installeroutput.scrollHeight;\n";	
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
		$progress = "11";
	if(strstr($status, "/bin /mnt/bin"))
		$progress = "12";
	if(strstr($status, "/conf /mnt/conf"))
		$progress = "15";
	if(strstr($status, "/conf.default /mnt/conf.default"))
		$progress = "20";
	if(strstr($status, "/dev /mnt/dev"))
		$progress = "25";
	if(strstr($status, "/etc /mnt/etc"))
		$progress = "30";
	if(strstr($status, "/home /mnt/home"))
		$progress = "35";
	if(strstr($status, "/kernels /mnt/kernels"))
		$progress = "40";
	if(strstr($status, "/libexec /mnt/libexec"))
		$progress = "50";
	if(strstr($status, "/lib /mnt/lib"))
		$progress = "60";
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
	// Check for error and bail if we see one.
	if(stristr($status, "error")) {
		$error = true;
		echo "\$('installerrunning').innerHTML='<img class=\"infoboxnpimg\" src=\"/themes/{$g['theme']}/images/icons/icon_exclam.gif\"> <font size=\"2\"><b>An error occurred.  Aborting installation.'; ";
		echo "\$('progressbar').style.width='100%';\n";
		unlink("/tmp/install_complete");
		return;
	}
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
	if(file_exists("/tmp/install_complete")) {
		echo "\$('installerrunning').innerHTML='<img class=\"infoboxnpimg\" src=\"/themes/{$g['theme']}/images/icons/icon_exclam.gif\"> <font size=\"+1\">Installation completed.  Please <a href=\"reboot.php\">reboot</a> to continue';\n";
		echo "\$('pbdiv').Fade();\n";
		unlink_if_exists("/tmp/installer.sh");
		file_put_contents("/tmp/installer_installer_running", "finished");
	}
}

function update_installer_status_win($status) {
	global $g, $fstype;
	echo "<script type=\"text/javascript\">\n";
	echo "	\$('installeroutput').value = '" . str_replace(htmlentities($status), "\n", "") . "';\n";
	echo "</script>";
}

function begin_install() {
	global $g;
	if(file_exists("/tmp/install_complete"))
		return;
	unlink_if_exists("/tmp/install_complete");
	if($_REQUEST['disk'])
		$disk = $_REQUEST['disk'];
	else 
		$disk = installer_find_first_disk();
	if(!$disk) {
		echo "<script type=\"text/javascript\">";
		echo "\$('pbdiv').Fade();\n";
		echo "</script>";
		$savemsg = gettext("Could not find a suitable disk for installation");
		update_installer_status_win(gettext("Could not find a suitable disk for installation."));
		return;
	}
	// Handle other type of file systems
	if($_REQUEST['fstype']) 
		$fstype = strtoupper($_REQUEST['fstype']);
	else 
		$fstype = "UFS+S";
	write_out_pc_sysinstaller_config($disk, $fstype);
	update_installer_status_win(sprintf(gettext("Beginning installation on disk %s."),$disk));
	start_installation();
}

function head_html() {
	global $g, $fstype;
	echo <<<EOF
<html>
	<head>
		<style type='text/css'>
			a:link { 
				color: #000000;
				text-decoration:underline;
				font-size:14;
			}
			a:visited { 
				color: #000000;
				text-decoration:underline;
				font-size:14;
			}
			a:hover { 
				color: #FFFF00;
				text-decoration: none;
				font-size:14;
			}
			a:active { 
				color: #FFFF00;
				text-decoration:underline;
				font-size:14;
			}
		</style>
	</head>
EOF;

}

function body_html() {
	global $g, $fstype;
	$pfSversion = str_replace("\n", "", file_get_contents("/etc/version"));
	if(strstr($pfSversion, "1.2"))
		$one_two = true;
	$pgtitle = "{$g['product_name']}: " . gettext("Installer");
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
	global $g, $fstype;
	echo "</form>";
	echo "</body>";
	echo "</html>";
}

function template() {
	global $g, $fstype;
	head_html();
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

function verify_before_install() {
	global $g, $fstype;
	head_html();
	body_html();
	page_table_start();
	$disk = pcsysinstall_get_disk_info($_REQUEST['disk']);
	$disksize = format_bytes($disk['size'] * 1048576);
	echo <<<EOF
	<form method="post" action="installer.php">
	<input type="hidden" name="fstype" value="{$_REQUEST['fstype']}">
	<input type="hidden" name="disk" value="{$_REQUEST['disk']}">
	<input type="hidden" name="state" value="begin_install">
	<div id="mainlevel">
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
	 		<tr>
	    		<td>
					<div id="mainarea">
						<table width="100%" border="0" cellpadding="0" cellspacing="0">
							<tr>
	     						<td >
									<div>
										<center>
											<div id="pfsensetemplate">
												<table bgcolor="FFFF00" width="400" height="30" cellpadding="2" style="border:1px dashed;">
													<tr valign="middle">
														<td>
															<center><b>Please verify that the following is correct:</b></center>
														</td>
													</tr>
												</table>
												<p/>
												<table>
													<tr><td align="right"><b>Disk:</td><td>{$_REQUEST['disk']}</td></tr>
													<tr><td align="right"><b>Description:</td><td>{$disk['desc']}</td></tr>
													<tr><td align="right"><b>Size:</td><td>{$disksize}</td></tr>
													<tr><td align="right"><b>Filesystem:</td><td>{$_REQUEST['fstype']}</td></tr>
												</table>
											</div>
										</center>
									</div>
	     						</td>
							</tr>
						</table>
					</div>
					<center>
						<p/>
						<input type="button" value="Cancel" onClick="javascript:document.location='/installer.php';"> &nbsp;&nbsp;
						<input type="submit" value="Begin installation"> 
					</center>
				</td>
			</tr>
		</table>
	</div>
EOF;
	page_table_end();
	end_html();
}

function installing_gui() {
	global $g, $fstype;
	head_html();
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
								<table width="100%" border="0" cellpadding="0" cellspacing="0">
									<tr>
			     						<td>
											<div id="pfsenseinstaller" width="100%">
												<div id='installerrunning' width='100%' style="padding:8px; border:1px dashed #000000">
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
												<div id='pbdiv'>
													<br/>
													<center>
													<table id='pbtable' height='15' width='640' border='0' colspacing='0' cellpadding='0' cellspacing='0'>
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
												</div>
												<textarea name='installeroutput' id='installeroutput' rows="31" cols="90">
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
}

function page_table_start() {
	global $g, $fstype;
	echo <<<EOF
	<center>
		<img border="0" src="./themes/{$g['theme']}/images/logo.gif"></a><br/>
		<table cellpadding="6" cellspacing="0" width="550" height="380" style="border:1px solid #000000">
		<tr height="10" bgcolor="#990000">
			<td style="border-bottom:1px solid #000000">
				<font color='white'>
					<b>
						{$g['product_name']} installer
					</b>
				</font>
			</td>
		</tr>
		<tr>
			<td>

EOF;

}

function page_table_end() {
	global $g, $fstype;
	echo <<<EOF
			</td>
		</tr>
		</table>
	</center>

EOF;
	
}

function installer_custom() {
	global $g, $fstype;
	if(file_exists("/tmp/.pc-sysinstall/pc-sysinstall.log")) 
		unlink("/tmp/.pc-sysinstall/pc-sysinstall.log");
	head_html();
	body_html();
	page_table_start();
	echo <<<EOF
		<form action="installer.php" method="post">
			<input type="hidden" name="state" value="verify_before_install">
			<div id="mainlevel">
				<center>
				<table width="100%" border="0" cellpadding="5" cellspacing="0">
			 		<tr>
			    		<td>
							<center>
							<div id="mainarea">
								<br/>
								<center>
								<table width="100%" border="0" cellpadding="5" cellspacing="5">
									<tr>
			     						<td>
											<div id="pfsenseinstaller">
												<center>
												<div id='loadingdiv'>
													<img src="/themes/{$g['theme']}/images/misc/loader.gif"> Probing disks, please wait...
												</div>
EOF;
	ob_flush();
	$disks = installer_find_all_disks();
	if(!$disks)  {
		$custom_txt = gettext("WARNING: Could not find any suitable disks for installation.");
	} else {
		// Prepare disk selection dropdown
		$custom_txt = <<<EOF
												<table bgcolor="FFFF00" width="400" height="30" cellpadding="2" style="border:1px dashed;">
													<tr valign="middle">
														<td>
															<center><b>Select the installation parameters for {$g['product_name']}:</b></center>
														</td>
													</tr>
												</table><p/>
												<table>
EOF;
		$custom_txt .= "<tr><td align='right'><b>Disk:</td><td><select name='disk'>\n";
		foreach($disks as $disk) {
			$disksize = format_bytes($disk['size'] * 1048576);
			$custom_txt .= "<option value='{$disk['disk']}'>{$disk['disk']} - {$disksize} - {$disk['desc']}</option>\n";
		}
		$custom_txt .= "</select></td></tr>\n";
		// XXX: Convert to rowhelper.  Add Ajax callbacks to verify sizes, etc.
		// Prepare disk types
		$custom_txt .=  "<tr><td align='right'><b>Filesystem type:</td><td><select name='fstype'>\n";
		$custom_txt .=  "<option value='UFS'>UFS</option>\n";
		$custom_txt .=  "<option value='UFS+S'>UFS + Softupdates</option>\n";
		$release = trim(`uname -r | cut -d'.' -f1`);
		if($release == "9")
			$custom_txt .=  "<option value='UFS+J'>UFS + Journaling</option>\n";
		if(file_exists("/boot/gptzfsboot")) 
			$custom_txt .= "<option value='ZFS'>ZFS</option>\n";
		$custom_txt .= "</select>\n</td></tr></table><p/>";
	}
	echo <<<EOF
													<script type="text/javascript">
														\$('loadingdiv').style.visibility='hidden';
													</script>
													<div id='contentdiv' style="display:none;">
														{$custom_txt}<p/>
														<input type="button" value="Cancel" onClick="javascript:document.location='/installer.php';"> &nbsp;&nbsp
														<input type="submit" value="Next">
													</div>
													<script type="text/javascript">
														\$('contentdiv').appear();
													</script>
												</center>
												</td></tr>
												</table>
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

function installer_main() {
	global $g, $fstype;
	if(file_exists("/tmp/.pc-sysinstall/pc-sysinstall.log")) 
		unlink("/tmp/.pc-sysinstall/pc-sysinstall.log");
	head_html();
	body_html();
	// Only enable ZFS if this exists.  The install will fail otherwise.
	if(file_exists("/boot/gptzfsboot")) 
		$zfs_enabled = "<tr bgcolor=\"#9A9A9A\"><td align=\"center\"><a href=\"installer.php?state=verify_before_install&fstype=ZFS\">Easy installation of {$g['product_name']} using the ZFS filesystem on disk {$disk}</a></td></tr>";
	$disk = installer_find_first_disk();
	if(!$disk) 
		echo gettext("WARNING: Could not find any suitable disks for installation.");
	page_table_start();
	echo <<<EOF
		<form action="installer.php" method="post" state="step1_post">
			<div id="mainlevel">
				<center>
				<b><font face="arial" size="+2">Welcome to the {$g['product_name']} PCSysInstaller!</b></font><p/>
				<font face="arial" size="+1">This utility will install {$g['product_name']} to a hard disk, flash drive, etc.</font>
				<table width="100%" border="0" cellpadding="5" cellspacing="0">
			 		<tr>
			    		<td>
							<center>
							<div id="mainarea">
								<br/>
								<center>
								Please select an installer option to begin:
								<p/>
								<table width="100%" border="0" cellpadding="5" cellspacing="5">
									<tr>
			     						<td>
											<div id="pfsenseinstaller">
												<center>
													<table cellspacing="5" cellpadding="5" style="border: 1px dashed;">
														<tr bgcolor="#CECECE"><td align="center">
															<a href="installer.php?state=verify_before_install&disk={$disk}&fstype=UFS">Easy installation of {$g['product_name']} using the UFS filesystem on disk {$disk}</a>
														</td></tr>
													 	{$zfs_enabled}
														<tr bgcolor="#AAAAAA"><td align="center">
															<a href="installer.php?state=custominstall">Custom installation of {$g['product_name']}</a>
														</td></tr>
														<tr bgcolor="#CECECE"><td align="center">
															<a href='/'>Cancel and return to Dashboard</a>
														</td></tr>
													</table>
												</center>
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