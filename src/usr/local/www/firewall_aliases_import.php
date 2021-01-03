<?php
/*
 * firewall_aliases_import.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-firewall-alias-import
##|*NAME=Firewall: Alias: Import
##|*DESCR=Allow access to the 'Firewall: Alias: Import' page.
##|*MATCH=firewall_aliases_import.php*
##|-PRIV


// Keywords not allowed in names, see globals.inc for list.
global $pf_reserved_keywords;

require_once("guiconfig.inc");
require_once("util.inc");
require_once("filter.inc");
require_once("shaper.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_aliases.php');

$reserved_ifs = get_configured_interface_list(true);
$pf_reserved_keywords = array_merge($pf_reserved_keywords, $reserved_ifs, $reserved_table_names);

$tab = $_REQUEST['tab'];
if (empty($tab)) {
	$tab = 'ip';
}

$pgtitle = array(gettext("Firewall"), gettext("Aliases"), gettext("Bulk import"));
$pglinks = array("", "firewall_aliases.php?tab=" . $tab, "@self");

init_config_arr(array('aliases', 'alias'));
$a_aliases = &$config['aliases']['alias'];

if ($_POST) {
	$reqdfields = explode(" ", "name aliasimport");
	$reqdfieldsn = array(gettext("Name"), gettext("Aliases to import"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!is_validaliasname($_POST['name'])) {
		$input_errors[] = invalidaliasnamemsg($_POST['name']);
	}

	/* check for name duplicates */
	if (is_alias($_POST['name'])) {
		$input_errors[] = gettext("An alias with this name already exists.");
	}


	/* Check for reserved keyword names */
	foreach ($pf_reserved_keywords as $rk) {
		if ($rk == $_POST['name']) {
			$input_errors[] = sprintf(gettext("Cannot use a reserved keyword as an alias name: %s"), $rk);
		}
	}

	/* check for name interface description conflicts */
	foreach ($config['interfaces'] as $interface) {
		if ($interface['descr'] == $_POST['name']) {
			$input_errors[] = gettext("An interface description with this name already exists.");
			break;
		}
	}

	/* Is the description already used as an interface group name? */
	if (is_array($config['ifgroups']['ifgroupentry'])) {
		foreach ($config['ifgroups']['ifgroupentry'] as $ifgroupentry) {
			if ($ifgroupentry['ifname'] == $_POST['name']) {
				$input_errors[] = gettext("Sorry, an interface group with this name already exists.");
			}
		}
	}

	if ($_POST['aliasimport']) {
		$tocheck = explode("\n", $_POST['aliasimport']);
		$imported_ips = array();
		$imported_descs = array();
		$desc_len_err_found = false;
		$desc_fmt_err_found = false;
		if ($tab == "port") {
			$alias_type = $tab;
		} else {
			$alias_type = "host";
		}

		foreach ($tocheck as $impline) {
			$implinea = explode(" ", trim($impline), 2);
			$impip = alias_idn_to_ascii($implinea[0]);
			$impdesc = trim($implinea[1]);
			if (strlen($impdesc) < 200) {
				if ((strpos($impdesc, "||") === false) && (substr($impdesc, 0, 1) != "|") && (substr($impdesc, -1, 1) != "|")) {
					if ($tab == "port") {
						// Port alias
						if (!empty($impip)) {
							if (is_port_or_range($impip)) {
								$imported_ips[] = $impip;
								$imported_descs[] = $impdesc;
							} else {
								$input_errors[] = sprintf(gettext("%s is not a valid port or port range."), $impip);
							}
						}
					} else {
						// IP alias - host or network
						$iprange_type = is_iprange($impip);
						if ($iprange_type == 4) {
							list($startip, $endip) = explode('-', $impip);
							$rangesubnets = ip_range_to_subnet_array($startip, $endip);
							$imported_ips = array_merge($imported_ips, $rangesubnets);
							$rangedescs = array_fill(0, count($rangesubnets), $impdesc);
							$imported_descs = array_merge($imported_descs, $rangedescs);
						} else if ($iprange_type == 6) {
							$input_errors[] = sprintf(gettext('IPv6 address ranges are not supported (%s)'), $impip);
						} else {
							$is_subnet = is_subnet($impip);
							if (!is_ipaddr($impip) && !$is_subnet && !is_hostname($impip) && !empty($impip)) {
								$input_errors[] = sprintf(gettext("%s is not an IP address. Please correct the error to continue"), $impip);
							} elseif (!empty($impip)) {
								if ($is_subnet) {
									$alias_type = "network";
								}
								$imported_ips[] = $impip;
								$imported_descs[] = $impdesc;
							}
						}
					}
				} else {
					if (!$desc_fmt_err_found) {
						$input_errors[] = gettext("Descriptions may not start or end with vertical bar (|) or contain double vertical bar ||.");
						$desc_fmt_err_found = true;
					}
				}
			} else {
				if (!$desc_len_err_found) {
					/* Note: The 200 character limit is just a practical check to avoid accidents */
					/* if the user pastes a large number of IP addresses without line breaks.	 */
					$input_errors[] = gettext("Descriptions must be less than 200 characters long.");
					$desc_len_err_found = true;
				}
			}
		}
		unset($desc_len_err_found, $desc_fmt_err_found);
	}

	if (!$input_errors && is_array($imported_ips)) {
		$alias = array();
		$alias['address'] = implode(" ", $imported_ips);
		$alias['detail'] = implode("||", $imported_descs);
		$alias['name'] = $_POST['name'];
		$alias['type'] = $alias_type;
		$alias['descr'] = $_POST['descr'];
		unset($imported_ips, $imported_descs);
		$a_aliases[] = $alias;

		// Sort list
		$a_aliases = msort($a_aliases, "name");

		if (write_config(gettext("Imported a firewall alias."))) {
			mark_subsystem_dirty('aliases');
		}

		if (!empty($tab)) {
			header("Location: firewall_aliases.php?tab=" . htmlspecialchars ($tab));
		} else {
			header("Location: firewall_aliases.php");
		}

		exit;
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;
$form->addGlobal(new Form_Input(
	'tab',
	null,
	'hidden',
	$tab
));

if ($tab == "port") {
	$sectiontext = gettext('Port Alias Details');
	$helptext = gettext('Paste in the ports to import separated by a carriage return. ' .
		'The list may contain port numbers, port ranges, blank lines (ignored) and ' .
		'an optional description after each port. e.g.:') .
		'</span><ul><li>' .
		'22' .
		'</li><li>' .
		'1234:1250' .
		'</li><li>' .
		gettext('443 HTTPS port') .
		'</li><li>' .
		gettext('4000:4099 Description of a port range') .
		'</li></ul><span class="help-block">';
} else {
	$sectiontext = gettext('IP Alias Details');
	$helptext = gettext('Paste in the aliases to ' .
		'import separated by a carriage return. Common examples are lists of IPs, ' .
		'networks, blacklists, etc. The list may contain IP addresses, with or without ' .
		'CIDR prefix, IP ranges, blank lines (ignored) and an optional description after ' .
		'each IP. e.g.:') .
		'</span><ul><li>' .
		'172.16.1.2' .
		'</li><li>' .
		'172.16.0.0/24' .
		'</li><li>' .
		'10.11.12.100-10.11.12.200' .
		'</li><li>' .
		gettext('192.168.1.254 Home router') .
		'</li><li>' .
		gettext('10.20.0.0/16 Office network') .
		'</li><li>' .
		gettext('10.40.1.10-10.40.1.19 Managed switches') .
		'</li></ul><span class="help-block">';
}

$section = new Form_Section($sectiontext);

$section->addInput(new Form_Input(
	'name',
	'*Alias Name',
	'text',
	$_POST['name']
))->setPattern('[a-zA-Z0-9_]+')->setHelp('The name of the alias may only consist '.
	'of the characters "a-z, A-Z, 0-9 and _".');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$_POST['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_Textarea(
	'aliasimport',
	'*Aliases to import',
	$_POST["aliasimport"]
))->setHelp($helptext);

$form->add($section);
print $form;

include("foot.inc");
