#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	diag_backup.php
	part of m0n0wall (http://m0n0.ch/wall)

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

/* omit no-cache headers because it confuses IE with file downloads */
$omit_nocacheheaders = true;
require("guiconfig.inc");
require("xmlparse_pkg.inc");

function spit_out_select_items($area) {
	$select = <<<EOD
	<select name="{$area}">
		<option VALUE="">ALL</option>
		<option VALUE="shaper">Traffic Shaper</option>
		<option VALUE="filter">Firewall Rules</option>
		<option VALUE="nat">NAT</option>
		<option VALUE="pptpd">PPTP Server</option>
		<option VALUE="ipsec">IPSEC VPN</option>
		<option VALUE="captiveportal">Captive Portal</option>
		<option VALUE="installedpackages">Package Manager</option>
		<option VALUE="interfaces">Interfaces</option>
		<option VALUE="dhcpd">DHCP Server</option>
		<option VALUE="syslog">Syslog</option>
		<option VALUE="system">System</option>
	</select>
EOD;
	echo $select;

}

if ($_POST) {
	unset($input_errors);
	if (stristr($_POST['Submit'], "Restore configuration"))
		$mode = "restore";
	else if (stristr($_POST['Submit'], "Reinstall"))
		$mode = "reinstallpackages";
	else if (stristr($_POST['Submit'], "Download"))
		$mode = "download";
	else if (stristr($_POST['Submit'], "Restore version"))
		$mode = "restore_ver";

	if ($_POST["nopackages"] <> "")
		$options = "nopackages";

	if ($_POST["ver"] <> "")
		$ver2restore = $_POST["ver"];

	if ($mode) {
		if ($mode == "download") {
			config_lock();
			$fn = "config-" . $config['system']['hostname'] . "." .
				$config['system']['domain'] . "-" . date("YmdHis") . ".xml";
			if($options == "nopackages") {
				exec("sed '/<installedpackages>/,/<\/installedpackages>/d' /conf/config.xml > /tmp/config.xml.nopkg");
				$fs = filesize("{$g['tmp_path']}/config.xml.nopkg");
				header("Content-Type: application/octet-stream");
                        	header("Content-Disposition: attachment; filename=$fn");
                        	header("Content-Length: $fs");
				readfile("{$g['tmp_path']}/config.xml.nopkg");
			} else {
				if($_POST['backuparea'] <> "") {
					/* user wishes to backup specific area of configuration */
					$current_trafficshaper_section = backup_config_section($_POST['backuparea']);
					/* generate aliases xml */
					$fout = fopen("{$g['tmp_path']}/backup_section.txt","w");
					fwrite($fout, $current_trafficshaper_section);
					fclose($fout);
					$fs = filesize($g['tmp_path'] . "/backup_section.txt");
					header("Content-Type: application/octet-stream");
					$fn = $_POST['backuparea'] . "-" . $fn;
					header("Content-Disposition: attachment; filename=$fn");
					header("Content-Length: $fs");
					readfile($g['tmp_path'] . "/backup_section.txt");
					unlink($g['tmp_path'] . "/backup_section.txt");
				} else {
					$fs = filesize($g['conf_path'] . "/config.xml");
					header("Content-Type: application/octet-stream");
					header("Content-Disposition: attachment; filename=$fn");
					header("Content-Length: $fs");
					readfile($g['conf_path'] . "/config.xml");
				}
			}
			config_unlock();
			exit;
		} else if ($mode == "restore") {
			if (is_uploaded_file($_FILES['conffile']['tmp_name'])) {
				$command = "/sbin/sysctl -a | grep carp";
				$fd = fopen($_FILES['conffile']['tmp_name'], "r");
				if(!$fd) {
					log_error("Warning, could not open " . $_FILES['conffile']['tmp_name']);
					return 1;
				}
				while(!feof($fd)) {
					    $tmp .= fread($fd,49);
				}
				fclose($fd);
				if(stristr($tmp, "m0n0wall" != false)) {
					log_error("Upgrading m0n0wall configuration to pfsense.");
					/* m0n0wall was found in config.  convert it. */
					$upgradedconfig = str_replace("m0n0wall", "pfsense", $tmp);
					fopen($_FILES['conffile']['tmp_name'], "w");
					fwrite($fd, $upgradedconfig);
					fclose($fd);
				}
				if($_POST['restorearea'] <> "") {
					/* restore a specific area of the configuration */
					$rules = return_filename_as_string($_FILES['conffile']['tmp_name']);
					restore_config_section($_POST['restorearea'], $rules);
					filter_configure();
					$savemsg = "The configuration area has been restored.";
				} else {
					/* restore the entire configuration */
					if (config_install($_FILES['conffile']['tmp_name']) == 0) {
						system_reboot();
						$savemsg = "The configuration has been restored. The firewall is now rebooting.";
					} else {
						$input_errors[] = "The configuration could not be restored.";
					}
				}
			} else {
				$input_errors[] = "The configuration could not be restored (file upload error).";
			}
		} else if ($mode == "reinstallpackages") {
			header("Location: pkg_mgr_install.php?mode=reinstallall");
			exit;
                } else if ($mode == "restore_ver") {
			$input_errors[] = "XXX - this feature may hose your config (do NOT backrev configs!) - billm";
			if ($ver2restore <> "") {
				$conf_file = "{$g['cf_conf_path']}/bak/config-" . strtotime($ver2restore) . ".xml";
                                if (config_install($conf_file) == 0) {
                                        system_reboot();
                                        $savemsg = "The configuration has been restored. The firewall is now rebooting.";
                                } else {
                                        $input_errors[] = "The configuration could not be restored.";
                                }
                        } else {
                                $input_errors[] = "No version selected.";
                        }
		}
	}
}


/* XXX - billm: begginnings of version control code
 * don't set system/version_control :)
 */
if (isset($config['system']['version_control'])) {
	$dir="{$g['cf_conf_path']}/bak";
	$old_versions = array();
	if (is_dir($dir)) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				if ($file != "." && $file != "..") {
					if (ereg("config-([0-9]+).xml", $file, $fname_array)) {
						$old_ver = array();
						$conf = return_filename_as_string("{$g['cf_conf_path']}/bak/" .$file);
						ereg("<lastchangedesc>(.*)</lastchangedesc>", $conf, $desc_array);
						$old_ver['date']=date("Y-m-d H:i:s", $fname_array[1]);
						$old_ver['desc']=$desc_array[1];

						array_push(&$old_versions, $old_ver);
					}
                                }
                        }
                        closedir($dh);
                }
	}
}

$id = rand() . '.' . time();

$mth = ini_get('upload_progress_meter.store_method');
$dir = ini_get('upload_progress_meter.file.filename_template');

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Diagnostics: Backup/restore");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
      <p class="pgtitle">Diagnostics: Backup/restore</p>
            <form action="diag_backup.php" method="post" enctype="multipart/form-data" onSubmit="window.open('progress.php?conffile=<?=$id?>','UploadMeter','width=370,height=115', true); return true; ">
            <?php if ($input_errors) print_input_errors($input_errors); ?>
            <?php if ($savemsg) print_info_box($savemsg); ?>
              <table width="100%" border="0" cellspacing="0" cellpadding="6">
                <tr>
                  <td colspan="2" class="listtopic">Backup configuration</td>
                </tr>
                <tr>
                  <td width="22%" valign="baseline" class="vncell">&nbsp;</td>
                  <td width="78%" class="vtable">
                    <p> Click this button to download the system configuration
                      in XML format.<br>
                      <br>
		      Backup area: <?php spit_out_select_items("backuparea"); ?>
		      <p>
		      <input name="nopackages" type="checkbox" class="formcheckbox" id="nopackages">Do not backup package information.<p>
                      <input name="Submit" type="submit" class="formbtn" id="download" value="Download configuration"></td>
                </tr>
                <tr>
                  <td colspan="2" class="list" height="12"></td>
                </tr>
                <tr>
                  <td colspan="2" class="listtopic">Restore configuration</td>
                </tr>
                <tr>
                  <td width="22%" valign="baseline" class="vncell">&nbsp;</td>
                  <td width="78%" class="vtable">
                    Open a pfSense configuration XML file and click the button
                      below to restore the configuration.<br>
                      <br>
		      Restore area: <?php spit_out_select_items("restorearea"); ?>
		      <p>
                      <input name="conffile" type="file" class="formfld" id="conffile" size="40">
                      <p>
                      <input name="Submit" type="submit" class="formbtn" id="restore" value="Restore configuration">
                      <p>
                      <strong><span class="red">Note:</span></strong><br>
                      The firewall will reboot after restoring the configuration.<br>
                  </td>
                </tr>
		<tr>
		 <td colspan="2">&nbsp;</td>
		</tr>
		<tr>
		 <td colspan="2" class="listtopic">Reinstall packages</td>
		</tr>
		<tr>
		  <td width="22%" valign="baseline" class="vncell">&nbsp;</td>
		  <td width="78%" class="vtable">
		  <p> Click this button to reinstall all system packages.  This may take a while.<br>
		  <br>
		  <input name="Submit" type="submit" class="formbtn" id="reinstallpackages" value="Reinstall packages"></td>
		</tr>
              </table>
            </form>
<?php
	/* XXX - billm: begginnings of version control code
	 * This code sucks and is really more of a PoC - it needs cleanup (and doesn't work)
	 * don't set system/version_control :)
	 */
	if (isset($config['system']['version_control'])) {
?>
	    <br>
            <form action="diag_backup.php" method="post" name="version_control" id="version_control">
            <table width="100%" border="0" cellspacing="0" cellpadding="6">
              <tr>
                <td colspan="3" class="listtopic">Config backups</td>
              </tr>
<?php foreach ($old_versions as $ver): ?>
              <tr>
		<td width="22%" valign="baseline" class="vncell"><?php echo $ver['date']; ?></td>
                <td width="56%" valign="baseline" class="vtable"><?php echo $ver['desc']; ?></td>
                <td width="22%" valign="baseline" align="right" class="vtable"><input name="ver" type="radio" class="formfld" value="<?php echo $ver; ?>"></td>
              </tr>
<?php endforeach; ?>
	   <tr>
		<td colspan="2" valign="baseline" class="vtable"></td>
                <td width="22%" valign="baseline"><input name="Submit" type="submit" class="formbtn" id="ver_restore" value="Restore version"></td>
	   </tr>
           </table>
           </form>
<?php }; ?>
<?php include("fend.inc"); ?>
</body>
</html>
