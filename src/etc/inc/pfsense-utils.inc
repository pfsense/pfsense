<?php
/*
 * pfsense-utils.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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

/****f* pfsense-utils/have_natpfruleint_access
 * NAME
 *   have_natpfruleint_access
 * INPUTS
 *	none
 * RESULT
 *   returns true if user has access to edit a specific firewall nat port forward interface
 ******/
function have_natpfruleint_access($if) {
	$security_url = "firewall_nat_edit.php?if=". strtolower($if);
	if (isAllowedPage($security_url, $allowed)) {
		return true;
	}
	return false;
}

/****f* pfsense-utils/have_ruleint_access
 * NAME
 *   have_ruleint_access
 * INPUTS
 *	none
 * RESULT
 *   returns true if user has access to edit a specific firewall interface
 ******/
function have_ruleint_access($if) {
	$security_url = "firewall_rules.php?if=". strtolower($if);
	if (isAllowedPage($security_url)) {
		return true;
	}
	return false;
}

/****f* pfsense-utils/does_url_exist
 * NAME
 *   does_url_exist
 * INPUTS
 *	none
 * RESULT
 *   returns true if a url is available
 ******/
function does_url_exist($url) {
	$fd = fopen("$url", "r");
	if ($fd) {
		fclose($fd);
		return true;
	} else {
		return false;
	}
}

/****f* pfsense-utils/is_private_ip
 * NAME
 *   is_private_ip
 * INPUTS
 *	none
 * RESULT
 *   returns true if an ip address is in a private range
 ******/
function is_private_ip($iptocheck) {
	$isprivate = false;
	$ip_private_list = array(
		"10.0.0.0/8",
		"100.64.0.0/10",
		"172.16.0.0/12",
		"192.168.0.0/16",
	);
	foreach ($ip_private_list as $private) {
		if (ip_in_subnet($iptocheck, $private) == true) {
			$isprivate = true;
		}
	}
	return $isprivate;
}

/****f* pfsense-utils/get_tmp_file
 * NAME
 *   get_tmp_file
 * INPUTS
 *	none
 * RESULT
 *   returns a temporary filename
 ******/
function get_tmp_file() {
	global $g;
	return "{$g['tmp_path']}/tmp-" . time();
}

/****f* pfsense-utils/get_dns_servers
 * NAME
 *   get_dns_servers - get system dns servers
 * INPUTS
 *   none
 * RESULT
 *   $dns_servers - an array of the dns servers
 ******/
function get_dns_servers() {
	$dns_servers = array();
	if (file_exists("/etc/resolv.conf")) {
		$dns_s = file("/etc/resolv.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	}
	if (is_array($dns_s)) {
		foreach ($dns_s as $dns) {
			$matches = "";
			if (preg_match("/nameserver (.*)/", $dns, $matches)) {
				$dns_servers[] = $matches[1];
			}
		}
	}
	return array_unique($dns_servers);
}

/****f* pfsense-utils/pfSenseHeader
 * NAME
 *   pfSenseHeader
 * INPUTS
 *   none
 * RESULT
 *   Javascript header change or browser Location:
 ******/
function pfSenseHeader($text) {
	global $_SERVER;
	if (isAjax()) {
		if ($_SERVER['HTTPS'] == "on") {
			$protocol = "https";
		} else {
			$protocol = "http";
		}

		$port = ":{$_SERVER['SERVER_PORT']}";
		if ($_SERVER['SERVER_PORT'] == "80" && $protocol == "http") {
			$port = "";
		}
		if ($_SERVER['SERVER_PORT'] == "443" && $protocol == "https") {
			$port = "";
		}
		$complete_url = "{$protocol}://{$_SERVER['HTTP_HOST']}{$port}/{$text}";
		echo "\ndocument.location.href = '{$complete_url}';\n";
	} else {
		header("Location: $text");
	}
}

/****f* pfsense-utils/get_css_files
 * NAME
 *   get_css_files - get a list of the available CSS files (themes)
 * INPUTS
 *   none
 * RESULT
 *   $csslist - an array of the CSS files
 ******/
function get_css_files() {
	$csslist = array();

	// List pfSense files, then any BETA files followed by any user-contributed files
	$cssfiles = glob("/usr/local/www/css/*.css");

	if (is_array($cssfiles)) {
		arsort($cssfiles);
		$usrcss = $pfscss = $betacss = array();

		foreach ($cssfiles as $css) {
			// Don't display any login/logo page related CSS files
			if (strpos($css, "login") == 0 &&
			    strpos($css, "logo") == 0) {
				if (strpos($css, "BETA") != 0) {
					array_push($betacss, $css);
				} else if (strpos($css, "pfSense") != 0) {
					array_push($pfscss, $css);
				} else {
					array_push($usrcss, $css);
				}
			}
		}

		$css = array_merge($pfscss, $betacss, $usrcss);

		foreach ($css as $file) {
			$file = basename($file);
			$csslist[$file] = pathinfo($file, PATHINFO_FILENAME);
		}
	}
	return $csslist;
}

/****f* pfsense-utils/gen_webguicss_field
 * NAME
 *   gen_webguicss_field
 * INPUTS
 *   Pointer to section object
 *   Initial value for the field
 * RESULT
 *   no return value, section object is updated
 ******/
function gen_webguicss_field(&$section, $value) {

	$csslist = get_css_files();

	if (!isset($csslist[$value])) {
		$value = "pfSense.css";
	}

	$section->addInput(new Form_Select(
		'webguicss',
		'Theme',
		$value,
		$csslist
	))->setHelp('Choose an alternative css file (if installed) to change the appearance of the webConfigurator. css files are located in /usr/local/www/css/%s', '<span id="csstxt"></span>');
}
function validate_webguicss_field(&$input_errors, $value) {
	$csslist = get_css_files();
	if (!isset($csslist[$value])) {
		$input_errors[] = gettext("The submitted Theme could not be found. Pick a different theme.");
	}
}

/****f* pfsense-utils/gen_webguifixedmenu_field
 * NAME
 *   gen_webguifixedmenu_field
 * INPUTS
 *   Pointer to section object
 *   Initial value for the field
 * RESULT
 *   no return value, section object is updated
 ******/
function gen_webguifixedmenu_field(&$section, $value) {

	$section->addInput(new Form_Select(
		'webguifixedmenu',
		'Top Navigation',
		$value,
		["" => gettext("Scrolls with page"), "fixed" => gettext("Fixed (Remains visible at top of page)")]
	))->setHelp("The fixed option is intended for large screens only.");
}
function validate_webguifixedmenu_field(&$input_errors, $value) {
	$valid_values = array("", "fixed");
	if (!in_array($value, $valid_values)) {
		$input_errors[] = gettext("The submitted Top Navigation value is invalid.");
	}
}

/****f* pfsense-utils/gen_webguihostnamemenu_field
 * NAME
 *   gen_webguihostnamemenu_field
 * INPUTS
 *   Pointer to section object
 *   Initial value for the field
 * RESULT
 *   no return value, section object is updated
 ******/
function gen_webguihostnamemenu_field(&$section, $value) {

	$section->addInput(new Form_Select(
		'webguihostnamemenu',
		'Hostname in Menu',
		$value,
		["" => gettext("Default (No hostname)"), "hostonly" => gettext("Hostname only"), "fqdn" => gettext("Fully Qualified Domain Name")]
	))->setHelp("Replaces the Help menu title in the Navbar with the system hostname or FQDN.");
}
function validate_webguihostnamemenu_field(&$input_errors, $value) {
	$valid_values = array("", "hostonly", "fqdn");
	if (!in_array($value, $valid_values)) {
		$input_errors[] = gettext("The submitted Hostname in Menu value is invalid.");
	}
}

/****f* pfsense-utils/gen_dashboardcolumns_field
 * NAME
 *   gen_dashboardcolumns_field
 * INPUTS
 *   Pointer to section object
 *   Initial value for the field
 * RESULT
 *   no return value, section object is updated
 ******/
function gen_dashboardcolumns_field(&$section, $value) {

	if (((int) $value < 1) || ((int) $value > 6)) {
		$value = 2;
	}

	$section->addInput(new Form_Input(
		'dashboardcolumns',
		'Dashboard Columns',
		'number',
		$value,
		['min' => 1, 'max' => 6]
	));
}
function validate_dashboardcolumns_field(&$input_errors, $value) {
	if (!is_numericint($value) || ((int) $value < 1) || ((int) $value > 6)) {
		$input_errors[] = gettext("The submitted Dashboard Columns value is invalid.");
	}
}

/****f* pfsense-utils/gen_interfacessort_field
 * NAME
 *   gen_interfacessort_field
 * INPUTS
 *   Pointer to section object
 *   Initial value for the field
 * RESULT
 *   no return value, section object is updated
 ******/
function gen_interfacessort_field(&$section, $value) {

	$section->addInput(new Form_Checkbox(
		'interfacessort',
		'Interfaces Sort',
		'Sort Alphabetically',
		$value
	))->setHelp('If selected, lists of interfaces will be sorted by description, otherwise they are listed wan,lan,optn...');
}

/****f* pfsense-utils/gen_associatedpanels_fields
 * NAME
 *   gen_associatedpanels_fields
 * INPUTS
 *   Pointer to section object
 *   Initial value for each of the fields
 * RESULT
 *   no return value, section object is updated
 ******/
function gen_associatedpanels_fields(&$section, $value1, $value2, $value3, $value4) {

	$group = new Form_Group('Associated Panels Show/Hide');

	$group->add(new Form_Checkbox(
		'dashboardavailablewidgetspanel',
		null,
		'Available Widgets',
		$value1
		))->setHelp('Show the Available Widgets panel on the Dashboard.');

	$group->add(new Form_Checkbox(
		'systemlogsfilterpanel',
		null,
		'Log Filter',
		$value2
	))->setHelp('Show the Log Filter panel in System Logs.');

	$group->add(new Form_Checkbox(
		'systemlogsmanagelogpanel',
		null,
		'Manage Log',
		$value3
	))->setHelp('Show the Manage Log panel in System Logs.');

	$group->add(new Form_Checkbox(
		'statusmonitoringsettingspanel',
		null,
		'Monitoring Settings',
		$value4
	))->setHelp('Show the Settings panel in Status Monitoring.');

	$group->setHelp('These options allow certain panels to be automatically hidden on page load. A control is provided in the title bar to un-hide the panel.');

	$section->add($group);
}

/****f* pfsense-utils/gen_webguileftcolumnhyper_field
 * NAME
 *   gen_webguileftcolumnhyper_field
 * INPUTS
 *   Pointer to section object
 *   Initial value for the field
 * RESULT
 *   no return value, section object is updated
 ******/
function gen_webguileftcolumnhyper_field(&$section, $value) {

	$section->addInput(new Form_Checkbox(
		'webguileftcolumnhyper',
		'Left Column Labels',
		'Active',
		$value
	))->setHelp('If selected, clicking a label in the left column will select/toggle the first item of the group.');
}

/****f* pfsense-utils/gen_disablealiaspopupdetail_field
 * NAME
 *   gen_disablealiaspopupdetail_field
 * INPUTS
 *   Pointer to section object
 *   Initial value for the field
 * RESULT
 *   no return value, section object is updated
 ******/
function gen_disablealiaspopupdetail_field(&$section, $value) {

	$section->addInput(new Form_Checkbox(
		'disablealiaspopupdetail',
		'Alias Popups',
		'Disable details in alias popups',
		$value
	))->setHelp('If selected, the details in alias popups will not be shown, just the alias description (e.g. in Firewall Rules).');
}

/****f* pfsense-utils/gen_pagenamefirst_field
 * NAME
 *   gen_pagenamefirst_field
 * INPUTS
 *   Pointer to section object
 *   Initial value for the field
 * RESULT
 *   no return value, section object is updated
 ******/
function gen_pagenamefirst_field(&$section, $value) {

	$section->addInput(new Form_Checkbox(
		'pagenamefirst',
		'Browser tab text',
		'Display page name first in browser tab',
		$value
	))->setHelp('When this is unchecked, the browser tab shows the host name followed '.
		'by the current page. Check this box to display the current page followed by the '.
		'host name.');
}

/****f* pfsense-utils/gen_user_settings_fields
 * NAME
 *   gen_user_settings_fields
 * INPUTS
 *   Pointer to section object
 *   Array of initial values for the fields
 * RESULT
 *   no return value, section object is updated
 ******/
function gen_user_settings_fields(&$section, $pconfig) {

	gen_webguicss_field($section, $pconfig['webguicss']);
	gen_webguifixedmenu_field($section, $pconfig['webguifixedmenu']);
	gen_webguihostnamemenu_field($section, $pconfig['webguihostnamemenu']);
	gen_dashboardcolumns_field($section, $pconfig['dashboardcolumns']);
	gen_interfacessort_field($section, $pconfig['interfacessort']);
	gen_associatedpanels_fields(
		$section,
		$pconfig['dashboardavailablewidgetspanel'],
		$pconfig['systemlogsfilterpanel'],
		$pconfig['systemlogsmanagelogpanel'],
		$pconfig['statusmonitoringsettingspanel']);
	gen_webguileftcolumnhyper_field($section, $pconfig['webguileftcolumnhyper']);
	gen_disablealiaspopupdetail_field($section, $pconfig['disablealiaspopupdetail']);
	gen_pagenamefirst_field($section, $pconfig['pagenamefirst']);
}

/****f* pfsense-utils/gen_requirestatefilter_field
 * NAME
 *   gen_requirestatefilter_field
 * INPUTS
 *   Pointer to section object
 *   Initial value for the field
 * RESULT
 *   no return value, section object is updated
 ******/
function gen_requirestatefilter_field(&$section, $value) {
	$section->addInput(new Form_Checkbox(
		'requirestatefilter',
		'Require State Filter',
		'Do not display state table without a filter',
		$value
	))->setHelp('By default, the entire state table is displayed when entering '.
		'Diagnostics > States. This option requires a filter to be entered '.
		'before the states are displayed. Useful for systems with large state tables.');
}

/****f* pfsense-utils/gen_created_updated_fields
 * NAME
 *   gen_created_updated_fields
 * INPUTS
 *   Pointer to form object
 *   Array of created time and username
 *   Array of updated time and username
 * RESULT
 *   no return value, section object is added to form if needed
 ******/
function gen_created_updated_fields(&$form, $created, $updated, $tracker = 0) {
	$has_created_time = (isset($created['time']) && isset($created['username']));
	$has_updated_time = (isset($updated['time']) && isset($updated['username']));

	if ($has_created_time || $has_updated_time) {
		$section = new Form_Section('Rule Information');

		if (!empty($tracker)) {
			$section->addInput(new Form_StaticText(
				'Tracking ID',
				htmlspecialchars($tracker)
			));
		}

		if ($has_created_time) {
			$section->addInput(new Form_StaticText(
				'Created',
				htmlspecialchars(sprintf(
					gettext('%1$s by %2$s'),
					date(gettext("n/j/y H:i:s"), $created['time']),
					$created['username']))
			));
		}

		if ($has_updated_time) {
			$section->addInput(new Form_StaticText(
				'Updated',
				htmlspecialchars(sprintf(
					gettext('%1$s by %2$s'),
					date(gettext("n/j/y H:i:s"), $updated['time']),
					$updated['username']))
			));
		}

		$form->add($section);
	}
}

function hardware_offloading_applyflags($iface) {
	global $config;

	$flags_on = 0;
	$flags_off = 0;
	$options = pfSense_get_interface_addresses($iface);

	if (isset($config['system']['disablechecksumoffloading'])) {
		if (isset($options['encaps']['txcsum'])) {
			$flags_off |= IFCAP_TXCSUM;
		}
		if (isset($options['encaps']['rxcsum'])) {
			$flags_off |= IFCAP_RXCSUM;
		}
		if (isset($options['encaps']['txcsum6'])) {
			$flags_off |= IFCAP_TXCSUM_IPV6;
		}
		if (isset($options['encaps']['rxcsum6'])) {
			$flags_off |= IFCAP_RXCSUM_IPV6;
		}
	} else {
		if (isset($options['caps']['txcsum'])) {
			$flags_on |= IFCAP_TXCSUM;
		}
		if (isset($options['caps']['rxcsum'])) {
			$flags_on |= IFCAP_RXCSUM;
		}
		if (isset($options['caps']['txcsum6'])) {
			$flags_on |= IFCAP_TXCSUM_IPV6;
		}
		if (isset($options['caps']['rxcsum6'])) {
			$flags_on |= IFCAP_RXCSUM_IPV6;
		}
	}

	if (isset($config['system']['disablesegmentationoffloading'])) {
		$flags_off |= IFCAP_TSO;
	} else if (isset($options['caps']['tso']) || isset($options['caps']['tso4']) || isset($options['caps']['tso6'])) {
		$flags_on |= IFCAP_TSO;
	}

	if (isset($config['system']['disablelargereceiveoffloading'])) {
		$flags_off |= IFCAP_LRO;
	} else if (isset($options['caps']['lro'])) {
		$flags_on |= IFCAP_LRO;
	}

	pfSense_interface_capabilities($iface, -$flags_off);
	pfSense_interface_capabilities($iface, $flags_on);
}

/****f* pfsense-utils/enable_hardware_offloading
 * NAME
 *   enable_hardware_offloading - Enable a NIC's supported hardware features.
 * INPUTS
 *   $interface	- string containing the physical interface to work on.
 * RESULT
 *   null
 * NOTES
 *   This function only supports the fxp driver's loadable microcode.
 ******/
function enable_hardware_offloading($interface) {
	global $g, $config;

	$int = get_real_interface($interface);
	if (empty($int)) {
		return;
	}

	if (!isset($config['system']['do_not_use_nic_microcode'])) {
		/* translate wan, lan, opt -> real interface if needed */
		$int_family = preg_split("/[0-9]+/", $int);
		$supported_ints = array('fxp');
		if (in_array($int_family, $supported_ints)) {
			if (does_interface_exist($int)) {
				pfSense_interface_flags($int, IFF_LINK0);
			}
		}
	}

	/* This is mostly for vlans and ppp types */
	$realhwif = get_parent_interface($interface);
	if ($realhwif[0] == $int) {
		hardware_offloading_applyflags($int);
	} else {
		hardware_offloading_applyflags($realhwif[0]);
		hardware_offloading_applyflags($int);
	}
}

/****f* pfsense-utils/is_alias_inuse
 * NAME
 *   checks to see if an alias is currently in use by a rule
 * INPUTS
 *
 * RESULT
 *   true or false
 * NOTES
 *
 ******/
function is_alias_inuse($alias) {
	global $g, $config;

	if ($alias == "") {
		return false;
	}
	/* loop through firewall rules looking for alias in use */
	if (is_array($config['filter']['rule'])) {
		foreach ($config['filter']['rule'] as $rule) {
			if ($rule['source']['address']) {
				if ($rule['source']['address'] == $alias) {
					return true;
				}
			}
			if ($rule['destination']['address']) {
				if ($rule['destination']['address'] == $alias) {
					return true;
				}
			}
		}
	}
	/* loop through nat rules looking for alias in use */
	if (is_array($config['nat']['rule'])) {
		foreach ($config['nat']['rule'] as $rule) {
			if ($rule['target'] && $rule['target'] == $alias) {
				return true;
			}
			if ($rule['source']['address'] && $rule['source']['address'] == $alias) {
				return true;
			}
			if ($rule['destination']['address'] && $rule['destination']['address'] == $alias) {
				return true;
			}
		}
	}
	return false;
}

/****f* pfsense-utils/is_schedule_inuse
 * NAME
 *   checks to see if a schedule is currently in use by a rule
 * INPUTS
 *
 * RESULT
 *   true or false
 * NOTES
 *
 ******/
function is_schedule_inuse($schedule) {
	global $g, $config;

	if ($schedule == "") {
		return false;
	}
	/* loop through firewall rules looking for schedule in use */
	if (is_array($config['filter']['rule'])) {
		foreach ($config['filter']['rule'] as $rule) {
			if ($rule['sched'] == $schedule) {
				return true;
			}
		}
	}
	return false;
}

/****f* pfsense-utils/setup_microcode
 * NAME
 *   enumerates all interfaces and calls enable_hardware_offloading which
 *   enables a NIC's supported hardware features.
 * INPUTS
 *
 * RESULT
 *   null
 * NOTES
 *   This function only supports the fxp driver's loadable microcode.
 ******/
function setup_microcode() {

	/* if list */
	$iflist = get_configured_interface_list(true);
	foreach ($iflist as $if => $ifdescr) {
		enable_hardware_offloading($if);
	}
	unset($iflist);
}

/****f* pfsense-utils/get_carp_status
 * NAME
 *   get_carp_status - Return whether CARP is enabled or disabled.
 * RESULT
 *   boolean	- true if CARP is enabled, false if otherwise.
 ******/
function get_carp_status() {
	/* grab the current status of carp */
	$status = get_single_sysctl('net.inet.carp.allow');
	return (intval($status) > 0);
}

/*
 * convert_ip_to_network_format($ip, $subnet): converts an ip address to network form

 */
function convert_ip_to_network_format($ip, $subnet) {
	$ipsplit = explode('.', $ip);
	$string = $ipsplit[0] . "." . $ipsplit[1] . "." . $ipsplit[2] . ".0/" . $subnet;
	return $string;
}

/*
 * get_carp_interface_status($carpid): returns the status of a carp uniqid
 */
function get_carp_interface_status($carpid) {

	$carpiface = get_configured_vip_interface($carpid);
	if ($carpiface == NULL)
		return "";
	$interface = get_real_interface($carpiface);
	if ($interface == NULL)
		return "";
	$vip = get_configured_vip($carpid);
	if ($vip == NULL || !isset($vip['vhid']))
		return "";

	$vhid = $vip['vhid'];
	$carp_query = '';
	$_gb = exec("/sbin/ifconfig {$interface} | /usr/bin/grep \"carp:.* vhid {$vhid} \"", $carp_query);
	foreach ($carp_query as $int) {
		if (stripos($int, "MASTER"))
			return "MASTER";
		elseif (stripos($int, "BACKUP"))
			return "BACKUP";
		elseif (stripos($int, "INIT"))
			return "INIT";
	}

	return "";
}

/*
 * get_pfsync_interface_status($pfsyncinterface): returns the status of a pfsync
 */
function get_pfsync_interface_status($pfsyncinterface) {
	if (!does_interface_exist($pfsyncinterface)) {
		return;
	}

	return exec_command("/sbin/ifconfig {$pfsyncinterface} | /usr/bin/awk '/pfsync:/ {print \$5}'");
}

/*
 * add_rule_to_anchor($anchor, $rule): adds the specified rule to an anchor
 */
function add_rule_to_anchor($anchor, $rule, $label) {
	mwexec("echo " . escapeshellarg($rule) . " | /sbin/pfctl -a " . escapeshellarg($anchor) . ":" . escapeshellarg($label) . " -f -");
}

/*
 * remove_text_from_file
 * remove $text from file $file
 */
function remove_text_from_file($file, $text) {
	if (!file_exists($file) && !is_writable($file)) {
		return;
	}
	$filecontents = file_get_contents($file);
	$text = str_replace($text, "", $filecontents);
	@file_put_contents($file, $text);
}

/*
 *   after_sync_bump_adv_skew(): create skew values by 1S
 */
function after_sync_bump_adv_skew() {
	global $config, $g;
	$processed_skew = 1;
	init_config_arr(array('virtualip', 'vip'));
	$a_vip = &$config['virtualip']['vip'];
	foreach ($a_vip as $vipent) {
		if ($vipent['advskew'] <> "") {
			$processed_skew = 1;
			$vipent['advskew'] = $vipent['advskew']+1;
		}
	}
	if ($processed_skew == 1) {
		write_config(gettext("After synch increase advertising skew"));
	}
}

/*
 * get_filename_from_url($url): converts a url to its filename.
 */
function get_filename_from_url($url) {
	return basename($url);
}

/*
 *   get_dir: return an array of $dir
 */
function get_dir($dir) {
	$dir_array = array();
	$d = dir($dir);
	if (!is_object($d)) {
		return array();
	}
	while (false !== ($entry = $d->read())) {
		array_push($dir_array, $entry);
	}
	$d->close();
	return $dir_array;
}

/****f* pfsense-utils/WakeOnLan
 * NAME
 *   WakeOnLan - Wake a machine up using the wake on lan format/protocol
 * RESULT
 *   true/false - true if the operation was successful
 ******/
function WakeOnLan($addr, $mac) {
	$addr_byte = explode(':', $mac);
	$hw_addr = '';

	for ($a = 0; $a < 6; $a++) {
		$hw_addr .= chr(hexdec($addr_byte[$a]));
	}

	$msg = chr(255).chr(255).chr(255).chr(255).chr(255).chr(255);

	for ($a = 1; $a <= 16; $a++) {
		$msg .= $hw_addr;
	}

	// send it to the broadcast address using UDP
	$s = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	if ($s == false) {
		log_error(gettext("Error creating socket!"));
		log_error(sprintf(gettext("Error code is '%1\$s' - %2\$s"), socket_last_error($s), socket_strerror(socket_last_error($s))));
	} else {
		// setting a broadcast option to socket:
		$opt_ret = socket_set_option($s, 1, 6, TRUE);
		if ($opt_ret < 0) {
			log_error(sprintf(gettext("setsockopt() failed, error: %s"), strerror($opt_ret)));
		}
		$e = socket_sendto($s, $msg, strlen($msg), 0, $addr, 2050);
		socket_close($s);
		log_error(sprintf(gettext('Magic Packet sent (%1$s) to (%2$s) MAC=%3$s'), $e, $addr, $mac));
		return true;
	}

	return false;
}

/*
 * reverse_strrchr($haystack, $needle):  Return everything in $haystack up to the *last* instance of $needle.
 *					 Useful for finding paths and stripping file extensions.
 */
function reverse_strrchr($haystack, $needle) {
	if (!is_string($haystack)) {
		return;
	}
	return strrpos($haystack, $needle) ? substr($haystack, 0, strrpos($haystack, $needle) +1) : false;
}

/*
 *  backup_config_section($section): returns as an xml file string of
 *                                   the configuration section
 */
function backup_config_section($section_name) {
	global $config;
	$new_section = &$config[$section_name];
	/* generate configuration XML */
	$xmlconfig = dump_xml_config($new_section, $section_name);
	$xmlconfig = str_replace("<?xml version=\"1.0\"?>", "", $xmlconfig);
	return $xmlconfig;
}

/*
 *  restore_config_section($section_name, new_contents): restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.
 */
function restore_config_section($section_name, $new_contents) {
	global $config, $g;
	$fout = fopen("{$g['tmp_path']}/tmpxml", "w");
	fwrite($fout, $new_contents);
	fclose($fout);

	$xml = parse_xml_config($g['tmp_path'] . "/tmpxml", null);
	if ($xml['pfsense']) {
		$xml = $xml['pfsense'];
	}
	else if ($xml['m0n0wall']) {
		$xml = $xml['m0n0wall'];
	}
	if ($xml[$section_name]) {
		$section_xml = $xml[$section_name];
	} else {
		$section_xml = -1;
	}

	@unlink($g['tmp_path'] . "/tmpxml");
	if ($section_xml === -1) {
		return false;
	}

	/* Save current pkg repo to re-add on new config */
	unset($pkg_repo_conf_path);
	if ($section_name == "system" &&
	    isset($config['system']['pkg_repo_conf_path'])) {
		$pkg_repo_conf_path = $config['system']['pkg_repo_conf_path'];
	}

	$config[$section_name] = &$section_xml;
	if (file_exists("{$g['tmp_path']}/config.cache")) {
		unlink("{$g['tmp_path']}/config.cache");
	}

	/* Restore previously pkg repo configured */
	if ($section_name == "system") {
		if (isset($pkg_repo_conf_path)) {
			$config['system']['pkg_repo_conf_path'] =
			    $pkg_repo_conf_path;
		} elseif (isset($config['system']['pkg_repo_conf_path'])) {
			unset($config['system']['pkg_repo_conf_path']);
		}
	}

	write_config(sprintf(gettext("Restored %s of config file (maybe from CARP partner)"), $section_name));
	disable_security_checks();
	return true;
}

/*
 *  merge_config_section($section_name, new_contents):   restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.  But preserve the prior
 * 													structure if needed
 */
function merge_config_section($section_name, $new_contents) {
	global $config;
	$fname = get_tmp_filename();
	$fout = fopen($fname, "w");
	fwrite($fout, $new_contents);
	fclose($fout);
	$section_xml = parse_xml_config($fname, $section_name);
	$config[$section_name] = $section_xml;
	unlink($fname);
	write_config(sprintf(gettext("Restored %s of config file (maybe from CARP partner)"), $section_name));
	disable_security_checks();
	return;
}

/*
 * rmdir_recursive($path, $follow_links=false)
 * Recursively remove a directory tree (rm -rf path)
 * This is for directories _only_
 */
function rmdir_recursive($path, $follow_links=false) {
	$to_do = glob($path);
	if (!is_array($to_do)) {
		$to_do = array($to_do);
	}
	foreach ($to_do as $workingdir) { // Handle wildcards by foreaching.
		if (file_exists($workingdir)) {
			if (is_dir($workingdir)) {
				$dir = opendir($workingdir);
				while ($entry = readdir($dir)) {
					if (is_file("$workingdir/$entry") || ((!$follow_links) && is_link("$workingdir/$entry"))) {
						unlink("$workingdir/$entry");
					} elseif (is_dir("$workingdir/$entry") && $entry != '.' && $entry != '..') {
						rmdir_recursive("$workingdir/$entry");
					}
				}
				closedir($dir);
				rmdir($workingdir);
			} elseif (is_file($workingdir)) {
				unlink($workingdir);
			}
		}
	}
	return;
}

/*
 * host_firmware_version(): Return the versions used in this install
 */
function host_firmware_version($tocheck = "") {
	global $g, $config;

	$os_version = trim(substr(php_uname("r"), 0, strpos(php_uname("r"), '-')));

	return array(
		"firmware" => array("version" => $g['product_version']),
		"kernel"   => array("version" => $os_version),
		"base"     => array("version" => $os_version),
		"platform" => $g['platform'],
		"config_version" => $config['version']
	);
}

function get_disk_info() {
	$diskout = "";
	exec("/bin/df -h | /usr/bin/grep -w '/' | /usr/bin/awk '{ print $2, $3, $4, $5 }'", $diskout);
	return explode(' ', $diskout[0]);
}

/****f* pfsense-utils/strncpy
 * NAME
 *   strncpy - copy strings
 * INPUTS
 *   &$dst, $src, $length
 * RESULT
 *   none
 ******/
function strncpy(&$dst, $src, $length) {
	if (strlen($src) > $length) {
		$dst = substr($src, 0, $length);
	} else {
		$dst = $src;
	}
}

/****f* pfsense-utils/reload_interfaces_sync
 * NAME
 *   reload_interfaces - reload all interfaces
 * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_interfaces_sync() {
	global $config, $g;

	if ($g['debug']) {
		log_error(gettext("reload_interfaces_sync() is starting."));
	}

	/* parse config.xml again */
	$config = parse_config(true);

	/* enable routing */
	system_routing_enable();
	if ($g['debug']) {
		log_error(gettext("Enabling system routing"));
	}

	if ($g['debug']) {
		log_error(gettext("Cleaning up Interfaces"));
	}

	/* set up interfaces */
	interfaces_configure();
}

/****f* pfsense-utils/reload_all
 * NAME
 *   reload_all - triggers a reload of all settings
 *   * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_all() {
	send_event("service reload all");
}

/****f* pfsense-utils/reload_interfaces
 * NAME
 *   reload_interfaces - triggers a reload of all interfaces
 * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_interfaces() {
	send_event("interface all reload");
}

/****f* pfsense-utils/reload_all_sync
 * NAME
 *   reload_all - reload all settings
 *   * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_all_sync() {
	global $config, $g;

	/* parse config.xml again */
	$config = parse_config(true);

	/* set up our timezone */
	system_timezone_configure();

	/* set up our hostname */
	system_hostname_configure();

	/* make hosts file */
	system_hosts_generate();

	/* generate resolv.conf */
	system_resolvconf_generate();

	/* enable routing */
	system_routing_enable();

	/* set up interfaces */
	interfaces_configure();

	/* start dyndns service */
	services_dyndns_configure();

	/* configure cron service */
	configure_cron();

	/* start the NTP client */
	system_ntp_configure();

	/* sync pw database */
	unlink_if_exists("/etc/spwd.db.tmp");
	mwexec("/usr/sbin/pwd_mkdb -d /etc/ /etc/master.passwd");

	/* restart sshd */
	send_event("service restart sshd");

	/* restart webConfigurator if needed */
	send_event("service restart webgui");
}

function load_loader_conf($loader_conf = NULL, $local = false) {

	if ($loader_conf == NULL) {
		return (NULL);
	}
	if (file_exists($loader_conf)) {
		$input = file_get_contents($loader_conf);
	} else {
		$input = "";
	}

	$input_split = explode("\n", $input);

	/*
	 * Loop through and only add lines that are not empty and not
	 * managed by us.
	 */
	$data = array();
	/* These values should be removed from loader.conf and loader.conf.local
	 * As they will be replaced when necessary. */
	$remove = array("hw.usb.no_pf", "hint.mdio.0.at", "hint.e6000sw.0",
	    "hw.e6000sw.default_disabled", "vm.pmap.pti",
	    "net.pf.request_maxcount");
	if (!$local) {
		/* These values should only be filtered in loader.conf, not .local */
		$remove = array_merge($remove,
		    array("autoboot_delay", "console", "comconsole_speed",
		    "boot_multicons", "boot_serial", "hint.uart.0.flags",
		    "hint.uart.1.flags"));
	}
	foreach ($input_split as $line) {
		if (empty($line)) {
			continue;
		}
		$skip = false;
		list($name, $value) = explode('=', $line, 2);
		foreach($remove as $rid => $rkey) {
			if (strncasecmp(trim($name), $rkey, strlen($rkey)) == 0) {
				$skip = true;
				break;
			}
		}
		if (!$skip) {
			$data[] = $line;
		}
	}

	return ($data);
}

function setup_loader_settings($path = "", $upgrade = false) {
	global $g, $config;

	$boot_config_file = "{$path}/boot.config";
	$loader_conf_file = "{$path}/boot/loader.conf";

	$serialspeed = (is_numeric($config['system']['serialspeed'])) ? $config['system']['serialspeed'] : "115200";

	$vga_only = false;
	$serial_only = false;
	$specific_platform = system_identify_specific_platform();
	$video_console_type = (get_single_sysctl("machdep.bootmethod") == "UEFI") ? "efi" : "vidconsole";
	if ($specific_platform['name'] == 'XG-1540') {
		$vga_only = true;
	} elseif ($specific_platform['name'] == 'Turbot Dual-E') {
		$g['primaryconsole_force'] = "video";
	} elseif ($specific_platform['name'] == 'RCC-VE' ||
	    $specific_platform['name'] == 'RCC' ||
	    $specific_platform['name'] == 'SG-2220' ||
	    $specific_platform['name'] == 'apu2') {
		$serial_only = true;
	}

	/* Serial console - write out /boot.config */
	if (file_exists($boot_config_file)) {
		$boot_config = file_get_contents($boot_config_file);
	} else {
		$boot_config = "";
	}
	$boot_config_split = explode("\n", $boot_config);
	$data = array();
	foreach ($boot_config_split as $bcs) {
		/* Ignore -D and -h lines now */
		if (!empty($bcs) && !stristr($bcs, "-D") &&
		    !stristr($bcs, "-h")) {
			$data[] = $bcs;
		}
	}
	if ($serial_only === true) {
		$data[] = "-S{$serialspeed} -h";
	} elseif (is_serial_enabled()) {
		$data[] = "-S{$serialspeed} -D";
	}

	if (empty($data)) {
		@unlink($boot_conf_file);
	} else {
		safe_write_file($boot_config_file, $data);
	}
	unset($data, $boot_config, $boot_config_file, $boot_config_split);

	/* Serial console - write out /boot/loader.conf */
	if ($upgrade) {
		system("echo \"Reading {$loader_conf_file}...\" >> /conf/upgrade_log.txt");
	}

	$data = load_loader_conf($loader_conf_file, false);
	if ($serial_only === true) {
		$data[] = 'boot_serial="YES"';
		$data[] = 'console="comconsole"';
		$data[] = 'comconsole_speed="' . $serialspeed . '"';
	} elseif ($vga_only === true) {
		$data[] = "console=\"{$video_console_type}\"";
	} elseif (is_serial_enabled()) {
		$data[] = 'boot_multicons="YES"';
		$data[] = 'boot_serial="YES"';
		$primaryconsole = isset($g['primaryconsole_force']) ?
		    $g['primaryconsole_force'] :
		    $config['system']['primaryconsole'];
		switch ($primaryconsole) {
			case "video":
				$data[] = "console=\"{$video_console_type},comconsole\"";
				break;
			case "serial":
			default:
				$data[] = "console=\"comconsole,{$video_console_type}\"";
		}
		$data[] = 'comconsole_speed="' . $serialspeed . '"';
	}

	if ($specific_platform['name'] == 'RCC-VE' ||
	    $specific_platform['name'] == 'RCC' ||
	    $specific_platform['name'] == 'SG-2220') {
		$data[] = 'comconsole_port="0x2F8"';
		$data[] = 'hint.uart.0.flags="0x00"';
		$data[] = 'hint.uart.1.flags="0x10"';
	}
	$data[] = 'autoboot_delay="3"';
	$data[] = 'hw.usb.no_pf="1"';
	if (isset($config['system']['pti_disabled'])) {
		$data[] = 'vm.pmap.pti="0"';
	}

	if (isset($config['system']['maximumtableentries'])) {
		$maximumtableentries = $config['system']['maximumtableentries'];
	} else {
		$maximumtableentries = pfsense_default_table_entries_size();
	}
	$data[] = 'net.pf.request_maxcount="' .  $maximumtableentries . '"';

	safe_write_file($loader_conf_file, $data);

	/* Filter loader.conf.local to avoid duplicate settings. */
	$loader_conf_file = "{$path}/boot/loader.conf.local";
	$data = load_loader_conf($loader_conf_file, true);
	if (empty($data)) {
		@unlink($loader_conf_file);
	} else {
		safe_write_file($loader_conf_file, $data);
	}

}

function console_configure($when = "save", $path = "") {
	global $config;
	$ttys_file = "{$path}/etc/ttys";

	/* Update the loader settings. */
	setup_loader_settings($path, ($when == "upgrade"));

	$ttys = file_get_contents($ttys_file);
	$ttys_split = explode("\n", $ttys);

	$data = array();

	$on_off = (is_serial_enabled() ? 'onifconsole' : 'off');

	if (isset($config['system']['disableconsolemenu'])) {
		$console_type = 'Pc';
		$serial_type = '3wire';
	} else {
		$console_type = 'al.Pc';
		$serial_type = 'al.3wire';
	}

	$console_line = "console\tnone\t\t\t\tunknown\toff\tsecure";
	$ttyv0_line =
	    "ttyv0\t\"/usr/libexec/getty {$console_type}\"\txterm\tonifexists secure";
	$ttyu_line =
	    "\"/usr/libexec/getty {$serial_type}\"\tvt100\t{$on_off}\tsecure";

	$found = array();

	foreach ($ttys_split as $tty) {
		/* Ignore blank lines */
		if (empty($tty)) {
			continue;
		}

		if (stristr($tty, "ttyv0")) {
			$found['ttyv0'] = 1;
			$data[] = $ttyv0_line;
		} elseif (stristr($tty, "ttyu")) {
			$ttyn = substr($tty, 0, 5);
			$found[$ttyn] = 1;
			$data[] = "{$ttyn}\t{$ttyu_line}";
		} elseif (substr($tty, 0, 7) == 'console') {
			$found['console'] = 1;
			$data[] = $tty;
		} else {
			$data[] = $tty;
		}
	}
	unset($on_off, $console_type, $serial_type);

	/* Detect missing main lines on original file and try to rebuild it */
	$items = array(
		'console',
		'ttyv0',
		'ttyu0',
		'ttyu1',
		'ttyu2',
		'ttyu3'
	);

	foreach ($items as $item) {
		if (isset($found[$item])) {
			continue;
		}

		if ($item == 'console') {
			$data[] = $console_line;
		} elseif ($item == 'ttyv0') {
			$data[] = $ttyv0_line;
		} else {
			$data[] = "{$item}\t{$ttyu_line}";
		}
	}

	safe_write_file($ttys_file, $data);

	unset($ttys, $ttys_file, $ttys_split, $data);

	if ($when != "upgrade") {
		reload_ttys();
	}

	return;
}

function is_serial_enabled() {
	global $g, $config;

	if (!isset($g['enableserial_force']) &&
	    !isset($config['system']['enableserial'])) {
		return false;
	}

	return true;
}

function reload_ttys() {
	// Send a HUP signal to init will make it reload /etc/ttys
	posix_kill(1, SIGHUP);
}

function print_value_list($list, $count = 10, $separator = ",") {
	$list = implode($separator, array_slice($list, 0, $count));
	if (count($list) < $count) {
		$list .= ".";
	} else {
		$list .= "...";
	}
	return $list;
}

/* DHCP enabled on any interfaces? */
function is_dhcp_server_enabled() {
	global $config;

	if (!is_array($config['dhcpd'])) {
		return false;
	}

	foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
		if (isset($dhcpifconf['enable']) && !empty($config['interfaces'][$dhcpif])) {
			return true;
		}
	}

	return false;
}

/* DHCP enabled on any interfaces? */
function is_dhcpv6_server_enabled() {
	global $config;

	if (is_array($config['interfaces'])) {
		foreach ($config['interfaces'] as $ifcfg) {
			if (isset($ifcfg['enable']) && !empty($ifcfg['track6-interface'])) {
				return true;
			}
		}
	}

	if (!is_array($config['dhcpdv6'])) {
		return false;
	}

	foreach ($config['dhcpdv6'] as $dhcpv6if => $dhcpv6ifconf) {
		if (isset($dhcpv6ifconf['enable']) && !empty($config['interfaces'][$dhcpv6if])) {
			return true;
		}
	}

	return false;
}

/* radvd enabled on any interfaces? */
function is_radvd_enabled() {
	global $config;

	if (!is_array($config['dhcpdv6'])) {
		$config['dhcpdv6'] = array();
	}

	$dhcpdv6cfg = $config['dhcpdv6'];
	$Iflist = get_configured_interface_list();

	/* handle manually configured DHCP6 server settings first */
	foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {
		if (!isset($config['interfaces'][$dhcpv6if]['enable'])) {
			continue;
		}

		if (!isset($dhcpv6ifconf['ramode'])) {
			$dhcpv6ifconf['ramode'] = $dhcpv6ifconf['mode'];
		}

		if ($dhcpv6ifconf['ramode'] == "disabled") {
			continue;
		}

		$ifcfgipv6 = get_interface_ipv6($dhcpv6if);
		if (!is_ipaddrv6($ifcfgipv6)) {
			continue;
		}

		return true;
	}

	/* handle DHCP-PD prefixes and 6RD dynamic interfaces */
	foreach ($Iflist as $if => $ifdescr) {
		if (!isset($config['interfaces'][$if]['track6-interface'])) {
			continue;
		}
		if (!isset($config['interfaces'][$if]['enable'])) {
			continue;
		}

		$ifcfgipv6 = get_interface_ipv6($if);
		if (!is_ipaddrv6($ifcfgipv6)) {
			continue;
		}

		$ifcfgsnv6 = get_interface_subnetv6($if);
		$subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);

		if (!is_ipaddrv6($subnetv6)) {
			continue;
		}

		return true;
	}

	return false;
}

/* Any PPPoE servers enabled? */
function is_pppoe_server_enabled() {
	global $config;

	$pppoeenable = false;

	if (!is_array($config['pppoes']) || !is_array($config['pppoes']['pppoe'])) {
		return false;
	}

	foreach ($config['pppoes']['pppoe'] as $pppoes) {
		if ($pppoes['mode'] == 'server') {
			$pppoeenable = true;
		}
	}

	return $pppoeenable;
}

/* Optional arg forces hh:mm:ss without days */
function convert_seconds_to_dhms($sec, $showhoursonly = false) {
	if (!is_numericint($sec)) {
		return '-';
	}
	// FIXME: When we move to PHP 7 we can use "intdiv($sec % X, Y)" etc
	list($d, $h, $m, $s) = array(	(int)($showhoursonly ? 0 : $sec/86400),
					(int)(($showhoursonly ? $sec : $sec % 86400)/3600),
					(int)(($sec % 3600)/60),
					$sec % 60
				);
	return ($d > 0 ? $d . 'd ' : '') . sprintf('%02d:%02d:%02d', $h, $m, $s);
}

/* Compute the total uptime from the ppp uptime log file in the conf directory */

function get_ppp_uptime($port) {
	if (file_exists("/conf/{$port}.log")) {
		$saved_time = file_get_contents("/conf/{$port}.log");
		$uptime_data = explode("\n", $saved_time);
		$sec = 0;
		foreach ($uptime_data as $upt) {
			$sec += substr($upt, 1 + strpos($upt, " "));
		}
		return convert_seconds_to_dhms($sec);
	} else {
		$total_time = gettext("No history data found!");
		return $total_time;
	}
}

//returns interface information
function get_interface_info($ifdescr) {
	global $config, $g;

	$ifinfo = array();
	if (empty($config['interfaces'][$ifdescr])) {
		return;
	}
	$ifinfo['hwif'] = $config['interfaces'][$ifdescr]['if'];
	$ifinfo['enable'] = isset($config['interfaces'][$ifdescr]['enable']);
	$ifinfo['if'] = get_real_interface($ifdescr);

	$chkif = $ifinfo['if'];
	$ifinfotmp = pfSense_get_interface_addresses($chkif);
	$ifinfo['status'] = $ifinfotmp['status'];
	if (empty($ifinfo['status'])) {
		$ifinfo['status'] = "down";
	}
	$ifinfo['macaddr'] = $ifinfotmp['macaddr'];
	$ifinfo['mtu'] = $ifinfotmp['mtu'];
	$ifinfo['ipaddr'] = $ifinfotmp['ipaddr'];
	$ifinfo['subnet'] = $ifinfotmp['subnet'];
	$ifinfo['linklocal'] = get_interface_linklocal($ifdescr);
	$ifinfo['ipaddrv6'] = get_interface_ipv6($ifdescr);
	$ifinfo['subnetv6'] = get_interface_subnetv6($ifdescr);
	if (isset($ifinfotmp['link0'])) {
		$link0 = "down";
	}
	$ifinfotmp = pfSense_get_interface_stats($chkif);
	// $ifinfo['inpkts'] = $ifinfotmp['inpkts'];
	// $ifinfo['outpkts'] = $ifinfotmp['outpkts'];
	$ifinfo['inerrs'] = $ifinfotmp['inerrs'];
	$ifinfo['outerrs'] = $ifinfotmp['outerrs'];
	$ifinfo['collisions'] = $ifinfotmp['collisions'];

	/* Use pfctl for non wrapping 64 bit counters */
	/* Pass */
	exec("/sbin/pfctl -vvsI -i {$chkif}", $pfctlstats);
	$pf_in4_pass = preg_split("/ +/ ", $pfctlstats[3]);
	$pf_out4_pass = preg_split("/ +/", $pfctlstats[5]);
	$pf_in6_pass = preg_split("/ +/ ", $pfctlstats[7]);
	$pf_out6_pass = preg_split("/ +/", $pfctlstats[9]);
	$in4_pass = $pf_in4_pass[5];
	$out4_pass = $pf_out4_pass[5];
	$in4_pass_packets = $pf_in4_pass[3];
	$out4_pass_packets = $pf_out4_pass[3];
	$in6_pass = $pf_in6_pass[5];
	$out6_pass = $pf_out6_pass[5];
	$in6_pass_packets = $pf_in6_pass[3];
	$out6_pass_packets = $pf_out6_pass[3];
	$ifinfo['inbytespass'] = $in4_pass + $in6_pass;
	$ifinfo['outbytespass'] = $out4_pass + $out6_pass;
	$ifinfo['inpktspass'] = $in4_pass_packets + $in6_pass_packets;
	$ifinfo['outpktspass'] = $out4_pass_packets + $out6_pass_packets;

	/* Block */
	$pf_in4_block = preg_split("/ +/", $pfctlstats[4]);
	$pf_out4_block = preg_split("/ +/", $pfctlstats[6]);
	$pf_in6_block = preg_split("/ +/", $pfctlstats[8]);
	$pf_out6_block = preg_split("/ +/", $pfctlstats[10]);
	$in4_block = $pf_in4_block[5];
	$out4_block = $pf_out4_block[5];
	$in4_block_packets = $pf_in4_block[3];
	$out4_block_packets = $pf_out4_block[3];
	$in6_block = $pf_in6_block[5];
	$out6_block = $pf_out6_block[5];
	$in6_block_packets = $pf_in6_block[3];
	$out6_block_packets = $pf_out6_block[3];
	$ifinfo['inbytesblock'] = $in4_block + $in6_block;
	$ifinfo['outbytesblock'] = $out4_block + $out6_block;
	$ifinfo['inpktsblock'] = $in4_block_packets + $in6_block_packets;
	$ifinfo['outpktsblock'] = $out4_block_packets + $out6_block_packets;

	$ifinfo['inbytes'] = $in4_pass + $in6_pass;
	$ifinfo['outbytes'] = $out4_pass + $out6_pass;
	$ifinfo['inpkts'] = $in4_pass_packets + $in6_pass_packets;
	$ifinfo['outpkts'] = $out4_pass_packets + $out6_pass_packets;

	$ifconfiginfo = "";
	$link_type = $config['interfaces'][$ifdescr]['ipaddr'];
	switch ($link_type) {
		/* DHCP? -> see if dhclient is up */
		case "dhcp":
			/* see if dhclient is up */
			if (find_dhclient_process($ifinfo['if']) != 0) {
				$ifinfo['dhcplink'] = "up";
			} else {
				$ifinfo['dhcplink'] = "down";
			}

			break;
		/* PPPoE/PPTP/L2TP interface? -> get status from virtual interface */
		case "pppoe":
		case "pptp":
		case "l2tp":
			if ($ifinfo['status'] == "up" && !isset($link0)) {
				/* get PPPoE link status for dial on demand */
				$ifinfo["{$link_type}link"] = "up";
			} else {
				$ifinfo["{$link_type}link"] = "down";
			}

			break;
		/* PPP interface? -> get uptime for this session and cumulative uptime from the persistent log file in conf */
		case "ppp":
			if ($ifinfo['status'] == "up") {
				$ifinfo['ppplink'] = "up";
			} else {
				$ifinfo['ppplink'] = "down" ;
			}

			if (empty($ifinfo['status'])) {
				$ifinfo['status'] = "down";
			}

			if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
				foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
					if ($config['interfaces'][$ifdescr]['if'] == $ppp['if']) {
						break;
					}
				}
			}
			$dev = $ppp['ports'];
			if ($config['interfaces'][$ifdescr]['if'] != $ppp['if'] || empty($dev)) {
				break;
			}
			if (!file_exists($dev)) {
				$ifinfo['nodevice'] = 1;
				$ifinfo['pppinfo'] = $dev . " " . gettext("device not present! Is the modem attached to the system?");
			}

			$usbmodemoutput = array();
			exec("/usr/sbin/usbconfig", $usbmodemoutput);
			$mondev = "{$g['tmp_path']}/3gstats.{$ifdescr}";
			if (file_exists($mondev)) {
				$cellstats = file($mondev);
				/* skip header */
				$a_cellstats = explode(",", $cellstats[1]);
				if (preg_match("/huawei/i", implode("\n", $usbmodemoutput))) {
					$ifinfo['cell_rssi'] = huawei_rssi_to_string($a_cellstats[1]);
					$ifinfo['cell_mode'] = huawei_mode_to_string($a_cellstats[2], $a_cellstats[3]);
					$ifinfo['cell_simstate'] = huawei_simstate_to_string($a_cellstats[10]);
					$ifinfo['cell_service'] = huawei_service_to_string(trim($a_cellstats[11]));
				}
				if (preg_match("/zte/i", implode("\n", $usbmodemoutput))) {
					$ifinfo['cell_rssi'] = zte_rssi_to_string($a_cellstats[1]);
					$ifinfo['cell_mode'] = zte_mode_to_string($a_cellstats[2], $a_cellstats[3]);
					$ifinfo['cell_simstate'] = zte_simstate_to_string($a_cellstats[10]);
					$ifinfo['cell_service'] = zte_service_to_string(trim($a_cellstats[11]));
				}
				$ifinfo['cell_upstream'] = $a_cellstats[4];
				$ifinfo['cell_downstream'] = trim($a_cellstats[5]);
				$ifinfo['cell_sent'] = $a_cellstats[6];
				$ifinfo['cell_received'] = trim($a_cellstats[7]);
				$ifinfo['cell_bwupstream'] = $a_cellstats[8];
				$ifinfo['cell_bwdownstream'] = trim($a_cellstats[9]);
			}
			// Calculate cumulative uptime for PPP link. Useful for connections that have per minute/hour contracts so you don't go over!
			if (isset($ppp['uptime'])) {
				$ifinfo['ppp_uptime_accumulated'] = "(".get_ppp_uptime($ifinfo['if']).")";
			}
			break;
		default:
			break;
	}

	if (file_exists("{$g['varrun_path']}/{$link_type}_{$ifdescr}.pid")) {
		$sec = trim(`/usr/local/sbin/ppp-uptime.sh {$ifinfo['if']}`);
		$ifinfo['ppp_uptime'] = convert_seconds_to_dhms($sec);
	}

	if ($ifinfo['status'] == "up") {
		/* try to determine media with ifconfig */
		unset($ifconfiginfo);
		exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);
		$wifconfiginfo = array();
		if (is_interface_wireless($ifdescr)) {
			exec("/sbin/ifconfig {$ifinfo['if']} list sta", $wifconfiginfo);
			array_shift($wifconfiginfo);
		}
		$matches = "";
		foreach ($ifconfiginfo as $ici) {

			/* don't list media/speed for wireless cards, as it always
			   displays 2 Mbps even though clients can connect at 11 Mbps */
			if (preg_match("/media: .*? \((.*?)\)/", $ici, $matches)) {
				$ifinfo['media'] = $matches[1];
			} else if (preg_match("/media: Ethernet (.*)/", $ici, $matches)) {
				$ifinfo['media'] = $matches[1];
			} else if (preg_match("/media: IEEE 802.11 Wireless Ethernet (.*)/", $ici, $matches)) {
				$ifinfo['media'] = $matches[1];
			}

			if (preg_match("/status: (.*)$/", $ici, $matches)) {
				if ($matches[1] != "active") {
					$ifinfo['status'] = $matches[1];
				}
				if ($ifinfo['status'] == gettext("running")) {
					$ifinfo['status'] = gettext("up");
				}
			}
			if (preg_match("/channel (\S*)/", $ici, $matches)) {
				$ifinfo['channel'] = $matches[1];
			}
			if (preg_match("/ssid (\".*?\"|\S*)/", $ici, $matches)) {
				if ($matches[1][0] == '"') {
					$ifinfo['ssid'] = substr($matches[1], 1, -1);
				}
				else {
					$ifinfo['ssid'] = $matches[1];
				}
			}
			if (preg_match("/laggproto (.*)$/", $ici, $matches)) {
				$ifinfo['laggproto'] = $matches[1];
			}
			if (preg_match("/laggport: (.*)$/", $ici, $matches)) {
				$ifinfo['laggport'][] = $matches[1];
			}
		}
		foreach ($wifconfiginfo as $ici) {
			$elements = preg_split("/[ ]+/i", $ici);
			if ($elements[0] != "") {
				$ifinfo['bssid'] = $elements[0];
			}
			if ($elements[3] != "") {
				$ifinfo['rate'] = $elements[3];
			}
			if ($elements[4] != "") {
				$ifinfo['rssi'] = $elements[4];
			}
		}
		/* lookup the gateway */
		if (interface_has_gateway($ifdescr)) {
			$ifinfo['gateway'] = get_interface_gateway($ifdescr);
			$ifinfo['gatewayv6'] = get_interface_gateway_v6($ifdescr);
		}
	}

	$bridge = "";
	$bridge = link_interface_to_bridge($ifdescr);
	if ($bridge) {
		$bridge_text = `/sbin/ifconfig {$bridge}`;
		if (stristr($bridge_text, "blocking") <> false) {
			$ifinfo['bridge'] = "<b><font color='red'>" . gettext("blocking") . "</font></b> - " . gettext("check for ethernet loops");
			$ifinfo['bridgeint'] = $bridge;
		} else if (stristr($bridge_text, "learning") <> false) {
			$ifinfo['bridge'] = gettext("learning");
			$ifinfo['bridgeint'] = $bridge;
		} else if (stristr($bridge_text, "forwarding") <> false) {
			$ifinfo['bridge'] = gettext("forwarding");
			$ifinfo['bridgeint'] = $bridge;
		}
	}

	return $ifinfo;
}

//returns cpu speed of processor. Good for determining capabilities of machine
function get_cpu_speed() {
	return get_single_sysctl("hw.clockrate");
}

function get_uptime_sec() {
	$boottime = "";
	$matches = "";
	$boottime = get_single_sysctl("kern.boottime");
	preg_match("/sec = (\d+)/", $boottime, $matches);
	$boottime = $matches[1];
	if (intval($boottime) == 0) {
		return 0;
	}

	$uptime = time() - $boottime;
	return $uptime;
}

function resolve_host_addresses($host, $recordtypes = array(DNS_A, DNS_AAAA, DNS_CNAME), $index = true) {
	$dnsresult = array();
	$resolved = array();
	$errreporting = error_reporting();
	error_reporting($errreporting & ~E_WARNING);// dns_get_record throws a warning if nothing is resolved..
	foreach ($recordtypes as $recordtype) {
		$tmp = dns_get_record($host, $recordtype);
		if (is_array($tmp)) {
			$dnsresult = array_merge($dnsresult, $tmp);
		}
	}
	error_reporting($errreporting);// restore original php warning/error settings.
	foreach ($dnsresult as $item) {
		$newitem = array();
		$newitem['type'] = $item['type'];
		switch ($item['type']) {
			case 'CNAME':
				$newitem['data'] = $item['target'];
				$resolved[] = $newitem;
				break;
			case 'A':
				$newitem['data'] = $item['ip'];
				$resolved[] = $newitem;
				break;
			case 'AAAA':
				$newitem['data'] = $item['ipv6'];
				$resolved[] = $newitem;
				break;
		}
	}
	if ($index == false) {
		foreach ($resolved as $res) {
			$noind[] = $res['data'];
		}
		$resolved = $noind;
	}
	return $resolved;
}

function add_hostname_to_watch($hostname) {
	if (!is_dir("/var/db/dnscache")) {
		mkdir("/var/db/dnscache");
	}
	$result = array();
	if ((is_fqdn($hostname)) && (!is_ipaddr($hostname))) {
		$contents = "";
		$domips = resolve_host_addresses($hostname, array(DNS_A), false);
		if (!empty($domips)) {
			foreach ($domips as $ip) {
				$contents .= "$ip\n";
			}
		}
		file_put_contents("/var/db/dnscache/$hostname", $contents);
		/* Remove empty elements */
		$result = array_filter(explode("\n", $contents), 'strlen');
	}
	return $result;
}

function is_fqdn($fqdn) {
	return (bool) preg_match('/^(?:(?!-)[-_a-z0-9]+(?<!-)\.)*(?!-)[a-z0-9\-]+(?<!-)\.(?:xn--)?[a-z]+[\.]?$/i', $fqdn);
}

function pfsense_default_state_size() {
	/* get system memory amount */
	$memory = get_memory();
	$physmem = $memory[0];
	/* Be cautious and only allocate 10% of system memory to the state table */
	$max_states = (int) ($physmem/10)*1000;
	return $max_states;
}

function pfsense_default_tables_size() {
	$current = `pfctl -sm | grep ^tables | awk '{print $4};'`;
	return $current;
}

function pfsense_default_table_entries_size() {
	$current = `pfctl -sm | grep table-entries | awk '{print $4};'`;
	return (trim($current));
}

/* Compare the current hostname DNS to the DNS cache we made
 * if it has changed we return the old records
 * if no change we return false */
function compare_hostname_to_dnscache($hostname) {
	if (!is_dir("/var/db/dnscache")) {
		mkdir("/var/db/dnscache");
	}
	$hostname = trim($hostname);
	if (is_readable("/var/db/dnscache/{$hostname}")) {
		$oldcontents = file_get_contents("/var/db/dnscache/{$hostname}");
	} else {
		$oldcontents = "";
	}
	if ((is_fqdn($hostname)) && (!is_ipaddr($hostname))) {
		$contents = "";
		$domips = resolve_host_addresses($hostname, array(DNS_A), false);
		if (!empty($domips)) {
			foreach ($domips as $ip) {
				$contents .= "$ip\n";
			}
		}
	}

	if (trim($oldcontents) != trim($contents)) {
		if ($g['debug']) {
			log_error(sprintf(gettext('DNSCACHE: Found old IP %1$s and new IP %2$s'), $oldcontents, $contents));
		}
		return ($oldcontents);
	} else {
		return false;
	}
}

/*
 * load_crypto() - Load crypto modules if enabled in config.
 */
function load_crypto() {
	global $config, $g;
	$crypto_modules = array('aesni', 'cryptodev');

	$enabled_modules = explode('_', $config['system']['crypto_hardware']);

	foreach ($enabled_modules as $enmod) {
		if (empty($enmod) || !in_array($enmod, $crypto_modules)) {
			continue;
		}
		if (!is_module_loaded($enmod)) {
			log_error(sprintf(gettext("Loading %s cryptographic accelerator module."), $enmod));
			mwexec("/sbin/kldload " . escapeshellarg($enmod));
		}
	}
}

/*
 * load_thermal_hardware() - Load temperature monitor kernel module
 */
function load_thermal_hardware() {
	global $config, $g;
	$thermal_hardware_modules = array('coretemp', 'amdtemp');

	if (!in_array($config['system']['thermal_hardware'], $thermal_hardware_modules)) {
		return false;
	}

	if (!empty($config['system']['thermal_hardware']) && !is_module_loaded($config['system']['thermal_hardware'])) {
		log_error(sprintf(gettext("Loading %s thermal monitor module."), $config['system']['thermal_hardware']));
		mwexec("/sbin/kldload {$config['system']['thermal_hardware']}");
	}
}

/****f* pfsense-utils/isvm
 * NAME
 *   isvm
 * INPUTS
 *	none
 * RESULT
 *   returns true if machine is running under a virtual environment
 ******/
function isvm() {
	$virtualenvs = array("vmware", "parallels", "qemu", "bochs", "plex86", "VirtualBox");
	$_gb = exec('/bin/kenv -q smbios.system.product 2>/dev/null', $output, $rc);

	if ($rc != 0 || !isset($output[0])) {
		return false;
	}

	foreach ($virtualenvs as $virtualenv) {
		if (stripos($output[0], $virtualenv) !== false) {
			return true;
		}
	}

	return false;
}

function get_freebsd_version() {
	$version = explode(".", php_uname("r"));
	return $version[0];
}

function download_file($url, $destination, $verify_ssl = true, $connect_timeout = 5, $timeout = 0) {
	global $config, $g;

	$fp = fopen($destination, "wb");

	if (!$fp) {
		return false;
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify_ssl);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify_ssl);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	if (!isset($config['system']['do_not_send_uniqueid'])) {
		curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version'] . ':' . system_get_uniqueid());
	} else {
		curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version']);
	}

	if (!empty($config['system']['proxyurl'])) {
		curl_setopt($ch, CURLOPT_PROXY, $config['system']['proxyurl']);
		if (!empty($config['system']['proxyport'])) {
			curl_setopt($ch, CURLOPT_PROXYPORT, $config['system']['proxyport']);
		}
		if (!empty($config['system']['proxyuser']) && !empty($config['system']['proxypass'])) {
			@curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_ANY | CURLAUTH_ANYSAFE);
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$config['system']['proxyuser']}:{$config['system']['proxypass']}");
		}
	}

	@curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	fclose($fp);
	curl_close($ch);
	if ($http_code == 200) {
		return true;
	} else {
		log_error(sprintf(gettext('Download file failed with status code %1$s. URL: %2$s'), $http_code, $url));
		unlink_if_exists($destination);
		return false;
	}
}

function download_file_with_progress_bar($url, $destination, $verify_ssl = true, $readbody = 'read_body', $connect_timeout = 5, $timeout = 0) {
	global $config, $g;
	global $ch, $fout, $file_size, $downloaded, $config, $first_progress_update;
	$file_size = 1;
	$downloaded = 1;
	$first_progress_update = TRUE;
	/* open destination file */
	$fout = fopen($destination, "wb");

	if (!$fout) {
		return false;
	}
	/*
	 *      Originally by Author: Keyvan Minoukadeh
	 *      Modified by Scott Ullrich to return Content-Length size
	 */
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify_ssl);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify_ssl);
	curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'read_header');
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_WRITEFUNCTION, $readbody);
	curl_setopt($ch, CURLOPT_NOPROGRESS, '1');
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	if (!isset($config['system']['do_not_send_uniqueid'])) {
		curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version'] . ':' . system_get_uniqueid());
	} else {
		curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version']);
	}

	if (!empty($config['system']['proxyurl'])) {
		curl_setopt($ch, CURLOPT_PROXY, $config['system']['proxyurl']);
		if (!empty($config['system']['proxyport'])) {
			curl_setopt($ch, CURLOPT_PROXYPORT, $config['system']['proxyport']);
		}
		if (!empty($config['system']['proxyuser']) && !empty($config['system']['proxypass'])) {
			@curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_ANY | CURLAUTH_ANYSAFE);
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$config['system']['proxyuser']}:{$config['system']['proxypass']}");
		}
	}

	@curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	fclose($fout);
	curl_close($ch);
	if ($http_code == 200) {
		return true;
	} else {
		log_error(sprintf(gettext('Download file failed with status code %1$s. URL: %2$s'), $http_code, $url));
		unlink_if_exists($destination);
		return false;
	}
}

function read_header($ch, $string) {
	global $file_size, $fout;
	$length = strlen($string);
	$regs = "";
	preg_match("/(Content-Length:) (.*)/", $string, $regs);
	if ($regs[2] <> "") {
		$file_size = intval($regs[2]);
	}
	ob_flush();
	return $length;
}

function read_body($ch, $string) {
	global $fout, $file_size, $downloaded, $sendto, $static_status, $static_output, $lastseen, $first_progress_update;
	global $pkg_interface;
	$length = strlen($string);
	$downloaded += intval($length);
	if ($file_size > 0) {
		$downloadProgress = round(100 * (1 - $downloaded / $file_size), 0);
		$downloadProgress = 100 - $downloadProgress;
	} else {
		$downloadProgress = 0;
	}
	if ($lastseen <> $downloadProgress and $downloadProgress < 101) {
		if ($sendto == "status") {
			if ($pkg_interface == "console") {
				if (($downloadProgress % 10) == 0 || $downloadProgress < 10) {
					$tostatus = $static_status . $downloadProgress . "%";
					if ($downloadProgress == 100) {
						$tostatus = $tostatus . "\r";
					}
					update_status($tostatus);
				}
			} else {
				$tostatus = $static_status . $downloadProgress . "%";
				update_status($tostatus);
			}
		} else {
			if ($pkg_interface == "console") {
				if (($downloadProgress % 10) == 0 || $downloadProgress < 10) {
					$tooutput = $static_output . $downloadProgress . "%";
					if ($downloadProgress == 100) {
						$tooutput = $tooutput . "\r";
					}
					update_output_window($tooutput);
				}
			} else {
				$tooutput = $static_output . $downloadProgress . "%";
				update_output_window($tooutput);
			}
		}
		if (($pkg_interface != "console") || (($downloadProgress % 10) == 0) || ($downloadProgress < 10)) {
			update_progress_bar($downloadProgress, $first_progress_update);
			$first_progress_update = FALSE;
		}
		$lastseen = $downloadProgress;
	}
	if ($fout) {
		fwrite($fout, $string);
	}
	ob_flush();
	return $length;
}

/*
 *   update_output_window: update bottom textarea dynamically.
 */
function update_output_window($text) {
	global $pkg_interface;
	$log = preg_replace("/\n/", "\\n", $text);
	if ($pkg_interface != "console") {
?>
<script type="text/javascript">
//<![CDATA[
	document.getElementById("output").textContent="<?=htmlspecialchars($log)?>";
	document.getElementById("output").scrollTop = document.getElementById("output").scrollHeight;
//]]>
</script>
<?php
	}
	/* ensure that contents are written out */
	ob_flush();
}

/*
 *   update_status: update top textarea dynamically.
 */
function update_status($status) {
	global $pkg_interface;

	if ($pkg_interface == "console") {
		print ("{$status}");
	}

	/* ensure that contents are written out */
	ob_flush();
}

/*
 * update_progress_bar($percent, $first_time): updates the javascript driven progress bar.
 */
function update_progress_bar($percent, $first_time) {
	global $pkg_interface;
	if ($percent > 100) {
		$percent = 1;
	}
	if ($pkg_interface <> "console") {
		echo '<script type="text/javascript">';
		echo "\n//<![CDATA[\n";
		echo 'document.getElementById("progressbar").style.width="'. $percent.'%"';
		echo "\n//]]>\n";
		echo '</script>';
	} else {
		if (!($first_time)) {
			echo "\x08\x08\x08\x08\x08";
		}
		echo sprintf("%4d%%", $percent);
	}
}

function update_alias_name($new_alias_name, $orig_alias_name) {
	if (!$orig_alias_name) {
		return;
	}

	// Firewall rules
	update_alias_names_upon_change(array('filter', 'rule'), array('source', 'address'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('filter', 'rule'), array('destination', 'address'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('filter', 'rule'), array('source', 'port'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('filter', 'rule'), array('destination', 'port'), $new_alias_name, $orig_alias_name);
	// NAT Rules
	update_alias_names_upon_change(array('nat', 'rule'), array('source', 'address'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('nat', 'rule'), array('source', 'port'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('nat', 'rule'), array('destination', 'address'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('nat', 'rule'), array('destination', 'port'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('nat', 'rule'), array('target'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('nat', 'rule'), array('local-port'), $new_alias_name, $orig_alias_name);
	// NAT 1:1 Rules
	//update_alias_names_upon_change(array('nat', 'onetoone'), array('external'), $new_alias_name, $orig_alias_name);
	//update_alias_names_upon_change(array('nat', 'onetoone'), array('source', 'address'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('nat', 'onetoone'), array('destination', 'address'), $new_alias_name, $orig_alias_name);
	// NAT Outbound Rules
	update_alias_names_upon_change(array('nat', 'outbound', 'rule'), array('source', 'network'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('nat', 'outbound', 'rule'), array('sourceport'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('nat', 'outbound', 'rule'), array('destination', 'address'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('nat', 'outbound', 'rule'), array('dstport'), $new_alias_name, $orig_alias_name);
	update_alias_names_upon_change(array('nat', 'outbound', 'rule'), array('target'), $new_alias_name, $orig_alias_name);
	// Alias in an alias
	update_alias_names_upon_change(array('aliases', 'alias'), array('address'), $new_alias_name, $orig_alias_name);
}

function update_alias_names_upon_change($section, $field, $new_alias_name, $origname) {
	global $g, $config, $pconfig, $debug;
	if (!$origname) {
		return;
	}

	$sectionref = &$config;
	foreach ($section as $sectionname) {
		if (is_array($sectionref) && isset($sectionref[$sectionname])) {
			$sectionref = &$sectionref[$sectionname];
		} else {
			return;
		}
	}

	if ($debug) {
		$fd = fopen("{$g['tmp_path']}/print_r", "a");
		fwrite($fd, print_r($pconfig, true));
	}

	if (is_array($sectionref)) {
		foreach ($sectionref as $itemkey => $item) {
			if ($debug) {
				fwrite($fd, "$itemkey\n");
			}

			$fieldfound = true;
			$fieldref = &$sectionref[$itemkey];
			foreach ($field as $fieldname) {
				if (is_array($fieldref) && isset($fieldref[$fieldname])) {
					$fieldref = &$fieldref[$fieldname];
				} else {
					$fieldfound = false;
					break;
				}
			}
			if ($fieldfound && $fieldref == $origname) {
				if ($debug) {
					fwrite($fd, "Setting old alias value $origname to $new_alias_name\n");
				}
				$fieldref = $new_alias_name;
			}
		}
	}

	if ($debug) {
		fclose($fd);
	}

}

function parse_aliases_file($filename, $type = "url", $max_items = -1, $kflc = false) {
	/*
	 * $filename = file to process for example blocklist like DROP:  http://www.spamhaus.org/drop/drop.txt
	 * $type = if set to 'url' then subnets and ips will be returned,
	 *         if set to 'url_ports' port-ranges and ports will be returned
	 * $max_items = sets the maximum amount of valid items to load, -1 the default defines there is no limit.
	 *
	 * RETURNS an array of ip subnets and ip's or ports and port-ranges, returns NULL upon a error conditions (file not found)
	 */

	if (!file_exists($filename)) {
		log_error(sprintf(gettext("Could not process non-existent file from alias: %s"), $filename));
		return null;
	}

	if (filesize($filename) == 0) {
		log_error(sprintf(gettext("Could not process empty file from alias: %s"), $filename));
		return null;
	}
	$fd = @fopen($filename, 'r');
	if (!$fd) {
		log_error(sprintf(gettext("Could not process aliases from alias: %s"), $filename));
		return null;
	}
	$items = array();
	$comments = array();
	/* NOTE: fgetss() is not a typo RTFM before being smart */
	while (($fc = fgetss($fd)) !== FALSE) {
		$tmp = trim($fc, " \t\n\r");
		if (empty($tmp)) {
			continue;
		}
		if (($kflc) && (strpos($tmp, '#') === 0)) {	// Keep Full Line Comments (lines beginning with #).
			$comments[] = $tmp;
		} else {
			$tmp_str = strstr($tmp, '#', true);
			if (!empty($tmp_str)) {
				$tmp = $tmp_str;
			}
			$tmp_str = strstr($tmp, ' ', true);
			if (!empty($tmp_str)) {
				$tmp = $tmp_str;
			}
			switch ($type) {
				case "url":
				case "urltable":
					if (is_ipaddr($tmp) || is_subnet($tmp)) {
						$items[] = $tmp;
						break;
					}
					if (is_fqdn($tmp)) {
						$results = resolve_host_addresses($tmp, array(DNS_A, DNS_AAAA), false);
						if (!empty($results)) {
							foreach ($results as $ip) {
								if (is_ipaddr($ip)) {
									$items[] = $ip;
								}
							}
						}
					}
					break;
				case "url_ports":
				case "urltable_ports":
					if (is_port_or_range($tmp)) {
						$items[] = $tmp;
					}
					break;
				default:
					/* unknown type */
					break;
				}
			if (count($items) == $max_items) {
				break;
			}
		}
	}
	fclose($fd);
	return array_merge($comments, $items);
}

function update_alias_url_data() {
	global $config, $g;

	$updated = false;

	/* item is a url type */
	$lockkey = lock('aliasurl');
	if (is_array($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $x => $alias) {
			if (empty($alias['aliasurl'])) {
				continue;
			}

			$address = null;
			foreach ($alias['aliasurl'] as $alias_url) {
				/* fetch down and add in */
				$temp_filename = tempnam("{$g['tmp_path']}/", "alias_import");
				unlink($temp_filename);
				$verify_ssl = isset($config['system']['checkaliasesurlcert']);
				mkdir($temp_filename);
				if (!download_file($alias_url, $temp_filename . "/aliases", $verify_ssl)) {
					log_error(sprintf(gettext("Failed to download alias %s"), $alias_url));
					continue;
				}

				/* if the item is tar gzipped then extract */
				if (stripos($alias_url, '.tgz')) {
					if (!process_alias_tgz($temp_filename)) {
						continue;
					}
				}
				if (file_exists("{$temp_filename}/aliases")) {
					$address = parse_aliases_file("{$temp_filename}/aliases", $alias['type'], 5000);
					mwexec("/bin/rm -rf {$temp_filename}");
				}
			}
			if ($address != null) {
				$config['aliases']['alias'][$x]['address'] = implode(" ", $address);
				$updated = true;
			}
		}
	}
	unlock($lockkey);

	/* Report status to callers as well */
	return $updated;
}

function process_alias_tgz($temp_filename) {
	if (!file_exists('/usr/bin/tar')) {
		log_error(gettext("Alias archive is a .tar/tgz file which cannot be decompressed because utility is missing!"));
		return false;
	}
	rename("{$temp_filename}/aliases", "{$temp_filename}/aliases.tgz");
	mwexec("/usr/bin/tar xzf {$temp_filename}/aliases.tgz -C {$temp_filename}/aliases/");
	unlink("{$temp_filename}/aliases.tgz");
	$files_to_process = return_dir_as_array("{$temp_filename}/");
	/* foreach through all extracted files and build up aliases file */
	$fd = @fopen("{$temp_filename}/aliases", "w");
	if (!$fd) {
		log_error(sprintf(gettext("Could not open %s/aliases for writing!"), $temp_filename));
		return false;
	}
	foreach ($files_to_process as $f2p) {
		$tmpfd = @fopen($f2p, 'r');
		if (!$tmpfd) {
			log_error(sprintf(gettext('The following file could not be read %1$s from %2$s'), $f2p, $temp_filename));
			continue;
		}
		while (($tmpbuf = fread($tmpfd, 65536)) !== FALSE) {
			fwrite($fd, $tmpbuf);
		}
		fclose($tmpfd);
		unlink($f2p);
	}
	fclose($fd);
	unset($tmpbuf);

	return true;
}

function version_compare_dates($a, $b) {
	$a_time = strtotime($a);
	$b_time = strtotime($b);

	if ((!$a_time) || (!$b_time)) {
		return FALSE;
	} else {
		if ($a_time < $b_time) {
			return -1;
		} elseif ($a_time == $b_time) {
			return 0;
		} else {
			return 1;
		}
	}
}
function version_get_string_value($a) {
	$strs = array(
		0 => "ALPHA-ALPHA",
		2 => "ALPHA",
		3 => "BETA",
		4 => "B",
		5 => "C",
		6 => "D",
		7 => "RC",
		8 => "RELEASE",
		9 => "*"			// Matches all release levels
	);
	$major = 0;
	$minor = 0;
	foreach ($strs as $num => $str) {
		if (substr($a, 0, strlen($str)) == $str) {
			$major = $num;
			$n = substr($a, strlen($str));
			if (is_numeric($n)) {
				$minor = $n;
			}
			break;
		}
	}
	return "{$major}.{$minor}";
}
function version_compare_string($a, $b) {
	// Only compare string parts if both versions give a specific release
	// (If either version lacks a string part, assume intended to match all release levels)
	if (isset($a) && isset($b)) {
		return version_compare_numeric(version_get_string_value($a), version_get_string_value($b));
	} else {
		return 0;
	}
}
function version_compare_numeric($a, $b) {
	$a_arr = explode('.', rtrim($a, '.'));
	$b_arr = explode('.', rtrim($b, '.'));

	foreach ($a_arr as $n => $val) {
		if (array_key_exists($n, $b_arr)) {
			// So far so good, both have values at this minor version level. Compare.
			if ($val > $b_arr[$n]) {
				return 1;
			} elseif ($val < $b_arr[$n]) {
				return -1;
			}
		} else {
			// a is greater, since b doesn't have any minor version here.
			return 1;
		}
	}
	if (count($b_arr) > count($a_arr)) {
		// b is longer than a, so it must be greater.
		return -1;
	} else {
		// Both a and b are of equal length and value.
		return 0;
	}
}
function pfs_version_compare($cur_time, $cur_text, $remote) {
	// First try date compare
	$v = version_compare_dates($cur_time, $remote);
	if ($v === FALSE) {
		// If that fails, try to compare by string
		// Before anything else, simply test if the strings are equal
		if (($cur_text == $remote) || ($cur_time == $remote)) {
			return 0;
		}
		list($cur_num, $cur_str) = explode('-', $cur_text);
		list($rem_num, $rem_str) = explode('-', $remote);

		// First try to compare the numeric parts of the version string.
		$v = version_compare_numeric($cur_num, $rem_num);

		// If the numeric parts are the same, compare the string parts.
		if ($v == 0) {
			return version_compare_string($cur_str, $rem_str);
		}
	}
	return $v;
}
function process_alias_urltable($name, $type, $url, $freq, $forceupdate=false, $validateonly=false) {
	global $g, $config;

	$urltable_prefix = "/var/db/aliastables/";
	$urltable_filename = $urltable_prefix . $name . ".txt";
	$tmp_urltable_filename = $urltable_filename . ".tmp";

	// Make the aliases directory if it doesn't exist
	if (!file_exists($urltable_prefix)) {
		mkdir($urltable_prefix);
	} elseif (!is_dir($urltable_prefix)) {
		unlink($urltable_prefix);
		mkdir($urltable_prefix);
	}

	// If the file doesn't exist or is older than update_freq days, fetch a new copy.
	if (!file_exists($urltable_filename) || (filesize($urltable_filename) == "0") ||
	    ((time() - filemtime($urltable_filename)) > ($freq * 86400 - 90)) ||
	    $forceupdate) {

		// Try to fetch the URL supplied
		unlink_if_exists($tmp_urltable_filename);
		$verify_ssl = isset($config['system']['checkaliasesurlcert']);
		if (download_file($url, $tmp_urltable_filename, $verify_ssl)) {
			// Convert lines that begin with '$' or ';' to comments '#' instead of deleting them.
			mwexec("/usr/bin/sed -i \"\" -E 's/^[[:space:]]*($|#|;)/#/g; /^#/!s/\;.*//g;' ". escapeshellarg($tmp_urltable_filename));

			$type = ($type) ? $type : alias_get_type($name);	// If empty type passed, try to get it from config.

			$parsed_contents = parse_aliases_file($tmp_urltable_filename, $type, "-1", true);
			if ($type == "urltable_ports") {
				$parsed_contents = group_ports($parsed_contents, true);
			}
			if (is_array($parsed_contents)) {
				file_put_contents($urltable_filename, implode("\n", $parsed_contents));
			} else {
				touch($urltable_filename);
			}

			/* Remove existing archive and create an up to date archive if RAM disk is enabled. */
			unlink_if_exists("{$g['cf_conf_path']}/RAM_Disk_Store/{$name}.txt.tgz");
			if (isset($config['system']['use_mfs_tmpvar'])) {
				mwexec("/usr/bin/tar -czf " . escapeshellarg("{$g['cf_conf_path']}/RAM_Disk_Store/{$name}.txt.tgz") . " -C / " . escapeshellarg($urltable_filename));
			}

			unlink_if_exists($tmp_urltable_filename);
		} else {
			if (!$validateonly) {
				touch($urltable_filename);
			}
			return false;
		}
		return true;
	} else {
		// File exists, and it doesn't need to be updated.
		return -1;
	}
}

function get_include_contents($filename) {
	if (is_file($filename)) {
		ob_start();
		include $filename;
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}
	return false;
}

/* This xml 2 array function is courtesy of the php.net comment section on xml_parse.
 * it is roughly 4 times faster then our existing pfSense parser but due to the large
 * size of the RRD xml dumps this is required.
 * The reason we do not use it for pfSense is that it does not know about array fields
 * which causes it to fail on array fields with single items. Possible Todo?
 */
function xml2array($contents, $get_attributes = 1, $priority = 'tag') {
	if (!function_exists('xml_parser_create')) {
		return array ();
	}
	$parser = xml_parser_create('');
	xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($parser, trim($contents), $xml_values);
	xml_parser_free($parser);
	if (!$xml_values) {
		return; //Hmm...
	}
	$xml_array = array ();
	$parents = array ();
	$opened_tags = array ();
	$arr = array ();
	$current = & $xml_array;
	$repeated_tag_index = array ();
	foreach ($xml_values as $data) {
		unset ($attributes, $value);
		extract($data);
		$result = array ();
		$attributes_data = array ();
		if (isset ($value)) {
			if ($priority == 'tag') {
				$result = $value;
			} else {
				$result['value'] = $value;
			}
		}
		if (isset ($attributes) and $get_attributes) {
			foreach ($attributes as $attr => $val) {
				if ($priority == 'tag') {
					$attributes_data[$attr] = $val;
				} else {
					$result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
				}
			}
		}
		if ($type == "open") {
			$parent[$level -1] = & $current;
			if (!is_array($current) or (!in_array($tag, array_keys($current)))) {
				$current[$tag] = $result;
				if ($attributes_data) {
					$current[$tag . '_attr'] = $attributes_data;
				}
				$repeated_tag_index[$tag . '_' . $level] = 1;
				$current = &$current[$tag];
			} else {
				if (isset ($current[$tag][0])) {
					$current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
					$repeated_tag_index[$tag . '_' . $level]++;
				} else {
					$current[$tag] = array (
						$current[$tag],
						$result
						);
					$repeated_tag_index[$tag . '_' . $level] = 2;
					if (isset ($current[$tag . '_attr'])) {
						$current[$tag]['0_attr'] = $current[$tag . '_attr'];
						unset ($current[$tag . '_attr']);
					}
				}
				$last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
				$current = &$current[$tag][$last_item_index];
			}
		} elseif ($type == "complete") {
			if (!isset ($current[$tag])) {
				$current[$tag] = $result;
				$repeated_tag_index[$tag . '_' . $level] = 1;
				if ($priority == 'tag' and $attributes_data) {
					$current[$tag . '_attr'] = $attributes_data;
				}
			} else {
				if (isset ($current[$tag][0]) and is_array($current[$tag])) {
					$current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
					if ($priority == 'tag' and $get_attributes and $attributes_data) {
						$current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
					}
					$repeated_tag_index[$tag . '_' . $level]++;
				} else {
					$current[$tag] = array (
						$current[$tag],
						$result
						);
					$repeated_tag_index[$tag . '_' . $level] = 1;
					if ($priority == 'tag' and $get_attributes) {
						if (isset ($current[$tag . '_attr'])) {
							$current[$tag]['0_attr'] = $current[$tag . '_attr'];
							unset ($current[$tag . '_attr']);
						}
						if ($attributes_data) {
							$current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
						}
					}
					$repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
				}
			}
		} elseif ($type == 'close') {
			$current = &$parent[$level -1];
		}
	}
	return ($xml_array);
}

function get_country_name($country_code = "ALL") {
	if ($country_code != "ALL" && strlen($country_code) != 2) {
		return "";
	}

	$country_names_xml = "/usr/local/share/pfSense/iso_3166-1_list_en.xml";
	$country_names_contents = file_get_contents($country_names_xml);
	$country_names = xml2array($country_names_contents);

	if ($country_code == "ALL") {
		$country_list = array();
		foreach ($country_names['ISO_3166-1_List_en']['ISO_3166-1_Entry'] as $country) {
			$country_list[] = array(
				"code" => $country['ISO_3166-1_Alpha-2_Code_element'],
				"name" => ucwords(strtolower($country['ISO_3166-1_Country_name'])));
		}
		return $country_list;
	}

	foreach ($country_names['ISO_3166-1_List_en']['ISO_3166-1_Entry'] as $country) {
		if ($country['ISO_3166-1_Alpha-2_Code_element'] == strtoupper($country_code)) {
			return ucwords(strtolower($country['ISO_3166-1_Country_name']));
		}
	}
	return "";
}

/* Return the list of country codes to be used on CAs and certs */
function get_cert_country_codes() {
	$countries = get_country_name();

	$country_codes = array();
	foreach ($countries as $country) {
		$country_codes[$country['code']] = $country['code'];
	}
	ksort($country_codes);

	/* Preserve historical order: None, US, CA, other countries */
	$first_items[''] = gettext("None");
	$first_items['US'] = $country_codes['US'];
	$first_items['CA'] = $country_codes['CA'];
	unset($country_codes['US']);
	unset($country_codes['CA']);

	return array_merge($first_items, $country_codes);
}

/* sort by interface only, retain the original order of rules that apply to
   the same interface */
function filter_rules_sort() {
	global $config;

	init_config_arr(array('filter', 'rule'));
	/* mark each rule with the sequence number (to retain the order while sorting) */
	for ($i = 0; isset($config['filter']['rule'][$i]); $i++) {
		if (!is_array($config['filter']['rule'][$i])) {
			$config['filter']['rule'][$i] = array();
		}
		$config['filter']['rule'][$i]['seq'] = $i;
	}

	usort($config['filter']['rule'], "filter_rules_compare");

	/* strip the sequence numbers again */
	for ($i = 0; isset($config['filter']['rule'][$i]); $i++) {
		unset($config['filter']['rule'][$i]['seq']);
	}
}
function filter_rules_compare($a, $b) {
	if (isset($a['floating']) && isset($b['floating'])) {
		return $a['seq'] - $b['seq'];
	} else if (isset($a['floating'])) {
		return -1;
	} else if (isset($b['floating'])) {
		return 1;
	} else if ($a['interface'] == $b['interface']) {
		return $a['seq'] - $b['seq'];
	} else {
		return compare_interface_friendly_names($a['interface'], $b['interface']);
	}
}

function generate_ipv6_from_mac($mac) {
	$elements = explode(":", $mac);
	if (count($elements) <> 6) {
		return false;
	}

	$i = 0;
	$ipv6 = "fe80::";
	foreach ($elements as $byte) {
		if ($i == 0) {
			$hexadecimal = substr($byte, 1, 2);
			$bitmap = base_convert($hexadecimal, 16, 2);
			$bitmap = str_pad($bitmap, 4, "0", STR_PAD_LEFT);
			$bitmap = substr($bitmap, 0, 2) ."1". substr($bitmap, 3, 4);
			$byte = substr($byte, 0, 1) . base_convert($bitmap, 2, 16);
		}
		$ipv6 .= $byte;
		if ($i == 1) {
			$ipv6 .= ":";
		}
		if ($i == 3) {
			$ipv6 .= ":";
		}
		if ($i == 2) {
			$ipv6 .= "ff:fe";
		}

		$i++;
	}
	return $ipv6;
}

/****f* pfsense-utils/load_mac_manufacturer_table
 * NAME
 *   load_mac_manufacturer_table
 * INPUTS
 *   none
 * RESULT
 *   returns associative array with MAC-Manufacturer pairs
 ******/
function load_mac_manufacturer_table() {
	/* load MAC-Manufacture data from the file */
	$macs = false;
	if (file_exists("/usr/local/share/nmap/nmap-mac-prefixes")) {
		$macs=file("/usr/local/share/nmap/nmap-mac-prefixes");
	}
	if ($macs) {
		foreach ($macs as $line) {
			if (preg_match('/([0-9A-Fa-f]{6}) (.*)$/', $line, $matches)) {
				/* store values like this $mac_man['000C29']='VMware' */
				$mac_man["$matches[1]"] = $matches[2];
			}
		}
		return $mac_man;
	} else {
		return -1;
	}

}

/****f* pfsense-utils/is_ipaddr_configured
 * NAME
 *   is_ipaddr_configured
 * INPUTS
 *   IP Address to check.
 *   If ignore_if is a VIP (not carp), vip array index is passed after string _virtualip
 *   check_localip - if true then also check for matches with PPTP and L2TP addresses
 *   check_subnets - if true then check if the given ipaddr is contained anywhere in the subnet of any other configured IP address
 *   cidrprefix - the CIDR prefix (16, 20, 24, 64...) of ipaddr.
 *     If check_subnets is true and cidrprefix is specified,
 *     then check if the ipaddr/cidrprefix subnet overlaps the subnet of any other configured IP address
 * RESULT
 *   returns true if the IP Address is configured and present on this device or overlaps a configured subnet.
*/
function is_ipaddr_configured($ipaddr, $ignore_if = "", $check_localip = false, $check_subnets = false, $cidrprefix = "") {
	if (count(where_is_ipaddr_configured($ipaddr, $ignore_if, $check_localip, $check_subnets, $cidrprefix))) {
		return true;
	}
	return false;
}

/****f* pfsense-utils/where_is_ipaddr_configured
 * NAME
 *   where_is_ipaddr_configured
 * INPUTS
 *   IP Address to check.
 *   If ignore_if is a VIP (not carp), vip array index is passed after string _virtualip
 *   check_localip - if true then also check for matches with PPTP and L2TP addresses
 *   check_subnets - if true then check if the given ipaddr is contained anywhere in the subnet of any other configured IP address
 *   cidrprefix - the CIDR prefix (16, 20, 24, 64...) of ipaddr.
 *     If check_subnets is true and cidrprefix is specified,
 *     then check if the ipaddr/cidrprefix subnet overlaps the subnet of any other configured IP address
 * RESULT
 *   Returns an array of the interfaces 'if' plus IP address or subnet 'ip_or_subnet' that match or overlap the IP address to check.
 *   If there are no matches then an empty array is returned.
*/
function where_is_ipaddr_configured($ipaddr, $ignore_if = "", $check_localip = false, $check_subnets = false, $cidrprefix = "") {
	global $config;

	$where_configured = array();

	$pos = strpos($ignore_if, '_virtualip');
	if ($pos !== false) {
		$ignore_vip_id = substr($ignore_if, $pos+10);
		$ignore_vip_if = substr($ignore_if, 0, $pos);
	} else {
		$ignore_vip_id = -1;
		$ignore_vip_if = $ignore_if;
	}

	$isipv6 = is_ipaddrv6($ipaddr);

	if ($isipv6) {
		$ipaddr = text_to_compressed_ip6($ipaddr);
	}

	if ($check_subnets) {
		$cidrprefix = intval($cidrprefix);
		if ($isipv6) {
			if (($cidrprefix < 1) || ($cidrprefix > 128)) {
				$cidrprefix = 128;
			}
		} else {
			if (($cidrprefix < 1) || ($cidrprefix > 32)) {
				$cidrprefix = 32;
			}
		}
		$iflist = get_configured_interface_list();
		foreach ($iflist as $if => $ifname) {
			if ($ignore_if == $if) {
				continue;
			}

			if ($isipv6) {
				$if_ipv6 = get_interface_ipv6($if);
				$if_snbitsv6 = get_interface_subnetv6($if);
				if ($if_ipv6 && $if_snbitsv6 && check_subnetsv6_overlap($ipaddr, $cidrprefix, $if_ipv6, $if_snbitsv6)) {
					$where_entry = array();
					$where_entry['if'] = $if;
					$where_entry['ip_or_subnet'] = get_interface_ipv6($if) . "/" . get_interface_subnetv6($if);
					$where_configured[] = $where_entry;
				}
			} else {
				$if_ipv4 = get_interface_ip($if);
				$if_snbitsv4 = get_interface_subnet($if);
				if ($if_ipv4 && $if_snbitsv4 && check_subnets_overlap($ipaddr, $cidrprefix, $if_ipv4, $if_snbitsv4)) {
					$where_entry = array();
					$where_entry['if'] = $if;
					$where_entry['ip_or_subnet'] = get_interface_ip($if) . "/" . get_interface_subnet($if);
					$where_configured[] = $where_entry;
				}
			}
		}
	} else {
		if ($isipv6) {
			$interface_list_ips = get_configured_ipv6_addresses();
		} else {
			$interface_list_ips = get_configured_ip_addresses();
		}

		foreach ($interface_list_ips as $if => $ilips) {
			if ($ignore_if == $if) {
				continue;
			}
			if (strcasecmp($ipaddr, $ilips) == 0) {
				$where_entry = array();
				$where_entry['if'] = $if;
				$where_entry['ip_or_subnet'] = $ilips;
				$where_configured[] = $where_entry;
			}
		}
	}

	if ($check_localip) {
		if (!is_array($config['l2tp']) && !empty($config['l2tp']['localip']) && (strcasecmp($ipaddr, text_to_compressed_ip6($config['l2tp']['localip'])) == 0)) {
			$where_entry = array();
			$where_entry['if'] = 'l2tp';
			$where_entry['ip_or_subnet'] = $config['l2tp']['localip'];
			$where_configured[] = $where_entry;
		}
	}

	return $where_configured;
}

/****f* pfsense-utils/pfSense_handle_custom_code
 * NAME
 *   pfSense_handle_custom_code
 * INPUTS
 *   directory name to process
 * RESULT
 *   globs the directory and includes the files
 */
function pfSense_handle_custom_code($src_dir) {
	// Allow extending of the nat edit page and include custom input validation
	if (is_dir("$src_dir")) {
		$cf = glob($src_dir . "/*.inc");
		foreach ($cf as $nf) {
			if ($nf == "." || $nf == "..") {
				continue;
			}
			// Include the extra handler
			include_once("$nf");
		}
	}
}

function set_language() {
	global $config, $g;

	if (!empty($config['system']['language'])) {
		$lang = $config['system']['language'];
	} elseif (!empty($g['language'])) {
		$lang = $g['language'];
	}
	$lang .= ".UTF-8";

	putenv("LANG={$lang}");
	setlocale(LC_ALL, $lang);
	textdomain("pfSense");
	bindtextdomain("pfSense", "/usr/local/share/locale");
	bind_textdomain_codeset("pfSense", $lang);
}

function get_locale_list() {
	$locales = array(
		"bs" => gettext("Bosnian"),
		"zh_CN" => gettext("Chinese"),
		"zh_Hans_CN" => gettext("Chinese (Simplified, China)"),
		"zh_Hans_HK" => gettext("Chinese (Simplified, Hong Kong SAR China)"),
		"zh_Hant_TW" => gettext("Chinese (Traditional, Taiwan)"),
		"nl" => gettext("Dutch"),
		"en_US" => gettext("English"),
		"fr" => gettext("French"),
		"de_DE" => gettext("German (Germany)"),
		"it" => gettext("Italian"),
		"ko" => gettext("Korean"),
		"nb" => gettext("Norwegian Bokmål"),
		"pl" => gettext("Polish"),
		"pt_PT" => gettext("Portuguese"),
		"pt_BR" => gettext("Portuguese (Brazil)"),
		"ru" => gettext("Russian"),
		"es" => gettext("Spanish"),
		"es_AR" => gettext("Spanish (Argentina)"),
	);

	// If the locales are sorted, the order changes depending on the language selected. If the user accidentally
	// selects the wrong language, this makes it very difficult to guess the intended language. NOT sorting
	// allows the user to remember that English (say) is the seventh on the list and to get back to it more easily

	//asort($locales);

	return $locales;
}

function return_hex_ipv4($ipv4) {
	if (!is_ipaddrv4($ipv4)) {
		return(false);
	}

	/* we need the hex form of the interface IPv4 address */
	$ip4arr = explode(".", $ipv4);
	return (sprintf("%02x%02x%02x%02x", $ip4arr[0], $ip4arr[1], $ip4arr[2], $ip4arr[3]));
}

function convert_ipv6_to_128bit($ipv6) {
	if (!is_ipaddrv6($ipv6)) {
		return(false);
	}

	$ip6arr = array();
	$ip6prefix = Net_IPv6::uncompress($ipv6);
	$ip6arr = explode(":", $ip6prefix);
	/* binary presentation of the prefix for all 128 bits. */
	$ip6prefixbin = "";
	foreach ($ip6arr as $element) {
		$ip6prefixbin .= sprintf("%016b", hexdec($element));
	}
	return($ip6prefixbin);
}

function convert_128bit_to_ipv6($ip6bin) {
	if (strlen($ip6bin) <> 128) {
		return(false);
	}

	$ip6arr = array();
	$ip6binarr = array();
	$ip6binarr = str_split($ip6bin, 16);
	foreach ($ip6binarr as $binpart) {
		$ip6arr[] = dechex(bindec($binpart));
	}
	$ip6addr = text_to_compressed_ip6(implode(":", $ip6arr));

	return($ip6addr);
}


/* Returns the calculated bit length of the prefix delegation from the WAN interface */
/* DHCP-PD is variable, calculate from the prefix-len on the WAN interface */
/* 6rd is variable, calculate from 64 - (v6 prefixlen - (32 - v4 prefixlen)) */
/* 6to4 is 16 bits, e.g. 65535 */
function calculate_ipv6_delegation_length($if) {
	global $config;

	if (!is_array($config['interfaces'][$if])) {
		return false;
	}

	switch ($config['interfaces'][$if]['ipaddrv6']) {
		case "6to4":
			$pdlen = 16;
			break;
		case "6rd":
			$rd6cfg = $config['interfaces'][$if];
			$rd6plen = explode("/", $rd6cfg['prefix-6rd']);
			$pdlen = (64 - ((int) $rd6plen[1] + (32 - (int) $rd6cfg['prefix-6rd-v4plen'])));
			break;
		case "dhcp6":
			$dhcp6cfg = $config['interfaces'][$if];
			$pdlen = $dhcp6cfg['dhcp6-ia-pd-len'];
			break;
		default:
			$pdlen = 0;
			break;
	}
	return($pdlen);
}

function merge_ipv6_delegated_prefix($prefix, $suffix, $len = 64) {
	$prefix = Net_IPv6::uncompress($prefix, true);
	$suffix = Net_IPv6::uncompress($suffix, true);

	/*
	 * xxxx:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx
	 *                ^^^^ ^
	 *                |||| \-> 64
	 *                |||\---> 63, 62, 61, 60
	 *                ||\----> 56
	 *                |\-----> 52
	 *                \------> 48
	 */

	switch ($len) {
	case 48:
		$prefix_len = 15;
		break;
	case 52:
		$prefix_len = 16;
		break;
	case 56:
		$prefix_len = 17;
		break;
	case 59:
	case 60:
		$prefix_len = 18;
		break;
	/*
	 * XXX 63, 62 and 61 should use 18 but PD can change and if
	 * we let user chose this bit it can end up out of PD network
	 *
	 * Leave this with 20 for now until we find a way to let user
	 * chose it. The side-effect is users with PD with one of these
	 * lengths will not be able to setup DHCP server range for full
	 * PD size, only for last /64 network
	 */
	case 63:
	case 62:
	case 61:
	default:
		$prefix_len = 20;
		break;
	}

	return text_to_compressed_ip6(substr($prefix, 0, $prefix_len) .
	    substr($suffix, $prefix_len));
}

function dhcpv6_pd_str_help($pdlen) {
	$result = '';

	switch ($pdlen) {
	case 48:
		$result = '::xxxx:xxxx:xxxx:xxxx:xxxx';
		break;
	case 52:
		$result = '::xxx:xxxx:xxxx:xxxx:xxxx';
		break;
	case 56:
		$result = '::xx:xxxx:xxxx:xxxx:xxxx';
		break;
	case 59:
	case 60:
		$result = '::x:xxxx:xxxx:xxxx:xxxx';
		break;
	/*
	 * XXX 63, 62 and 61 should use same mask as 60 but if
	 * we let the user choose this bit it can end up out of PD network
	 *
	 * Leave this with the same as 64 for now until we find a way to
	 * let the user choose it. The side-effect is users with PD with one
	 * of these lengths will not be able to setup DHCP server ranges
	 * for full PD size, only for last /64 network
	 */
	case 61:
	case 62:
	case 63:
	case 64:
	default:
		$result = '::xxxx:xxxx:xxxx:xxxx';
		break;
	}

	return $result;
}

function huawei_rssi_to_string($rssi) {
	$dbm = array();
	$i = 0;
	$dbstart = -113;
	while ($i < 32) {
		$dbm[$i] = $dbstart + ($i * 2);
		$i++;
	}
	$percent = round(($rssi / 31) * 100);
	$string = "rssi:{$rssi} level:{$dbm[$rssi]}dBm percent:{$percent}%";
	return $string;
}

function huawei_mode_to_string($mode, $submode) {
	$modes[0] = gettext("None");
	$modes[1] = "AMPS";
	$modes[2] = "CDMA";
	$modes[3] = "GSM/GPRS";
	$modes[4] = "HDR";
	$modes[5] = "WCDMA";
	$modes[6] = "GPS";

	$submodes[0] = gettext("No Service");
	$submodes[1] = "GSM";
	$submodes[2] = "GPRS";
	$submodes[3] = "EDGE";
	$submodes[4] = "WCDMA";
	$submodes[5] = "HSDPA";
	$submodes[6] = "HSUPA";
	$submodes[7] = "HSDPA+HSUPA";
	$submodes[8] = "TD-SCDMA";
	$submodes[9] = "HSPA+";
	$string = "{$modes[$mode]}, {$submodes[$submode]} " . gettext("Mode");
	return $string;
}

function huawei_service_to_string($state) {
	$modes[0] = gettext("No Service");
	$modes[1] = gettext("Restricted Service");
	$modes[2] = gettext("Valid Service");
	$modes[3] = gettext("Restricted Regional Service");
	$modes[4] = gettext("Powersaving Service");
	$modes[255] = gettext("Unknown Service");
	$string = $modes[$state];
	return $string;
}

function huawei_simstate_to_string($state) {
	$modes[0] = gettext("Invalid SIM/locked State");
	$modes[1] = gettext("Valid SIM State");
	$modes[2] = gettext("Invalid SIM CS State");
	$modes[3] = gettext("Invalid SIM PS State");
	$modes[4] = gettext("Invalid SIM CS/PS State");
	$modes[255] = gettext("Missing SIM State");
	$string = $modes[$state];
	return $string;
}

function zte_rssi_to_string($rssi) {
	return huawei_rssi_to_string($rssi);
}

function zte_mode_to_string($mode, $submode) {
	$modes[0] = gettext("No Service");
	$modes[1] = gettext("Limited Service");
	$modes[2] = "GPRS";
	$modes[3] = "GSM";
	$modes[4] = "UMTS";
	$modes[5] = "EDGE";
	$modes[6] = "HSDPA";

	$submodes[0] = "CS_ONLY";
	$submodes[1] = "PS_ONLY";
	$submodes[2] = "CS_PS";
	$submodes[3] = "CAMPED";
	$string = "{$modes[$mode]}, {$submodes[$submode]} " . gettext("Mode");
	return $string;
}

function zte_service_to_string($service) {
	$modes[0] = gettext("Initializing Service");
	$modes[1] = gettext("Network Lock error Service");
	$modes[2] = gettext("Network Locked Service");
	$modes[3] = gettext("Unlocked or correct MCC/MNC Service");
	$string = $modes[$service];
	return $string;
}

function zte_simstate_to_string($state) {
	$modes[0] = gettext("No action State");
	$modes[1] = gettext("Network lock State");
	$modes[2] = gettext("(U)SIM card lock State");
	$modes[3] = gettext("Network Lock and (U)SIM card Lock State");
	$string = $modes[$state];
	return $string;
}

function get_configured_pppoe_server_interfaces() {
	global $config;
	$iflist = array();
	if (is_array($config['pppoes']['pppoe'])) {
		foreach ($config['pppoes']['pppoe'] as $pppoe) {
			if ($pppoe['mode'] == "server") {
				$int = "poes". $pppoe['pppoeid'];
				$iflist[$int] = strtoupper($int);
			}
		}
	}
	return $iflist;
}

function get_pppoes_child_interfaces($ifpattern) {
	$if_arr = array();
	if ($ifpattern == "") {
		return;
	}

	exec("/sbin/ifconfig", $out, $ret);
	foreach ($out as $line) {
		if (preg_match("/^({$ifpattern}[0-9]+):/i", $line, $match)) {
			$if_arr[] = $match[1];
		}
	}
	return $if_arr;

}

/****f* pfsense-utils/pkg_call_plugins
 * NAME
 *   pkg_call_plugins
 * INPUTS
 *   $plugin_type value used to search in package configuration if the plugin is used, also used to create the function name
 *   $plugin_params parameters to pass to the plugin function for passing multiple parameters a array can be used.
 * RESULT
 *   returns associative array results from the plugin calls for each package
 * NOTES
 *   This generic function can be used to notify or retrieve results from functions that are defined in packages.
 ******/
function pkg_call_plugins($plugin_type, $plugin_params) {
	global $g, $config;
	$results = array();
	if (!is_array($config['installedpackages']['package'])) {
		return $results;
	}
	foreach ($config['installedpackages']['package'] as $package) {
		if (is_array($package['plugins']['item'])) {
			foreach ($package['plugins']['item'] as $plugin) {
				if ($plugin['type'] == $plugin_type) {
					if (file_exists($package['include_file'])) {
						require_once($package['include_file']);
					} else {
						continue;
					}
					$pkgname = substr(reverse_strrchr($package['configurationfile'], "."), 0, -1);
					$plugin_function = $pkgname . '_'. $plugin_type;
					$results[$pkgname] = call_user_func($plugin_function, $plugin_params);
				}
			}
		}
	}
	return $results;
}

// Convert IPv6 addresses to lower case
function addrtolower($ip) {
	if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
		return(strtolower($ip));
	} else {
		return($ip);
	}
}

function compare_by_name($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

/****f* pfsense-utils/getarraybyref
 * NAME
 *   getarraybyref
 * INPUTS
 *   $array the array of which a items array needs to be found.
 *   $args.. the sub-items to be retrieved/created
 * RESULT
 *   returns the array that was retrieved from configuration, its created if it does not exist
 * NOTES
 *   Used by haproxy / acme / others.?. .
 *   can be used like this:  $a_certificates = getarraybyref($config, 'installedpackages', 'acme', 'certificates', 'item');
 ******/
function &getarraybyref(&$array) {
	if (!isset($array)) {
		return false;
	}
	if (!is_array($array)) {
		$array = array();
	}
	$item = &$array;
	$arg = func_get_args();
	for($i = 1; $i < count($arg); $i++) {
		$itemindex = $arg[$i];
		if (!is_array($item[$itemindex])) {
			$item[$itemindex] = array();
		}
		$item = &$item[$itemindex];
	}
	return $item;
}

/****f* pfsense-utils/send_download_data
 * NAME
 *   send_download_data - Send content to a user's browser as a file to download
 * INPUTS
 *   $type        : The type of download, either 'data' to send the contents of
 *                    a variable or 'file' to send the contents of a file on the
 *                    filesystem.
 *   $content     : For 'data' type, the content to send the user. For 'file'
 *                    type, the full path to the file to send.
 *   $userfilename: The filename presented to the user when downloading. For
 *                    'file' type, this may be omitted and the basename of
 *                    $content will be used instead.
 *   $contenttype : MIME content type of the data. Default "application/octet-stream"
 * RESULT
 *   Sends the data to the browser as a file to download.
 ******/

function send_user_download($type = 'data', $content, $userfilename = "", $contenttype = "application/octet-stream") {
	/* If the type is 'file', then use the file size, otherwise use the size of the data to send */
	$size = ($type == 'file') ? filesize($content) : strlen($content);

	/* If the filename to pass to the user is empty, assume it to be the filename being read. */
	$name = basename((($type == 'file') && empty($userfilename)) ? $content : $userfilename);

	/* Cannot determine the filename, so bail. */
	if (empty($name)) {
		exit;
	}

	/* Send basic download headers */
	header("Content-Type: {$contenttype}");
	header("Content-Length: {$size}");
	header("Content-Disposition: attachment; filename=" . urlencode($name));

	/* Send cache headers */
	if (isset($_SERVER['HTTPS'])) {
		header('Pragma: ');
		header('Cache-Control: ');
	} else {
		header("Pragma: private");
		header("Cache-Control: private, must-revalidate");
	}

	/* Ensure output buffering is off so PHP does not consume
	 * memory in readfile(). https://redmine.pfsense.org/issues/9239 */
	while (ob_get_level()) {
		@ob_end_clean();
	}

	/* Send the data to the user */
	if ($type == 'file') {
		readfile($content);
	} else {
		echo $content;
	}

	/* Flush any remaining output buffer */
	@ob_end_flush();
	exit;
}

?>
