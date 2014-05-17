<?php
/* $Id$ */
/*
	diag_backup.php
	Copyright (C) 2004-2009 Scott Ullrich
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
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
	pfSense_BUILDER_BINARIES:	/sbin/shutdown
	pfSense_MODULE:	backup
*/

##|+PRIV
##|*IDENT=page-diagnostics-backup/restore
##|*NAME=Diagnostics: Backup/restore page
##|*DESCR=Allow access to the 'Diagnostics: Backup/restore' page.
##|*MATCH=diag_backup.php*
##|-PRIV

/* Allow additional execution time 0 = no limit. */
ini_set('max_execution_time', '0');
ini_set('max_input_time', '0');

/* omit no-cache headers because it confuses IE with file downloads */
$omit_nocacheheaders = true;
$nocsrf = true;
require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$rrddbpath = "/var/db/rrd";
$rrdtool = "/usr/bin/nice -n20 /usr/local/bin/rrdtool";

function rrd_data_xml() {
	global $rrddbpath;
	global $rrdtool;

	$result = "\t<rrddata>\n";
	$rrd_files = glob("{$rrddbpath}/*.rrd");
	$xml_files = array();
	foreach ($rrd_files as $rrd_file) {
		$basename = basename($rrd_file);
		$xml_file = preg_replace('/\.rrd$/', ".xml", $rrd_file);
		exec("$rrdtool dump '{$rrd_file}' '{$xml_file}'");
		$xml_data = file_get_contents($xml_file);
		unlink($xml_file);
		if ($xml_data !== false) {
			$result .= "\t\t<rrddatafile>\n";
			$result .= "\t\t\t<filename>{$basename}</filename>\n";
			$result .= "\t\t\t<xmldata>" . base64_encode(gzdeflate($xml_data)) . "</xmldata>\n";
			$result .= "\t\t</rrddatafile>\n";
		}
	}
	$result .= "\t</rrddata>\n";
	return $result;
}

function restore_rrddata() {
	global $config, $g, $rrdtool, $input_errors;
	foreach($config['rrddata']['rrddatafile'] as $rrd) {
		if ($rrd['xmldata']) {
			$rrd_file = "{$g['vardb_path']}/rrd/{$rrd['filename']}";
			$xml_file = preg_replace('/\.rrd$/', ".xml", $rrd_file);
			if (file_put_contents($xml_file, gzinflate(base64_decode($rrd['xmldata']))) === false) {
				log_error("Cannot write $xml_file");
				continue;
			}
			$output = array();
			$status = null;
			exec("$rrdtool restore -f '{$xml_file}' '{$rrd_file}'", $output, $status);
			if ($status) {
				log_error("rrdtool restore -f '{$xml_file}' '{$rrd_file}' failed returning {$status}.");
				continue;
			}
			unlink($xml_file);
		}
		else if ($rrd['data']) {
			$rrd_file = "{$g['vardb_path']}/rrd/{$rrd['filename']}";
			$rrd_fd = fopen($rrd_file, "w");
			if (!$rrd_fd) {
				log_error("Cannot write $rrd_file");
				continue;
			}
			$data = base64_decode($rrd['data']);
			/* Try to decompress the data. */
			$dcomp = @gzinflate($data);
			if ($dcomp) {
				/* If the decompression worked, write the decompressed data */
				if (fwrite($rrd_fd, $dcomp) === false) {
					log_error("fwrite $rrd_file failed");
					continue;
				}
			} else {
				/* If the decompression failed, it wasn't compressed, so write raw data */
				if (fwrite($rrd_fd, $data) === false) {
					log_error("fwrite $rrd_file failed");
					continue;
				}
			}
			if (fclose($rrd_fd) === false) {
				log_error("fclose $rrd_file failed");
				continue;
			}
		}
	}
}

function add_base_packages_menu_items() {
	global $g, $config;
	$base_packages = explode(",", $g['base_packages']);
	$modified_config = false;
	foreach($base_packages as $bp) {
		$basepkg_path = "/usr/local/pkg/{$bp}";
		$tmpinfo = pathinfo($basepkg_path, PATHINFO_EXTENSION);
		if($tmpinfo['extension'] == "xml" && file_exists($basepkg_path)) {
			$pkg_config = parse_xml_config_pkg($basepkg_path, "packagegui");
			if($pkg_config['menu'] != "") {
				if(is_array($pkg_config['menu'])) {
					foreach($pkg_config['menu'] as $menu) {
						if(is_array($config['installedpackages']['menu']))
							foreach($config['installedpackages']['menu'] as $amenu)
								if($amenu['name'] == $menu['name'])
									continue;
						$config['installedpackages']['menu'][] = $menu;
						$modified_config = true;
					}
				}
				$static_output .= "done.\n";
				update_output_window($static_output);
			}
		}
	}
	if($modified_config) {
		write_config(gettext("Restored base_package menus after configuration restore."));
		$config = parse_config(true);
	}
}

function remove_bad_chars($string) {
	return preg_replace('/[^a-z_0-9]/i','',$string);
}

function check_and_returnif_section_exists($section) {
	global $config;
	if(is_array($config[$section]))
		return true;
	return false;
}

function spit_out_select_items($name, $showall) {
	global $config;

	$areas = array("aliases" => gettext("Aliases"),
		       "captiveportal" => gettext("Captive Portal"),
		       "voucher" => gettext("Captive Portal Vouchers"),
		       "dnsmasq" => gettext("DNS Forwarder"),
		       "dhcpd" => gettext("DHCP Server"),
		       "dhcpdv6" => gettext("DHCPv6 Server"),
		       "filter" => gettext("Firewall Rules"),
		       "interfaces" => gettext("Interfaces"),
		       "ipsec" => gettext("IPSEC"),
		       "nat" => gettext("NAT"),
		       "openvpn" => gettext("OpenVPN"),
		       "installedpackages" => gettext("Package Manager"),
		       "pptpd" => gettext("PPTP Server"),
		       "rrddata" => gettext("RRD Data"),
		       "cron" => gettext("Scheduled Tasks"),
		       "syslog" => gettext("Syslog"),
		       "system" => gettext("System"),
		       "staticroutes" => gettext("Static routes"),
		       "sysctl" => gettext("System tunables"),
		       "snmpd" => gettext("SNMP Server"),
		       "shaper" => gettext("Traffic Shaper"),
		       "vlans" => gettext("VLANS"),
		       "wol" => gettext("Wake on LAN")
		);

	$select  = "<select name=\"{$name}\" id=\"{$name}\">";
	$select .= "<option value=\"\">" . gettext("ALL") . "</option>";

	if($showall == true)
		foreach($areas as $area => $areaname)
			$select .= "<option value=\"{$area}\">{$areaname}</option>\n";
	else
		foreach($areas as $area => $areaname)
			if($area === "rrddata" || check_and_returnif_section_exists($area) == true)
				$select .= "<option value=\"{$area}\">{$areaname}</option>\n";

	$select .= "</select>\n";

	if ($name === "backuparea") {
		$select .= <<<END_SCRIPT_BLOCK
			<script type="text/javascript">
			//<![CDATA[
				jQuery(function (\$) {
					$("#{$name}").change(function () {
						backuparea_change(this);
					}).trigger("change");
				});
			//]]>
			</script>
END_SCRIPT_BLOCK;
	}

	echo $select;

}

if ($_POST['apply']) {
	ob_flush();
	flush();
	conf_mount_rw();
	clear_subsystem_dirty("restore");
	conf_mount_ro();
	exit;
}

if ($_POST) {
	unset($input_errors);
	if (stristr($_POST['Submit'], gettext("Restore configuration")))
		$mode = "restore";
	else if (stristr($_POST['Submit'], gettext("Reinstall")))
		$mode = "reinstallpackages";
	else if (stristr($_POST['Submit'], gettext("Clear Package Lock")))
		$mode = "clearpackagelock";
	else if (stristr($_POST['Submit'], gettext("Download")))
		$mode = "download";
	else if (stristr($_POST['Submit'], gettext("Restore version")))
		$mode = "restore_ver";

	if ($_POST["nopackages"] <> "")
		$options = "nopackages";

	if ($_POST["ver"] <> "")
		$ver2restore = $_POST["ver"];

	if ($mode) {

		if ($mode == "download") {

			if ($_POST['encrypt']) {
				if(!$_POST['encrypt_password'] || !$_POST['encrypt_passconf'])
					$input_errors[] = gettext("You must supply and confirm the password for encryption.");
				if($_POST['encrypt_password'] != $_POST['encrypt_passconf'])
					$input_errors[] = gettext("The supplied 'Password' and 'Confirm' field values must match.");
			}

			if (!$input_errors) {

				//$lockbckp = lock('config');

				$host = "{$config['system']['hostname']}.{$config['system']['domain']}";
				$name = "config-{$host}-".date("YmdHis").".xml";
				$data = "";

				if($options == "nopackages") {
					if(!$_POST['backuparea']) {
						/* backup entire configuration */
						$data = file_get_contents("{$g['conf_path']}/config.xml");
					} else {
						/* backup specific area of configuration */
						$data = backup_config_section($_POST['backuparea']);
						$name = "{$_POST['backuparea']}-{$name}";
					}
					$sfn = "{$g['tmp_path']}/config.xml.nopkg";
					file_put_contents($sfn, $data);
					exec("sed '/<installedpackages>/,/<\/installedpackages>/d' {$sfn} > {$sfn}-new");
					$data = file_get_contents($sfn . "-new");
				} else {
					if(!$_POST['backuparea']) {
						/* backup entire configuration */
						$data = file_get_contents("{$g['conf_path']}/config.xml");
					} else if ($_POST['backuparea'] === "rrddata") {
						$data = rrd_data_xml();
						$name = "{$_POST['backuparea']}-{$name}";
					} else {
						/* backup specific area of configuration */
						$data = backup_config_section($_POST['backuparea']);
						$name = "{$_POST['backuparea']}-{$name}";
					}
				}

				//unlock($lockbckp);

				/*
				 *  Backup RRD Data
				 */
				if ($_POST['backuparea'] !== "rrddata" && !$_POST['donotbackuprrd']) {
					$rrd_data_xml = rrd_data_xml();
					$closing_tag = "</" . $g['xml_rootobj'] . ">";
					$data = str_replace($closing_tag, $rrd_data_xml . $closing_tag, $data);
				}

				if ($_POST['encrypt']) {
					$data = encrypt_data($data, $_POST['encrypt_password']);
					tagfile_reformat($data, $data, "config.xml");
				}

				$size = strlen($data);
				header("Content-Type: application/octet-stream");
				header("Content-Disposition: attachment; filename={$name}");
				header("Content-Length: $size");
				if (isset($_SERVER['HTTPS'])) {
					header('Pragma: ');
					header('Cache-Control: ');
				} else {
					header("Pragma: private");
					header("Cache-Control: private, must-revalidate");
				}
				echo $data;

				exit;
			}
		}

		if ($mode == "restore") {

			if ($_POST['decrypt']) {
				if(!$_POST['decrypt_password'] || !$_POST['decrypt_passconf'])
					$input_errors[] = gettext("You must supply and confirm the password for decryption.");
				if($_POST['decrypt_password'] != $_POST['decrypt_passconf'])
					$input_errors[] = gettext("The supplied 'Password' and 'Confirm' field values must match.");
			}

			if (!$input_errors) {

				if (is_uploaded_file($_FILES['conffile']['tmp_name'])) {

					/* read the file contents */
					$data = file_get_contents($_FILES['conffile']['tmp_name']);
					if(!$data) {
						log_error(sprintf(gettext("Warning, could not read file %s"), $_FILES['conffile']['tmp_name']));
						return 1;
					}

					if ($_POST['decrypt']) {
						if (!tagfile_deformat($data, $data, "config.xml")) {
							$input_errors[] = gettext("The uploaded file does not appear to contain an encrypted pfsense configuration.");
							return 1;
						}
						$data = decrypt_data($data, $_POST['decrypt_password']);
					}

					if(stristr($data, "<m0n0wall>")) {
						log_error(gettext("Upgrading m0n0wall configuration to pfsense."));
						/* m0n0wall was found in config.  convert it. */
						$data = str_replace("m0n0wall", "pfsense", $data);
						$m0n0wall_upgrade = true;
					}
					if($_POST['restorearea']) {
						/* restore a specific area of the configuration */
						if(!stristr($data, "<" . $_POST['restorearea'] . ">")) {
							$input_errors[] = gettext("You have selected to restore an area but we could not locate the correct xml tag.");
						} else {
							if (!restore_config_section($_POST['restorearea'], $data)) {
								$input_errors[] = gettext("You have selected to restore an area but we could not locate the correct xml tag.");
							} else {
								if ($config['rrddata']) {
									restore_rrddata();
									unset($config['rrddata']);
									unlink_if_exists("{$g['tmp_path']}/config.cache");
									write_config();
									add_base_packages_menu_items();
									convert_config();
									conf_mount_ro();
								}
								filter_configure();
								$savemsg = gettext("The configuration area has been restored.  You may need to reboot the firewall.");
							}
						}
					} else {
						if(!stristr($data, "<" . $g['xml_rootobj'] . ">")) {
							$input_errors[] = sprintf(gettext("You have selected to restore the full configuration but we could not locate a %s tag."), $g['xml_rootobj']);
						} else {
							/* restore the entire configuration */
							file_put_contents($_FILES['conffile']['tmp_name'], $data);
							if (config_install($_FILES['conffile']['tmp_name']) == 0) {
								/* this will be picked up by /index.php */
								conf_mount_rw();
								mark_subsystem_dirty("restore");
								touch("/conf/needs_package_sync");
								/* remove cache, we will force a config reboot */
								if(file_exists("{$g['tmp_path']}/config.cache"))
									unlink("{$g['tmp_path']}/config.cache");
								$config = parse_config(true);
								/* extract out rrd items, unset from $config when done */
								if($config['rrddata']) {
									restore_rrddata();
									unset($config['rrddata']);
									unlink_if_exists("{$g['tmp_path']}/config.cache");
									write_config();
									add_base_packages_menu_items();
									convert_config();
									conf_mount_ro();
								}
								if($m0n0wall_upgrade == true) {
									if($config['system']['gateway'] <> "")
										$config['interfaces']['wan']['gateway'] = $config['system']['gateway'];
									unset($config['shaper']);
									/* optional if list */
									$ifdescrs = get_configured_interface_list(true, true);
									/* remove special characters from interface descriptions */
									if(is_array($ifdescrs))
										foreach($ifdescrs as $iface)
											$config['interfaces'][$iface]['descr'] = remove_bad_chars($config['interfaces'][$iface]['descr']);
									/* check for interface names with an alias */
									if(is_array($ifdescrs)) {
										foreach($ifdescrs as $iface) {
											if(is_alias($config['interfaces'][$iface]['descr'])) {
												// Firewall rules
												$origname = $config['interfaces'][$iface]['descr'];
												$newname  = $config['interfaces'][$iface]['descr'] . "Alias";
												update_alias_names_upon_change(array('filter', 'rule'), array('source', 'address'), $newname, $origname);
												update_alias_names_upon_change(array('filter', 'rule'), array('destination', 'address'), $newname, $origname);
												// NAT Rules
												update_alias_names_upon_change(array('nat', 'rule'), array('source', 'address'), $newname, $origname);
												update_alias_names_upon_change(array('nat', 'rule'), array('destination', 'address'), $newname, $origname);
												update_alias_names_upon_change(array('nat', 'rule'), array('target'), $newname, $origname);
												// Alias in an alias
												update_alias_names_upon_change(array('aliases', 'alias'), array('address'), $newname, $origname);
											}
										}
									}
									unlink_if_exists("{$g['tmp_path']}/config.cache");
									// Reset configuration version to something low
									// in order to force the config upgrade code to
									// run through with all steps that are required.
									$config['system']['version'] = "1.0";
									// Deal with descriptions longer than 63 characters
									for ($i = 0; isset($config["filter"]["rule"][$i]); $i++) {
										if(count($config['filter']['rule'][$i]['descr']) > 63)
											$config['filter']['rule'][$i]['descr'] = substr($config['filter']['rule'][$i]['descr'], 0, 63);
									}
									// Move interface from ipsec to enc0
									for ($i = 0; isset($config["filter"]["rule"][$i]); $i++) {
										if($config['filter']['rule'][$i]['interface'] == "ipsec")
											$config['filter']['rule'][$i]['interface'] = "enc0";
									}
									// Convert icmp types
									// http://www.openbsd.org/cgi-bin/man.cgi?query=icmp&sektion=4&arch=i386&apropos=0&manpath=OpenBSD+Current
									for ($i = 0; isset($config["filter"]["rule"][$i]); $i++) {
										if($config["filter"]["rule"][$i]['icmptype']) {
											switch($config["filter"]["rule"][$i]['icmptype']) {
											case "echo":
												$config["filter"]["rule"][$i]['icmptype'] = "echoreq";
												break;
											case "unreach":
												$config["filter"]["rule"][$i]['icmptype'] = "unreach";
												break;
											case "echorep":
												$config["filter"]["rule"][$i]['icmptype'] = "echorep";
												break;
											case "squench":
												$config["filter"]["rule"][$i]['icmptype'] = "squench";
												break;
											case "redir":
												$config["filter"]["rule"][$i]['icmptype'] = "redir";
												break;
											case "timex":
												$config["filter"]["rule"][$i]['icmptype'] = "timex";
												break;
											case "paramprob":
												$config["filter"]["rule"][$i]['icmptype'] = "paramprob";
												break;
											case "timest":
												$config["filter"]["rule"][$i]['icmptype'] = "timereq";
												break;
											case "timestrep":
												$config["filter"]["rule"][$i]['icmptype'] = "timerep";
												break;
											case "inforeq":
												$config["filter"]["rule"][$i]['icmptype'] = "inforeq";
												break;
											case "inforep":
												$config["filter"]["rule"][$i]['icmptype'] = "inforep";
												break;
											case "maskreq":
												$config["filter"]["rule"][$i]['icmptype'] = "maskreq";
												break;
											case "maskrep":
												$config["filter"]["rule"][$i]['icmptype'] = "maskrep";
												break;
											}
										}
									}
									$config['diag']['ipv6nat'] = true;
									write_config();
									add_base_packages_menu_items();
									convert_config();
									conf_mount_ro();
									$savemsg = gettext("The m0n0wall configuration has been restored and upgraded to pfSense.");
									mark_subsystem_dirty("restore");
								}
								if(is_array($config['captiveportal'])) {
									foreach($config['captiveportal'] as $cp) {
										if (isset($cp['enable'])) {
											/* for some reason ipfw doesn't init correctly except on bootup sequence */
											mark_subsystem_dirty("restore");
											break;
										}
									}
								}
								setup_serial_port();
								if(is_interface_mismatch() == true) {
									touch("/var/run/interface_mismatch_reboot_needed");
									clear_subsystem_dirty("restore");
									convert_config();
									header("Location: interfaces_assign.php");
									exit;
								}
								if (is_interface_vlan_mismatch() == true) {
									touch("/var/run/interface_mismatch_reboot_needed");
									clear_subsystem_dirty("restore");
									convert_config();
									header("Location: interfaces_assign.php");
									exit;
								}
							} else {
								$input_errors[] = gettext("The configuration could not be restored.");
							}
						}
					}
				} else {
					$input_errors[] = gettext("The configuration could not be restored (file upload error).");
				}
			}
		}

		if ($mode == "reinstallpackages") {

			header("Location: pkg_mgr_install.php?mode=reinstallall");
			exit;
		} else if ($mode == "clearpackagelock") {
			clear_subsystem_dirty('packagelock');
			$savemsg = "Package Lock Cleared";
		} else if ($mode == "restore_ver") {
			$input_errors[] = gettext("XXX - this feature may hose your config (do NOT backrev configs!) - billm");
			if ($ver2restore <> "") {
				$conf_file = "{$g['cf_conf_path']}/bak/config-" . strtotime($ver2restore) . ".xml";
				if (config_install($conf_file) == 0) {
					mark_subsystem_dirty("restore");
				} else {
					$input_errors[] = gettext("The configuration could not be restored.");
				}
			} else {
				$input_errors[] = gettext("No version selected.");
			}
		}
	}
}

$id = rand() . '.' . time();

$mth = ini_get('upload_progress_meter.store_method');
$dir = ini_get('upload_progress_meter.file.filename_template');

$pgtitle = array(gettext("Diagnostics"),gettext("Backup/restore"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[

function encrypt_change() {

	if (!document.iform.encrypt.checked)
		document.getElementById("encrypt_opts").style.display="none";
	else
		document.getElementById("encrypt_opts").style.display="";
}

function decrypt_change() {

	if (!document.iform.decrypt.checked)
		document.getElementById("decrypt_opts").style.display="none";
	else
		document.getElementById("decrypt_opts").style.display="";
}

function backuparea_change(obj) {
	if (obj.value == "rrddata") {
		document.getElementById("nopackages").disabled      = true;
		document.getElementById("dotnotbackuprrd").disabled = true;
	} else {
		document.getElementById("nopackages").disabled      = false;
		document.getElementById("dotnotbackuprrd").disabled = false;
	}
}
//]]>
</script>

<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('restore')): ?><br/>
<form action="reboot.php" method="post">
<input name="Submit" type="hidden" value="Yes" />
<?php print_info_box(gettext("The firewall configuration has been changed.") . "<br />" . gettext("The firewall is now rebooting."));?><br />
</form>
<?php endif; ?>
<form action="diag_backup.php" method="post" name="iform" enctype="multipart/form-data">
<table width="100%" border="0" cellspacing="0" cellpadding="0" summary="diag backup">
	<tr>
		<td>
<?php
		$tab_array = array();
		$tab_array[0] = array(gettext("Config History"), false, "diag_confbak.php");
		$tab_array[1] = array(gettext("Backup/Restore"), true, "diag_backup.php");
		display_top_tabs($tab_array);
?>
		</td>
	</tr>
	<tr>
		<td>
			<div id="mainarea">
			<table class="tabcont" align="center" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
				<tr>
					<td colspan="2" class="listtopic"><?=gettext("Backup configuration"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign="baseline" class="vncell">&nbsp;</td>
					<td width="78%" class="vtable">
						<p><?=gettext("Click this button to download the system configuration in XML format."); ?><br /><br /> <?=gettext("Backup area:"); ?> <?php spit_out_select_items("backuparea", false); ?></p>
						<table>
							<tr>
								<td>
									<input name="nopackages" type="checkbox" class="formcheckbox" id="nopackages" />
								</td>
								<td>
									<span class="vexpl"><?=gettext("Do not backup package information."); ?></span>
								</td>
							</tr>
						</table>
						<table>
							<tr>
								<td>
									<input name="encrypt" type="checkbox" class="formcheckbox" id="nopackages" onclick="encrypt_change()" />
								</td>
								<td>
									<span class="vexpl"><?=gettext("Encrypt this configuration file."); ?></span>
								</td>
							</tr>
							<tr>
								<td>
									<input name="donotbackuprrd" type="checkbox" class="formcheckbox" id="dotnotbackuprrd" checked="checked" />
								</td>
								<td>
									<span class="vexpl"><?=gettext("Do not backup RRD data (NOTE: RRD Data can consume 4+ megabytes of config.xml space!)"); ?></span>
								</td>
							</tr>
						</table>
						<table id="encrypt_opts">
							<tr>
								<td>
									<span class="vexpl"><?=gettext("Password:"); ?> </span>
								</td>
								<td>
									<input name="encrypt_password" type="password" class="formfld pwd" size="20" value="" />
								</td>
							</tr>
							<tr>
								<td>
									<span class="vexpl"><?=gettext("confirm:"); ?> </span>
								</td>
								<td>
									<input name="encrypt_passconf" type="password" class="formfld pwd" size="20" value="" />
								</td>
							</tr>
						</table>
						<p><input name="Submit" type="submit" class="formbtn" id="download" value="<?=gettext("Download configuration"); ?>" /></p>
					</td>
				</tr>
				<tr>
					<td colspan="2" class="list" height="12">&nbsp;</td>
				</tr>
				<tr>
					<td colspan="2" class="listtopic"><?=gettext("Restore configuration"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign="baseline" class="vncell">&nbsp;</td>
					<td width="78%" class="vtable">
						<?=gettext("Open a"); ?> <?=$g['[product_name']?> <?=gettext("configuration XML file and click the button below to restore the configuration."); ?>
						<br /><br />
						<?=gettext("Restore area:"); ?> <?php spit_out_select_items("restorearea", true); ?>
						<p><input name="conffile" type="file" class="formbtn" id="conffile" size="40" /></p>
						<table>
							<tr>
								<td>
									<input name="decrypt" type="checkbox" class="formcheckbox" id="nopackages" onclick="decrypt_change()" />
								</td>
								<td>
									<span class="vexpl"><?=gettext("Configuration file is encrypted."); ?></span>
								</td>
							</tr>
						</table>
						<table id="decrypt_opts">
							<tr>
								<td>
									<span class="vexpl"><?=gettext("Password :"); ?></span>
								</td>
								<td>
									<input name="decrypt_password" type="password" class="formfld pwd" size="20" value="" />
								</td>
							</tr>
							<tr>
								<td>
									<span class="vexpl"><?=gettext("confirm :"); ?></span>
								</td>
								<td>
									<input name="decrypt_passconf" type="password" class="formfld pwd" size="20" value="" />
								</td>
							</tr>
						</table>
						<p><input name="Submit" type="submit" class="formbtn" id="restore" value="<?=gettext("Restore configuration"); ?>" /></p>
						<p><strong><span class="red"><?=gettext("Note:"); ?></span></strong><br /><?=gettext("The firewall will reboot after restoring the configuration."); ?><br /></p>
					</td>
				</tr>
				<?php if (($config['installedpackages']['package'] != "") || (is_subsystem_dirty("packagelock"))) { ?>
				<tr>
					<td colspan="2" class="list" height="12">&nbsp;</td>
				</tr>
				<tr>
					<td colspan="2" class="listtopic"><?=gettext("Package Functions"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign="baseline" class="vncell">&nbsp;</td>
					<td width="78%" class="vtable">
						<?php if ($config['installedpackages']['package'] != "") { ?>
							<p><?=gettext("Click this button to reinstall all system packages.  This may take a while."); ?> <br /><br />
							<input name="Submit" type="submit" class="formbtn" id="reinstallpackages" value="<?=gettext("Reinstall packages"); ?>" />
							<br />
							<br />
						<?php } ?>
						<?php if (is_subsystem_dirty("packagelock")) { ?>
							<p><?=gettext("Click this button to clear the package lock if a package fails to reinstall properly after an upgrade."); ?> <br /><br />
							<input name="Submit" type="submit" class="formbtn" id="clearpackagelock" value="<?=gettext("Clear Package Lock"); ?>" />
						<?php } ?>
							</p>
					</td>
				</tr>
				<?php } ?>
			</table>
			</div>
		</td>
	</tr>
</table>
</form>

<script type="text/javascript">
//<![CDATA[
encrypt_change();
decrypt_change();
//]]>
</script>

<?php include("fend.inc"); ?>
</body>
</html>
<?php

if (is_subsystem_dirty('restore'))
	system_reboot();

?>
