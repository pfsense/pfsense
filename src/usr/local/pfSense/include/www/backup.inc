<?php
/*
 * backup.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

/* Allow additional execution time 0 = no limit. */
ini_set('max_execution_time', '0');
ini_set('max_input_time', '0');

/* omit no-cache headers because it confuses IE with file downloads */
$omit_nocacheheaders = true;
require_once("config.gui.inc");
require_once("config.lib.inc");
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

function backup_xmldatafile($tab=false, $type='voucher') {
	global $g;

	$xmldata_files = glob("{$g['backuppath'][$type]}");
	if (empty($xmldata_files)) {
		return;
	}
	$t = ($tab) ? "\t" : "";
	$result = "{$t}\t<{$type}data>\n";
	foreach ($xmldata_files as $xmldata_file) {
		$basename = basename($xmldata_file);
		$data = file_get_contents($xmldata_file);
		if ($data !== false) {
			$result .= "{$t}\t\t<xmldatafile>\n";
			$result .= "{$t}\t\t\t<filename>{$basename}</filename>\n";
			$result .= "{$t}\t\t\t<data>" . base64_encode(gzdeflate($data)) . "</data>\n";
			$result .= "{$t}\t\t</xmldatafile>\n";
		}
	}
	$result .= "{$t}\t</{$type}data>\n";

	return $result;
}

function ssh_keys_xml() {
	global $ssh_keys, $sshConfigDir;

	$keyfiles = array();
	foreach ($ssh_keys as $key) {
		$keyfiles[] = "ssh_host_{$key['suffix']}key";
		$keyfiles[] = "ssh_host_{$key['suffix']}key.pub";
	}

	$result = "\t<sshdata>\n";
	foreach ($keyfiles as $f2c) {
		$keyfile = "{$sshConfigDir}/{$f2c}";
		if (file_exists($keyfile) || filesize($keyfile) != 0) {
			$result .= "\t\t<sshkeyfile>\n";
			$result .= "\t\t\t<filename>{$f2c}</filename>\n";
			$result .= "\t\t\t<xmldata>" . base64_encode(gzdeflate(file_get_contents($keyfile))) . "</xmldata>\n";
			$result .= "\t\t</sshkeyfile>\n";
		} else {
			return;
		}
	}
	$result .= "\t</sshdata>\n";
	return $result;
}

function check_and_returnif_section_exists($section) {
	global $config;
	if (is_array($config[$section])) {
		return true;
	}
	return false;
}

function execPost($post, $postfiles, $ui = true) {
	global $config, $g;

	unset($input_errors);

	if ($post['restore']) {
		$mode = "restore";
	} else if ($post['download']) {
		$mode = "download";
	}
	if ($post["nopackages"] <> "") {
		$options = "nopackages";
	}

	if ($mode) {
		if ($mode == "download") {
			if ($post['encrypt']) {
				if (!$post['encrypt_password'] || ($post['encrypt_password'] != $post['encrypt_password_confirm'])) {
					$input_errors[] = gettext("Supplied password and confirmation do not match.");
				}
			}

			if (!$input_errors) {
				$host = "{$config['system']['hostname']}.{$config['system']['domain']}";
				$name = "config-{$host}-".date("YmdHis").".xml";
				$data = "";

				if ($options == "nopackages") {
					if (!$post['backuparea']) {
						/* backup entire configuration */
						$data = file_get_contents("{$g['conf_path']}/config.xml");
					} else {
						/* backup specific area of configuration */
						$data = backup_config_section($post['backuparea']);
						$name = "{$post['backuparea']}-{$name}";
					}
					$data = preg_replace('/\t*<installedpackages>.*<\/installedpackages>\n/sm', '', $data);
				} else {
					if (!$post['backuparea']) {
						/* backup entire configuration */
						$data = file_get_contents("{$g['conf_path']}/config.xml");
					} else if ($post['backuparea'] === "rrddata") {
						$data = rrd_data_xml();
						$name = "{$post['backuparea']}-{$name}";
					} else if (array_key_exists($post['backuparea'], $g['backuppath']) && $post['backupdata']) {
						$data = backup_config_section($post['backuparea']);
						$data = clear_tagdata($post['backuparea'], $data);
						$dataxml = backup_xmldatafile(false, $post['backuparea']);
						$closing_tag = "</{$post['backuparea']}>";
						$data = str_replace($closing_tag, $dataxml . $closing_tag, $data);
						$name = "{$post['backuparea']}-{$name}";
					} else {
						/* backup specific area of configuration */
						$data = backup_config_section($post['backuparea']);
						$name = "{$post['backuparea']}-{$name}";
					}
				}

				if ($post['backuparea'] != "rrddata") {
					$data = clear_tagdata('rrd', $data);
				}

				if (!$post['backuparea'] && $post['backupdata']) {
					foreach ($g['backuppath'] as $bk => $path) {
						if (!empty($config[$bk])) {
							$data = clear_tagdata($bk, $data);
							$dataxml = backup_xmldatafile(true, $bk);
							$closing_tag = "\t</{$bk}>";
							$data = str_replace($closing_tag, $dataxml . $closing_tag, $data);
						}
					}
				}

				if (($post['backuparea'] != "rrddata") && !$post['donotbackuprrd']) {
					$rrd_data_xml = rrd_data_xml();
					$closing_tag = "</" . $g['xml_rootobj'] . ">";
					$data = str_replace($closing_tag, $rrd_data_xml . $closing_tag, $data);
				}

				if ($post['backupssh']) {
					$ssh_keys_xml = ssh_keys_xml();
					$closing_tag = "</" . $g['xml_rootobj'] . ">";
					$data = str_replace($closing_tag, $ssh_keys_xml . $closing_tag, $data);
				}

				if ($post['encrypt']) {
					$data = encrypt_data($data, $post['encrypt_password']);
					tagfile_reformat($data, $data, "config.xml");
				}

				if ($ui) {
					send_user_download('data', $data, $name);
				} else {
					return json_encode(array("contents" => base64_encode($data), "name" => $name));
				}
			}
		}

		if ($mode == "restore") {
			if ($post['decrypt']) {
				if (!$post['decrypt_password']) {
					$input_errors[] = gettext("A password for decryption must be supplied and confirmed.");
				}
			}

			if (!$input_errors) {
				if (!$ui || is_uploaded_file($postfiles['conffile']['tmp_name'])) {

					/* read the file contents */
					$data = $ui ? file_get_contents($postfiles['conffile']['tmp_name']) : $postfiles['conffile']['tmp_name'];
					if (!$data) {
						$input_errors[] = gettext("Warning, could not read file {$postfiles['conffile']['tmp_name']}");
					} elseif ($post['decrypt']) {
						if (!tagfile_deformat($data, $data, "config.xml")) {
							$input_errors[] = sprintf(gettext(
							    "The uploaded file does not appear to contain an encrypted %s configuration."),
							    $g['product_label']);
						} else {
							$data = decrypt_data($data, $post['decrypt_password']);
							if (empty($data)) {
								$input_errors[] = gettext("File decryption failed. Incorrect password or file is invalid.");
							}
						}
					}

					/* If the config on disk had empty rrddata tags, remove them to
					 * avoid an XML parsing error.
					 * See https://redmine.pfsense.org/issues/8994 */
					$data = preg_replace("/<rrddata><\\/rrddata>/", "", $data);
					$data = preg_replace("/<rrddata\\/>/", "", $data);

					if ($post['restorearea'] && !$input_errors) {
						/* restore a specific area of the configuration */
						if (!stristr($data, "<" . $post['restorearea'] . ">")) {
							$input_errors[] = gettext("An area to restore was selected but the correct xml tag could not be located.");
						} else {
							if (!restore_config_section($post['restorearea'], $data)) {
								$input_errors[] = gettext("An area to restore was selected but the correct xml tag could not be located.");
							} else {
								$conf_change = false;
								if ($config['rrddata']) {
									restore_rrddata();
									unset($config['rrddata']);
									$conf_change = true;
								}
								if ($config['sshdata']) {
									restore_sshdata();
									unset($config['sshdata']);
									$conf_change = true;
								}
								if (!empty($config[$post['restorearea']][$post['restorearea'].'data'])) {
									restore_xmldatafile($post['restorearea']);
									unset($config[$post['restorearea']][$post['restorearea'].'data']);
									$conf_change = true;
								}
								if ($conf_change) {
									write_config(sprintf(gettext("Unset RRD and extra data from configuration after restoring %s configuration area"), $post['restorearea']));
									unlink_if_exists("{$g['tmp_path']}/config.cache");
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
							file_put_contents($postfiles['conffile']['tmp_name'], $data);
							if (config_install($postfiles['conffile']['tmp_name']) == 0) {
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
								$conf_change = false;
								if ($config['rrddata']) {
									restore_rrddata();
									unset($config['rrddata']);
									$conf_change = true;
								}
								if ($config['sshdata']) {
									restore_sshdata();
									unset($config['sshdata']);
									$conf_change = true;
								}
								foreach ($g['backuppath'] as $bk => $path) {
									if (!empty($config[$bk][$bk.'data'])) {
										restore_xmldatafile($bk);
										unset($config[$bk][$bk.'data']);
										$conf_change = true;
									}
								}
								if ($conf_change) {
									write_config(gettext("Unset RRD and extra data from configuration after full restore."));
									unlink_if_exists("{$g['tmp_path']}/config.cache");
									convert_config();
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
									if ($ui) {
										header("Location: interfaces_assign.php");
									}
									exit;
								}
								if (is_interface_vlan_mismatch() == true) {
									touch("/var/run/interface_mismatch_reboot_needed");
									clear_subsystem_dirty("restore");
									convert_config();
									if ($ui) {
										header("Location: interfaces_assign.php");
									}
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
	}

	return array("input_errors" => $input_errors, "savemsg" => $savemsg);
}

// Compose a list of recent backups formatted as a JSON array
function listBackupsJSON() {
	global $g;

	cleanup_backupcache(false);

	$raw = unserialize(file_get_contents($g["cf_conf_path"] . "/backup/backup.cache"));

	$backups = array();
	foreach($raw as $key => $value) {
	    $backups[] = array("time" => $key, "desc" => $value['description'], "size" => $value['filesize'], "vers" => $value['version']);
	}

	return json_encode($backups);
}

?>
