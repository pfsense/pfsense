<?php
/*
 * diag_backup.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-diagnostics-backup-restore
##|*NAME=Diagnostics: Backup & Restore
##|*DESCR=Allow access to the 'Diagnostics: Backup & Restore' page.
##|*WARN=standard-warning-root
##|*MATCH=diag_backup.php*
##|-PRIV

/* Allow additional execution time 0 = no limit. */
ini_set('max_execution_time', '0');
ini_set('max_input_time', '0');

/* omit no-cache headers because it confuses IE with file downloads */
$omit_nocacheheaders = true;
require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("pkg-utils.inc");

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
	foreach ($config['rrddata']['rrddatafile'] as $rrd) {
		if ($rrd['xmldata']) {
			$rrd_file = "{$g['vardb_path']}/rrd/{$rrd['filename']}";
			$xml_file = preg_replace('/\.rrd$/', ".xml", $rrd_file);
			if (file_put_contents($xml_file, gzinflate(base64_decode($rrd['xmldata']))) === false) {
				log_error(sprintf(gettext("Cannot write %s"), $xml_file));
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
		} else if ($rrd['data']) {
			$rrd_file = "{$g['vardb_path']}/rrd/{$rrd['filename']}";
			$rrd_fd = fopen($rrd_file, "w");
			if (!$rrd_fd) {
				log_error(sprintf(gettext("Cannot write %s"), $rrd_file));
				continue;
			}
			$data = base64_decode($rrd['data']);
			/* Try to decompress the data. */
			$dcomp = @gzinflate($data);
			if ($dcomp) {
				/* If the decompression worked, write the decompressed data */
				if (fwrite($rrd_fd, $dcomp) === false) {
					log_error(sprintf(gettext("fwrite %s failed"), $rrd_file));
					continue;
				}
			} else {
				/* If the decompression failed, it wasn't compressed, so write raw data */
				if (fwrite($rrd_fd, $data) === false) {
					log_error(sprintf(gettext("fwrite %s failed"), $rrd_file));
					continue;
				}
			}
			if (fclose($rrd_fd) === false) {
				log_error(sprintf(gettext("fclose %s failed"), $rrd_file));
				continue;
			}
		}
	}
}

function remove_bad_chars($string) {
	return preg_replace('/[^a-z_0-9]/i', '', $string);
}

function check_and_returnif_section_exists($section) {
	global $config;
	if (is_array($config[$section])) {
		return true;
	}
	return false;
}

if ($_POST['apply']) {
	ob_flush();
	flush();
	clear_subsystem_dirty("restore");
	exit;
}

if ($_POST) {
	unset($input_errors);
	if ($_POST['restore']) {
		$mode = "restore";
	} else if ($_POST['reinstallpackages']) {
		$mode = "reinstallpackages";
	} else if ($_POST['clearpackagelock']) {
		$mode = "clearpackagelock";
	} else if ($_POST['download']) {
		$mode = "download";
	}
	if ($_POST["nopackages"] <> "") {
		$options = "nopackages";
	}
	if ($mode) {
		if ($mode == "download") {
			if ($_POST['encrypt']) {
				if (!$_POST['encrypt_password'] || ($_POST['encrypt_password'] != $_POST['encrypt_password_confirm'])) {
					$input_errors[] = gettext("Supplied password and confirmation do not match.");
				}
			}

			if (!$input_errors) {

				//$lockbckp = lock('config');

				$host = "{$config['system']['hostname']}.{$config['system']['domain']}";
				$name = "config-{$host}-".date("YmdHis").".xml";
				$data = "";

				if ($options == "nopackages") {
					if (!$_POST['backuparea']) {
						/* backup entire configuration */
						$data = file_get_contents("{$g['conf_path']}/config.xml");
					} else {
						/* backup specific area of configuration */
						$data = backup_config_section($_POST['backuparea']);
						$name = "{$_POST['backuparea']}-{$name}";
					}
					$data = preg_replace('/\t*<installedpackages>.*<\/installedpackages>\n/sm', '', $data);
				} else {
					if (!$_POST['backuparea']) {
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
				 *	Backup RRD Data
				 */

				/* If the config on disk had rrddata tags already, remove that section first.
				 * See https://redmine.pfsense.org/issues/8994 and
				 *     https://redmine.pfsense.org/issues/10508 */
				$data = preg_replace("/[[:blank:]]*<rrddata>.*<\\/rrddata>[[:blank:]]*\n*/s", "", $data);
				$data = preg_replace("/[[:blank:]]*<rrddata\\/>[[:blank:]]*\n*/", "", $data);

				if ($_POST['backuparea'] !== "rrddata" && !$_POST['donotbackuprrd']) {
					$rrd_data_xml = rrd_data_xml();
					$closing_tag = "</" . $g['xml_rootobj'] . ">";
					$data = str_replace($closing_tag, $rrd_data_xml . $closing_tag, $data);
				}

				if ($_POST['encrypt']) {
					$data = encrypt_data($data, $_POST['encrypt_password']);
					tagfile_reformat($data, $data, "config.xml");
				}

				send_user_download('data', $data, $name);
			}
		}

		if ($mode == "restore") {
			if ($_POST['decrypt']) {
				if (!$_POST['decrypt_password']) {
					$input_errors[] = gettext("A password for decryption must be supplied and confirmed.");
				}
			}

			if (!$input_errors) {
				if (is_uploaded_file($_FILES['conffile']['tmp_name'])) {

					/* read the file contents */
					$data = file_get_contents($_FILES['conffile']['tmp_name']);
					if (!$data) {
						$input_errors[] = gettext("Warning, could not read file {$_FILES['conffile']['tmp_name']}");
					} elseif ($_POST['decrypt']) {
						if (!tagfile_deformat($data, $data, "config.xml")) {
							$input_errors[] = gettext("The uploaded file does not appear to contain an encrypted pfsense configuration.");
						} else {
							$data = decrypt_data($data, $_POST['decrypt_password']);
							if (empty($data)) {
								$input_errors[] = gettext("File decryption failed. Incorrect password or file is invalid.");
							}
						}
					}
					if (stristr($data, "<m0n0wall>")) {
						log_error(gettext("Upgrading m0n0wall configuration to pfsense."));
						/* m0n0wall was found in config.  convert it. */
						$data = str_replace("m0n0wall", "pfsense", $data);
						$m0n0wall_upgrade = true;
					}

					/* If the config on disk had empty rrddata tags, remove them to
					 * avoid an XML parsing error.
					 * See https://redmine.pfsense.org/issues/8994 */
					$data = preg_replace("/<rrddata><\\/rrddata>/", "", $data);
					$data = preg_replace("/<rrddata\\/>/", "", $data);

					if ($_POST['restorearea'] && !$input_errors) {
						/* restore a specific area of the configuration */
						if (!stristr($data, "<" . $_POST['restorearea'] . ">")) {
							$input_errors[] = gettext("An area to restore was selected but the correct xml tag could not be located.");
						} else {
							if (!restore_config_section($_POST['restorearea'], $data)) {
								$input_errors[] = gettext("An area to restore was selected but the correct xml tag could not be located.");
							} else {
								if ($config['rrddata']) {
									restore_rrddata();
									unset($config['rrddata']);
									unlink_if_exists("{$g['tmp_path']}/config.cache");
									write_config(sprintf(gettext("Unset RRD data from configuration after restoring %s configuration area"), $_POST['restorearea']));
									convert_config();
								}
								filter_configure();
								$savemsg = gettext("The configuration area has been restored. The firewall may need to be rebooted.");
							}
						}
					} elseif (!$input_errors) {
						if (!stristr($data, "<" . $g['xml_rootobj'] . ">")) {
							$input_errors[] = sprintf(gettext("A full configuration restore was selected but a %s tag could not be located."), $g['xml_rootobj']);
						} else {
							/* restore the entire configuration */
							file_put_contents($_FILES['conffile']['tmp_name'], $data);
							if (config_install($_FILES['conffile']['tmp_name']) == 0) {
								/* Save current pkg repo to re-add on new config */
								unset($pkg_repo_conf_path);
								if (isset($config['system']['pkg_repo_conf_path'])) {
									$pkg_repo_conf_path = $config['system']['pkg_repo_conf_path'];
								}

								/* this will be picked up by /index.php */
								mark_subsystem_dirty("restore");
								touch("/conf/needs_package_sync");
								/* remove cache, we will force a config reboot */
								if (file_exists("{$g['tmp_path']}/config.cache")) {
									unlink("{$g['tmp_path']}/config.cache");
								}
								$config = parse_config(true);

								/* Restore previously pkg repo configured */
								$pkg_repo_restored = false;
								if (isset($pkg_repo_conf_path)) {
									$config['system']['pkg_repo_conf_path'] =
									    $pkg_repo_conf_path;
									$pkg_repo_restored = true;
								} elseif (isset($config['system']['pkg_repo_conf_path'])) {
									unset($config['system']['pkg_repo_conf_path']);
									$pkg_repo_restored = true;
								}

								if ($pkg_repo_restored) {
									write_config(gettext("Removing pkg repository set after restoring full configuration"));
									pkg_update(true);
								}

								if (file_exists("/boot/loader.conf")) {
									$loaderconf = file_get_contents("/boot/loader.conf");
									if (strpos($loaderconf, "console=\"comconsole") ||
									    strpos($loaderconf, "boot_serial=\"YES")) {
										$config['system']['enableserial'] = true;
										write_config(gettext("Restore serial console enabling in configuration."));
									}
									unset($loaderconf);
								}
								if (file_exists("/boot/loader.conf.local")) {
									$loaderconf = file_get_contents("/boot/loader.conf.local");
									if (strpos($loaderconf, "console=\"comconsole") ||
									    strpos($loaderconf, "boot_serial=\"YES")) {
										$config['system']['enableserial'] = true;
										write_config(gettext("Restore serial console enabling in configuration."));
									}
									unset($loaderconf);
								}
								/* extract out rrd items, unset from $config when done */
								if ($config['rrddata']) {
									restore_rrddata();
									unset($config['rrddata']);
									unlink_if_exists("{$g['tmp_path']}/config.cache");
									write_config(gettext("Unset RRD data from configuration after restoring full configuration"));
									convert_config();
								}
								if ($m0n0wall_upgrade == true) {
									if ($config['system']['gateway'] <> "") {
										$config['interfaces']['wan']['gateway'] = $config['system']['gateway'];
									}
									unset($config['shaper']);
									/* optional if list */
									$ifdescrs = get_configured_interface_list(true);
									/* remove special characters from interface descriptions */
									if (is_array($ifdescrs)) {
										foreach ($ifdescrs as $iface) {
											$config['interfaces'][$iface]['descr'] = remove_bad_chars($config['interfaces'][$iface]['descr']);
										}
									}
									/* check for interface names with an alias */
									if (is_array($ifdescrs)) {
										foreach ($ifdescrs as $iface) {
											if (is_alias($config['interfaces'][$iface]['descr'])) {
												$origname = $config['interfaces'][$iface]['descr'];
												update_alias_name($origname . "Alias", $origname);
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
										if (count($config['filter']['rule'][$i]['descr']) > 63) {
											$config['filter']['rule'][$i]['descr'] = substr($config['filter']['rule'][$i]['descr'], 0, 63);
										}
									}
									// Move interface from ipsec to enc0
									for ($i = 0; isset($config["filter"]["rule"][$i]); $i++) {
										if ($config['filter']['rule'][$i]['interface'] == "ipsec") {
											$config['filter']['rule'][$i]['interface'] = "enc0";
										}
									}
									// Convert icmp types
									// http://www.openbsd.org/cgi-bin/man.cgi?query=icmp&sektion=4&arch=i386&apropos=0&manpath=OpenBSD+Current
									$convert = array('echo' => 'echoreq', 'timest' => 'timereq', 'timestrep' => 'timerep');
									foreach ($config["filter"]["rule"] as $ruleid => &$ruledata) {
										if ($convert[$ruledata['icmptype']]) {
											$ruledata['icmptype'] = $convert[$ruledata['icmptype']];
										}
									}
									$config['diag']['ipv6nat'] = true;
									write_config(gettext("Imported m0n0wall configuration"));
									convert_config();
									$savemsg = gettext("The m0n0wall configuration has been restored and upgraded to pfSense.");
									mark_subsystem_dirty("restore");
								}
								if (is_array($config['captiveportal'])) {
									foreach ($config['captiveportal'] as $cp) {
										if (isset($cp['enable'])) {
											/* for some reason ipfw doesn't init correctly except on bootup sequence */
											mark_subsystem_dirty("restore");
											break;
										}
									}
								}
								console_configure();
								if (is_interface_mismatch() == true) {
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
			$savemsg = "Package lock cleared.";
		}
	}
}

$id = rand() . '.' . time();

$mth = ini_get('upload_progress_meter.store_method');
$dir = ini_get('upload_progress_meter.file.filename_template');

function build_area_list($showall) {
	global $config;

	$areas = array(
		"aliases" => gettext("Aliases"),
		"captiveportal" => gettext("Captive Portal"),
		"voucher" => gettext("Captive Portal Vouchers"),
		"dnsmasq" => gettext("DNS Forwarder"),
		"unbound" => gettext("DNS Resolver"),
		"dhcpd" => gettext("DHCP Server"),
		"dhcpdv6" => gettext("DHCPv6 Server"),
		"filter" => gettext("Firewall Rules"),
		"interfaces" => gettext("Interfaces"),
		"ipsec" => gettext("IPSEC"),
		"dnshaper" => gettext("Limiters"),
		"nat" => gettext("NAT"),
		"openvpn" => gettext("OpenVPN"),
		"installedpackages" => gettext("Package Manager"),
		"rrddata" => gettext("RRD Data"),
		"cron" => gettext("Scheduled Tasks"),
		"syslog" => gettext("Syslog"),
		"system" => gettext("System"),
		"staticroutes" => gettext("Static routes"),
		"sysctl" => gettext("System tunables"),
		"snmpd" => gettext("SNMP Server"),
		"shaper" => gettext("Traffic Shaper"),
		"vlans" => gettext("VLANS"),
		"wol" => gettext("Wake-on-LAN")
		);

	$list = array("" => gettext("All"));

	if ($showall) {
		return($list + $areas);
	} else {
		foreach ($areas as $area => $areaname) {
			if ($area === "rrddata" || check_and_returnif_section_exists($area) == true) {
				$list[$area] = $areaname;
			}
		}

		return($list);
	}
}

$pgtitle = array(gettext("Diagnostics"), htmlspecialchars(gettext("Backup & Restore")), htmlspecialchars(gettext("Backup & Restore")));
$pglinks = array("", "@self", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('restore')):
?>
	<br/>
	<form action="diag_reboot.php" method="post">
		<input name="Submit" type="hidden" value="Yes" />
		<?php print_info_box(gettext("The firewall configuration has been changed.") . "<br />" . gettext("The firewall is now rebooting.")); ?>
		<br />
	</form>
<?php
endif;

$tab_array = array();
$tab_array[] = array(htmlspecialchars(gettext("Backup & Restore")), true, "diag_backup.php");
$tab_array[] = array(gettext("Config History"), false, "diag_confbak.php");
display_top_tabs($tab_array);

$form = new Form(false);
$form->setMultipartEncoding();	// Allow file uploads

$section = new Form_Section('Backup Configuration');

$section->addInput(new Form_Select(
	'backuparea',
	'Backup area',
	'',
	build_area_list(false)
));

$section->addInput(new Form_Checkbox(
	'nopackages',
	'Skip packages',
	'Do not backup package information.',
	false
));

$section->addInput(new Form_Checkbox(
	'donotbackuprrd',
	'Skip RRD data',
	'Do not backup RRD data (NOTE: RRD Data can consume 4+ megabytes of config.xml space!)',
	true
));

$section->addInput(new Form_Checkbox(
	'encrypt',
	'Encryption',
	'Encrypt this configuration file.',
	false
));

$section->addPassword(new Form_Input(
	'encrypt_password',
	'Password',
	'password',
	null
));

$group = new Form_Group('');
// Note: ID attribute of each element created is to be unique.  Not being used, suppressing it.
$group->add(new Form_Button(
	'download',
	'Download configuration as XML',
	null,
	'fa-download'
))->setAttribute('id')->addClass('btn-primary');

$section->add($group);
$form->add($section);

$section = new Form_Section('Restore Backup');

$section->addInput(new Form_StaticText(
	null,
	sprintf(gettext("Open a %s configuration XML file and click the button below to restore the configuration."), $g['product_name'])
));

$section->addInput(new Form_Select(
	'restorearea',
	'Restore area',
	'',
	build_area_list(true)
));

$section->addInput(new Form_Input(
	'conffile',
	'Configuration file',
	'file',
	null
));

$section->addInput(new Form_Checkbox(
	'decrypt',
	'Encryption',
	'Configuration file is encrypted.',
	false
));

$section->addInput(new Form_Input(
	'decrypt_password',
	'Password',
	'password',
	null,
	['placeholder' => 'Password']
));

$group = new Form_Group('');
// Note: ID attribute of each element created is to be unique.  Not being used, suppressing it.
$group->add(new Form_Button(
	'restore',
	'Restore Configuration',
	null,
	'fa-undo'
))->setHelp('The firewall will reboot after restoring the configuration.')->addClass('btn-danger restore')->setAttribute('id');

$section->add($group);

$form->add($section);

if (($config['installedpackages']['package'] != "") || (is_subsystem_dirty("packagelock"))) {
	$section = new Form_Section('Package Functions');

	if ($config['installedpackages']['package'] != "") {
		$group = new Form_Group('');
		// Note: ID attribute of each element created is to be unique.  Not being used, suppressing it.
		$group->add(new Form_Button(
			'reinstallpackages',
			'Reinstall Packages',
			null,
			'fa-retweet'
		))->setHelp('Click this button to reinstall all system packages.  This may take a while.')->addClass('btn-success')->setAttribute('id');

		$section->add($group);
	}

	if (is_subsystem_dirty("packagelock")) {
		$group = new Form_Group('');
		// Note: ID attribute of each element created is to be unique.  Not being used, suppressing it.
		$group->add(new Form_Button(
			'clearpackagelock',
			'Clear Package Lock',
			null,
			'fa-wrench'
		))->setHelp('Click this button to clear the package lock if a package fails to reinstall properly after an upgrade.')->addClass('btn-warning')->setAttribute('id');

		$section->add($group);
	}

	$form->add($section);
}

print($form);
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// ------- Show/hide sections based on checkbox settings --------------------------------------

	function hideSections(hide) {
		hidePasswords();
	}

	function hidePasswords() {

		encryptHide = !($('input[name="encrypt"]').is(':checked'));
		decryptHide = !($('input[name="decrypt"]').is(':checked'));

		hideInput('encrypt_password', encryptHide);
		hideInput('decrypt_password', decryptHide);
	}

	// ---------- Click handlers ------------------------------------------------------------------

	$('input[name="encrypt"]').on('change', function() {
		hidePasswords();
	});

	$('input[name="decrypt"]').on('change', function() {
		hidePasswords();
	});

	$('#conffile').change(function () {
		if (document.getElementById("conffile").value) {
			$('.restore').prop('disabled', false);
		} else {
			$('.restore').prop('disabled', true);
		}
    });
	// ---------- On initial page load ------------------------------------------------------------

	hideSections();
	$('.restore').prop('disabled', true);
});
//]]>
</script>

<?php
include("foot.inc");

if (is_subsystem_dirty('restore')) {
	system_reboot();
}
