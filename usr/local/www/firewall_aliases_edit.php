<?php
/* $Id$ */
/*
	firewall_aliases_edit.php
	Copyright (C) 2004 Scott Ullrich
	Copyright (C) 2009 Ermal Luçi
	Copyright (C) 2010 Jim Pingle
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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
	pfSense_BUILDER_BINARIES:	/bin/rm	/bin/mkdir	/usr/bin/fetch
	pfSense_MODULE:	aliases
*/

##|+PRIV
##|*IDENT=page-firewall-alias-edit
##|*NAME=Firewall: Alias: Edit page
##|*DESCR=Allow access to the 'Firewall: Alias: Edit' page.
##|*MATCH=firewall_aliases_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$pgtitle = array(gettext("Firewall"), gettext("Aliases"), gettext("Edit"));

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_aliases.php');

// Keywords not allowed in names
$reserved_keywords = array("all", "pass", "block", "out", "queue", "max", "min", "pptp", "pppoe", "L2TP", "OpenVPN", "IPsec");

// Add all Load balance names to reserved_keywords
if (is_array($config['load_balancer']['lbpool'])) {
	foreach ($config['load_balancer']['lbpool'] as $lbpool) {
		$reserved_keywords[] = $lbpool['name'];
	}
}

$reserved_ifs = get_configured_interface_list(false, true);
$reserved_keywords = array_merge($reserved_keywords, $reserved_ifs, $reserved_table_names);
$max_alias_addresses = 5000;

if (!is_array($config['aliases']['alias'])) {
	$config['aliases']['alias'] = array();
}
$a_aliases = &$config['aliases']['alias'];

$tab = $_REQUEST['tab'];

if ($_POST) {
	$origname = $_POST['origname'];
}

// Debugging
if ($debug) {
	unlink_if_exists("{$g['tmp_path']}/alias_rename_log.txt");
}

function alias_same_type($name, $type) {
	global $config;

	foreach ($config['aliases']['alias'] as $alias) {
		if ($name == $alias['name']) {
			if (in_array($type, array("host", "network")) &&
			    in_array($alias['type'], array("host", "network"))) {
				return true;
			}
			if ($type == $alias['type']) {
				return true;
			} else {
				return false;
			}
		}
	}
	return true;
}

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_aliases[$id]) {
	$original_alias_name = $a_aliases[$id]['name'];
	$pconfig['name'] = $a_aliases[$id]['name'];
	$pconfig['detail'] = $a_aliases[$id]['detail'];
	$pconfig['address'] = $a_aliases[$id]['address'];
	$pconfig['type'] = $a_aliases[$id]['type'];
	$pconfig['descr'] = html_entity_decode($a_aliases[$id]['descr']);

	if (preg_match("/urltable/i", $a_aliases[$id]['type'])) {
		$pconfig['address'] = $a_aliases[$id]['url'];
		$pconfig['updatefreq'] = $a_aliases[$id]['updatefreq'];
	}
	if ($a_aliases[$id]['aliasurl'] <> "") {
		if (is_array($a_aliases[$id]['aliasurl'])) {
			$pconfig['address'] = implode(" ", $a_aliases[$id]['aliasurl']);
		} else {
			$pconfig['address'] = $a_aliases[$id]['aliasurl'];
		}
	}
}

if ($_POST) {
	unset($input_errors);
	$vertical_bar_err_text = gettext("Vertical bars (|) at start or end, or double in the middle of descriptions not allowed. Descriptions have been cleaned. Check and save again.");

	/* input validation */

	$reqdfields = explode(" ", "name");
	$reqdfieldsn = array(gettext("Name"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	$x = is_validaliasname($_POST['name']);
	if (!isset($x)) {
		$input_errors[] = gettext("Reserved word used for alias name.");
	} else if ($_POST['type'] == "port" && (getservbyname($_POST['name'], "tcp") || getservbyname($_POST['name'], "udp"))) {
		$input_errors[] = gettext("Reserved word used for alias name.");
	} else {
		if (is_validaliasname($_POST['name']) == false) {
			$input_errors[] = gettext("The alias name must be less than 32 characters long, may not consist of only numbers, and may only contain the following characters") . " a-z, A-Z, 0-9, _.";
		}
	}
	/* check for name conflicts */
	if (empty($a_aliases[$id])) {
		foreach ($a_aliases as $alias) {
			if ($alias['name'] == $_POST['name']) {
				$input_errors[] = gettext("An alias with this name already exists.");
				break;
			}
		}
	}

	/* Check for reserved keyword names */
	foreach ($reserved_keywords as $rk) {
		if ($rk == $_POST['name']) {
			$input_errors[] = sprintf(gettext("Cannot use a reserved keyword as alias name %s"), $rk);
		}
	}

	/* check for name interface description conflicts */
	foreach ($config['interfaces'] as $interface) {
		if ($interface['descr'] == $_POST['name']) {
			$input_errors[] = gettext("An interface description with this name already exists.");
			break;
		}
	}

	$alias = array();
	$address = array();
	$final_address_details = array();
	$alias['name'] = $_POST['name'];

	if (preg_match("/urltable/i", $_POST['type'])) {
		$address = "";

		/* item is a url table type */
		if ($_POST['address0']) {
			/* fetch down and add in */
			$_POST['address0'] = trim($_POST['address0']);
			$address[] = $_POST['address0'];
			$alias['url'] = $_POST['address0'];
			$alias['updatefreq'] = $_POST['address_subnet0'] ? $_POST['address_subnet0'] : 7;
			if (!is_URL($alias['url']) || empty($alias['url'])) {
				$input_errors[] = gettext("You must provide a valid URL.");
			} elseif (!process_alias_urltable($alias['name'], $alias['url'], 0, true)) {
				$input_errors[] = gettext("Unable to fetch usable data.");
			}
			if ($_POST["detail0"] <> "") {
				if ((strpos($_POST["detail0"], "||") === false) && (substr($_POST["detail0"], 0, 1) != "|") && (substr($_POST["detail0"], -1, 1) != "|")) {
					$final_address_details[] = $_POST["detail0"];
				} else {
					/* Remove leading and trailing vertical bars and replace multiple vertical bars with single, */
					/* and put in the output array so the text is at least redisplayed for the user. */
					$final_address_details[] = preg_replace('/\|\|+/', '|', trim($_POST["detail0"], "|"));
					$input_errors[] = $vertical_bar_err_text;
				}
			} else {
				$final_address_details[] = sprintf(gettext("Entry added %s"), date('r'));
			}
		}
	} else if ($_POST['type'] == "url" || $_POST['type'] == "url_ports") {
		$desc_fmt_err_found = false;

		/* item is a url type */
		for ($x = 0; $x < $max_alias_addresses - 1; $x++) {
			$_POST['address' . $x] = trim($_POST['address' . $x]);
			if ($_POST['address' . $x]) {
				/* fetch down and add in */
				$temp_filename = tempnam("{$g['tmp_path']}/", "alias_import");
				unlink_if_exists($temp_filename);
				$verify_ssl = isset($config['system']['checkaliasesurlcert']);
				mkdir($temp_filename);
				download_file($_POST['address' . $x], $temp_filename . "/aliases", $verify_ssl);

				/* if the item is tar gzipped then extract */
				if (stristr($_POST['address' . $x], ".tgz")) {
					process_alias_tgz($temp_filename);
				} else if (stristr($_POST['address' . $x], ".zip")) {
					process_alias_unzip($temp_filename);
				}

				if (!isset($alias['aliasurl'])) {
					$alias['aliasurl'] = array();
				}

				$alias['aliasurl'][] = $_POST['address' . $x];
				if ($_POST["detail{$x}"] <> "") {
					if ((strpos($_POST["detail{$x}"], "||") === false) && (substr($_POST["detail{$x}"], 0, 1) != "|") && (substr($_POST["detail{$x}"], -1, 1) != "|")) {
						$final_address_details[] = $_POST["detail{$x}"];
					} else {
						/* Remove leading and trailing vertical bars and replace multiple vertical bars with single, */
						/* and put in the output array so the text is at least redisplayed for the user. */
						$final_address_details[] = preg_replace('/\|\|+/', '|', trim($_POST["detail{$x}"], "|"));
						if (!$desc_fmt_err_found) {
							$input_errors[] = $vertical_bar_err_text;
							$desc_fmt_err_found = true;
						}
					}
				} else {
					$final_address_details[] = sprintf(gettext("Entry added %s"), date('r'));
				}

				if (file_exists("{$temp_filename}/aliases")) {
					$address = parse_aliases_file("{$temp_filename}/aliases", $_POST['type'], 3000);
					if ($address == null) {
						/* nothing was found */
						$input_errors[] = sprintf(gettext("You must provide a valid URL. Could not fetch usable data from '%s'."), $_POST['address' . $x]);
					}
					mwexec("/bin/rm -rf " . escapeshellarg($temp_filename));
				} else {
					$input_errors[] = sprintf(gettext("URL '%s' is not valid."), $_POST['address' . $x]);
				}
			}
		}
		unset($desc_fmt_err_found);
		if ($_POST['type'] == "url_ports") {
			$address = group_ports($address);
		}
	} else {
		/* item is a normal alias type */
		$wrongaliases = "";
		$desc_fmt_err_found = false;
		$alias_address_count = 0;

		// First trim and expand the input data.
		// Users can paste strings like "10.1.2.0/24 10.3.0.0/16 9.10.11.0/24" into an address box.
		// They can also put an IP range.
		// This loop expands out that stuff so it can easily be validated.
		for ($x = 0; $x < ($max_alias_addresses - 1); $x++) {
			if ($_POST["address{$x}"] <> "") {
				if ($_POST["detail{$x}"] <> "") {
					if ((strpos($_POST["detail{$x}"], "||") === false) && (substr($_POST["detail{$x}"], 0, 1) != "|") && (substr($_POST["detail{$x}"], -1, 1) != "|")) {
						$detail_text = $_POST["detail{$x}"];
					} else {
						/* Remove leading and trailing vertical bars and replace multiple vertical bars with single, */
						/* and put in the output array so the text is at least redisplayed for the user. */
						$detail_text = preg_replace('/\|\|+/', '|', trim($_POST["detail{$x}"], "|"));
						if (!$desc_fmt_err_found) {
							$input_errors[] = $vertical_bar_err_text;
							$desc_fmt_err_found = true;
						}
					}
				} else {
					$detail_text = sprintf(gettext("Entry added %s"), date('r'));
				}
				$address_items = explode(" ", trim($_POST["address{$x}"]));
				foreach ($address_items as $address_item) {
					$iprange_type = is_iprange($address_item);
					if ($iprange_type == 4) {
						list($startip, $endip) = explode('-', $address_item);
						if ($_POST['type'] == "network") {
							// For network type aliases, expand an IPv4 range into an array of subnets.
							$rangesubnets = ip_range_to_subnet_array($startip, $endip);
							foreach ($rangesubnets as $rangesubnet) {
								if ($alias_address_count > $max_alias_addresses) {
									break;
								}
								list($address_part, $subnet_part) = explode("/", $rangesubnet);
								$input_addresses[] = $address_part;
								$input_address_subnet[] = $subnet_part;
								$final_address_details[] = $detail_text;
								$alias_address_count++;
							}
						} else {
							// For host type aliases, expand an IPv4 range into a list of individual IPv4 addresses.
							$rangeaddresses = ip_range_to_address_array($startip, $endip, $max_alias_addresses - $alias_address_count);
							if (is_array($rangeaddresses)) {
								foreach ($rangeaddresses as $rangeaddress) {
									$input_addresses[] = $rangeaddress;
									$input_address_subnet[] = "";
									$final_address_details[] = $detail_text;
									$alias_address_count++;
								}
							} else {
								$input_errors[] = sprintf(gettext('Range is too large to expand into individual host IP addresses (%s)'), $address_item);
								$input_errors[] = sprintf(gettext('The maximum number of entries in an alias is %s'), $max_alias_addresses);
								// Put the user-entered data in the output anyway, so it will be re-displayed for correction.
								$input_addresses[] = $address_item;
								$input_address_subnet[] = "";
								$final_address_details[] = $detail_text;
							}
						}
					} else if ($iprange_type == 6) {
						$input_errors[] = sprintf(gettext('IPv6 address ranges are not supported (%s)'), $address_item);
						// Put the user-entered data in the output anyway, so it will be re-displayed for correction.
						$input_addresses[] = $address_item;
						$input_address_subnet[] = "";
						$final_address_details[] = $detail_text;
					} else {
						$subnet_type = is_subnet($address_item);
						if (($_POST['type'] == "host") && $subnet_type) {
							if ($subnet_type == 4) {
								// For host type aliases, if the user enters an IPv4 subnet, expand it into a list of individual IPv4 addresses.
								if (subnet_size($address_item) <= ($max_alias_addresses - $alias_address_count)) {
									$rangeaddresses = subnetv4_expand($address_item);
									foreach ($rangeaddresses as $rangeaddress) {
										$input_addresses[] = $rangeaddress;
										$input_address_subnet[] = "";
										$final_address_details[] = $detail_text;
										$alias_address_count++;
									}
								} else {
									$input_errors[] = sprintf(gettext('Subnet is too large to expand into individual host IP addresses (%s)'), $address_item);
									$input_errors[] = sprintf(gettext('The maximum number of entries in an alias is %s'), $max_alias_addresses);
									// Put the user-entered data in the output anyway, so it will be re-displayed for correction.
									$input_addresses[] = $address_item;
									$input_address_subnet[] = "";
									$final_address_details[] = $detail_text;
								}
							} else {
								$input_errors[] = sprintf(gettext('IPv6 subnets are not supported in host aliases (%s)'), $address_item);
								// Put the user-entered data in the output anyway, so it will be re-displayed for correction.
								$input_addresses[] = $address_item;
								$input_address_subnet[] = "";
								$final_address_details[] = $detail_text;
							}
						} else {
							list($address_part, $subnet_part) = explode("/", $address_item);
							if (!empty($subnet_part)) {
								if (is_subnet($address_item)) {
									$input_addresses[] = $address_part;
									$input_address_subnet[] = $subnet_part;
								} else {
									// The user typed something like "1.2.3.444/24" or "1.2.3.0/36" or similar rubbish.
									// Feed it through without splitting it apart, then it will be caught by the validation loop below.
									$input_addresses[] = $address_item;
									$input_address_subnet[] = "";
								}
							} else {
								$input_addresses[] = $address_part;
								$input_address_subnet[] = $_POST["address_subnet{$x}"];
							}
							$final_address_details[] = $detail_text;
							$alias_address_count++;
						}
					}
					if ($alias_address_count > $max_alias_addresses) {
						$input_errors[] = sprintf(gettext('The maximum number of entries in an alias has been exceeded (%s)'), $max_alias_addresses);
						break;
					}
				}
			}
		}

		// Validate the input data expanded above.
		foreach ($input_addresses as $idx => $input_address) {
			if (is_alias($input_address)) {
				if (!alias_same_type($input_address, $_POST['type'])) {
					// But alias type network can include alias type urltable. Feature#1603.
					if (!($_POST['type'] == 'network' &&
					    preg_match("/urltable/i", alias_get_type($input_address)))) {
						$wrongaliases .= " " . $input_address;
					}
				}
			} else if ($_POST['type'] == "port") {
				if (!is_port($input_address) && !is_portrange($input_address)) {
					$input_errors[] = $input_address . " " . gettext("is not a valid port or alias.");
				}
			} else if ($_POST['type'] == "host" || $_POST['type'] == "network") {
				if (is_subnet($input_address) ||
				    (!is_ipaddr($input_address) && !is_hostname($input_address))) {
					$input_errors[] = sprintf(gettext('%1$s is not a valid %2$s address, FQDN or alias.'), $input_address, $_POST['type']);
				}
			}
			$tmpaddress = $input_address;
			if ($_POST['type'] != "host" && is_ipaddr($input_address) && $input_address_subnet[$idx] <> "") {
				if (!is_subnet($input_address . "/" . $input_address_subnet[$idx])) {
					$input_errors[] = sprintf(gettext('%s/%s is not a valid subnet.'), $input_address, $input_address_subnet[$idx]);
				} else {
					$tmpaddress .= "/" . $input_address_subnet[$idx];
				}
			}
			$address[] = $tmpaddress;
		}
		unset($desc_fmt_err_found);
		if ($wrongaliases <> "") {
			$input_errors[] = sprintf(gettext('The alias(es): %s cannot be nested because they are not of the same type.'), $wrongaliases);
		}
	}

	unset($vertical_bar_err_text);

	// Allow extending of the firewall edit page and include custom input validation
	pfSense_handle_custom_code("/usr/local/pkg/firewall_aliases_edit/input_validation");

	if (!$input_errors) {
		$alias['address'] = is_array($address) ? implode(" ", $address) : $address;
		$alias['descr'] = $_POST['descr'];
		$alias['type'] = $_POST['type'];
		$alias['detail'] = implode("||", $final_address_details);

		/*   Check to see if alias name needs to be
		 *   renamed on referenced rules and such
		 */
		if ($_POST['name'] <> $_POST['origname']) {
			// Firewall rules
			update_alias_names_upon_change(array('filter', 'rule'), array('source', 'address'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('filter', 'rule'), array('destination', 'address'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('filter', 'rule'), array('source', 'port'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('filter', 'rule'), array('destination', 'port'), $_POST['name'], $origname);
			// NAT Rules
			update_alias_names_upon_change(array('nat', 'rule'), array('source', 'address'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('nat', 'rule'), array('source', 'port'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('nat', 'rule'), array('destination', 'address'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('nat', 'rule'), array('destination', 'port'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('nat', 'rule'), array('target'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('nat', 'rule'), array('local-port'), $_POST['name'], $origname);
			// NAT 1:1 Rules
			//update_alias_names_upon_change(array('nat', 'onetoone'), array('external'), $_POST['name'], $origname);
			//update_alias_names_upon_change(array('nat', 'onetoone'), array('source', 'address'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('nat', 'onetoone'), array('destination', 'address'), $_POST['name'], $origname);
			// NAT Outbound Rules
			update_alias_names_upon_change(array('nat', 'outbound', 'rule'), array('source', 'network'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('nat', 'outbound', 'rule'), array('sourceport'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('nat', 'outbound', 'rule'), array('destination', 'address'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('nat', 'outbound', 'rule'), array('dstport'), $_POST['name'], $origname);
			update_alias_names_upon_change(array('nat', 'outbound', 'rule'), array('target'), $_POST['name'], $origname);
			// Alias in an alias
			update_alias_names_upon_change(array('aliases', 'alias'), array('address'), $_POST['name'], $origname);
		}

		pfSense_handle_custom_code("/usr/local/pkg/firewall_aliases_edit/pre_write_config");

		if (isset($id) && $a_aliases[$id]) {
			if ($a_aliases[$id]['name'] <> $alias['name']) {
				foreach ($a_aliases as $aliasid => $aliasd) {
					if ($aliasd['address'] <> "") {
						$tmpdirty = false;
						$tmpaddr = explode(" ", $aliasd['address']);
						foreach ($tmpaddr as $tmpidx => $tmpalias) {
							if ($tmpalias == $a_aliases[$id]['name']) {
								$tmpaddr[$tmpidx] = $alias['name'];
								$tmpdirty = true;
							}
						}
						if ($tmpdirty == true) {
							$a_aliases[$aliasid]['address'] = implode(" ", $tmpaddr);
						}
					}
				}
			}
			$a_aliases[$id] = $alias;
		} else {
			$a_aliases[] = $alias;
		}

		// Sort list
		$a_aliases = msort($a_aliases, "name");

		if (write_config()) {
			mark_subsystem_dirty('aliases');
		}

		if (!empty($tab)) {
			header("Location: firewall_aliases.php?tab=" . htmlspecialchars ($tab));
		} else {
			header("Location: firewall_aliases.php");
		}
		exit;
	} else {
		//we received input errors, copy data to prevent retype
		$pconfig['name'] = $_POST['name'];
		$pconfig['descr'] = $_POST['descr'];
		if (($_POST['type'] == 'url') || ($_POST['type'] == 'url_ports')) {
			$pconfig['address'] = implode(" ", $alias['aliasurl']);
		} else {
			$pconfig['address'] = implode(" ", $address);
		}
		$pconfig['type'] = $_POST['type'];
		$pconfig['detail'] = implode("||", $final_address_details);
	}
}

include("head.inc");

$jscriptstr = <<<EOD

<script type="text/javascript">
//<![CDATA[
var objAlias = new Array(4999);
function typesel_change() {
	var field_disabled = 0;
	var field_value = "";
	var set_value = false;
	switch (document.iform.type.selectedIndex) {
		case 0:	/* host */
			field_disabled = 1;
			field_value = "";
			set_value = true;
			break;
		case 1:	/* network */
			field_disabled = 0;
			break;
		case 2:	/* port */
			field_disabled = 1;
			field_value = "128";
			set_value = true;
			break;
		case 3:	/* url */
			field_disabled = 1;
			break;
		case 4:	/* url_ports */
			field_disabled = 1;
			break;
		case 5:	/* urltable */
			field_disabled = 0;
			break;
		case 6:	/* urltable_ports */
			field_disabled = 0;
			break;
	}

	jQuery("select[id^='address_subnet']").prop("disabled", field_disabled);
	if (set_value == true) {
		jQuery("select[id^='address_subnet']").prop("value", field_value);
	}
}

function add_alias_control() {
	var name = "address" + (totalrows - 1);
	obj = document.getElementById(name);
	obj.setAttribute('class', 'formfldalias');
	obj.setAttribute('autocomplete', 'off');
	objAlias[totalrows - 1] = new AutoSuggestControl(obj, new StateSuggestions(addressarray));
}
EOD;

$network_str = gettext("Network or FQDN");
$networks_str = gettext("Network(s)");
$cidr_str = gettext("CIDR");
$description_str = gettext("Description");
$hosts_str = gettext("Host(s)");
$ip_str = gettext("IP or FQDN");
$ports_str = gettext("Port(s)");
$port_str = gettext("Port");
$url_str = gettext("URL (IPs)");
$url_ports_str = gettext("URL (Ports)");
$urltable_str = gettext("URL Table (IPs)");
$urltable_ports_str = gettext("URL Table (Ports)");
$update_freq_str = gettext("Update Freq. (days)");

$networks_help = gettext("Networks are specified in CIDR format.  Select the CIDR mask that pertains to each entry. /32 specifies a single IPv4 host, /128 specifies a single IPv6 host, /24 specifies 255.255.255.0, /64 specifies a normal IPv6 network, etc. Hostnames (FQDNs) may also be specified, using a /32 mask for IPv4 or /128 for IPv6. You may also enter an IP range such as 192.168.1.1-192.168.1.254 and a list of CIDR networks will be derived to fill the range.");
$hosts_help = gettext("Enter as many hosts as you would like.  Hosts must be specified by their IP address or fully qualified domain name (FQDN). FQDN hostnames are periodically re-resolved and updated. If multiple IPs are returned by a DNS query, all are used. You may also enter an IP range such as 192.168.1.1-192.168.1.10 or a small subnet such as 192.168.1.16/28 and a list of individual IP addresses will be generated.");
$ports_help = gettext("Enter as many ports as you wish.  Port ranges can be expressed by separating with a colon.");
$url_help = sprintf(gettext("Enter as many URLs as you wish. After saving %s will download the URL and import the items into the alias. Use only with small sets of IP addresses (less than 3000)."), $g['product_name']);
$url_ports_help = sprintf(gettext("Enter as many URLs as you wish. After saving %s will download the URL and import the items into the alias. Use only with small sets of Ports (less than 3000)."), $g['product_name']);
$urltable_help = sprintf(gettext("Enter a single URL containing a large number of IPs and/or Subnets. After saving %s will download the URL and create a table file containing these addresses. This will work with large numbers of addresses (30,000+) or small numbers."), $g['product_name']);
$urltable_ports_help = sprintf(gettext("Enter a single URL containing a list of Port numbers and/or Port ranges. After saving %s will download the URL."), $g['product_name']);

$openvpn_str = gettext("Username");
$openvpn_user_str = gettext("OpenVPN Users");
$openvpn_help = gettext("Enter as many usernames as you wish.");
$openvpn_freq = "";

$jscriptstr .= <<<EOD

function update_box_type() {
	var indexNum = document.forms[0].type.selectedIndex;
	var selected = document.forms[0].type.options[indexNum].text;
	if (selected == '{$networks_str}') {
		document.getElementById ("addressnetworkport").firstChild.data = "{$networks_str}";
		document.getElementById ("onecolumn").firstChild.data = "{$network_str}";
		document.getElementById ("twocolumn").firstChild.data = "{$cidr_str}";
		document.getElementById ("threecolumn").firstChild.data = "{$description_str}";
		document.getElementById ("threecolumn").style.display = 'block';
		document.getElementById ("itemhelp").firstChild.data = "{$networks_help}";
		document.getElementById ("addrowbutton").style.display = 'block';
	} else if (selected == '{$hosts_str}') {
		document.getElementById ("addressnetworkport").firstChild.data = "{$hosts_str}";
		document.getElementById ("onecolumn").firstChild.data = "{$ip_str}";
		document.getElementById ("twocolumn").firstChild.data = "";
		document.getElementById ("threecolumn").firstChild.data = "{$description_str}";
		document.getElementById ("threecolumn").style.display = 'block';
		document.getElementById ("itemhelp").firstChild.data = "{$hosts_help}";
		document.getElementById ("addrowbutton").style.display = 'block';
	} else if (selected == '{$ports_str}') {
		document.getElementById ("addressnetworkport").firstChild.data = "{$ports_str}";
		document.getElementById ("onecolumn").firstChild.data = "{$port_str}";
		document.getElementById ("twocolumn").firstChild.data = "";
		document.getElementById ("threecolumn").firstChild.data = "{$description_str}";
		document.getElementById ("threecolumn").style.display = 'block';
		document.getElementById ("itemhelp").firstChild.data = "{$ports_help}";
		document.getElementById ("addrowbutton").style.display = 'block';
	} else if (selected == '{$url_str}') {
		document.getElementById ("addressnetworkport").firstChild.data = "{$url_str}";
		document.getElementById ("onecolumn").firstChild.data = "{$url_str}";
		document.getElementById ("twocolumn").firstChild.data = "";
		document.getElementById ("threecolumn").firstChild.data = "{$description_str}";
		document.getElementById ("threecolumn").style.display = 'block';
		document.getElementById ("itemhelp").firstChild.data = "{$url_help}";
		document.getElementById ("addrowbutton").style.display = 'block';
	} else if (selected == '{$url_ports_str}') {
		document.getElementById ("addressnetworkport").firstChild.data = "{$url_ports_str}";
		document.getElementById ("onecolumn").firstChild.data = "{$url_ports_str}";
		document.getElementById ("twocolumn").firstChild.data = "";
		document.getElementById ("threecolumn").firstChild.data = "{$description_str}";
		document.getElementById ("threecolumn").style.display = 'block';
		document.getElementById ("itemhelp").firstChild.data = "{$url_ports_help}";
		document.getElementById ("addrowbutton").style.display = 'block';
	} else if (selected == '{$openvpn_user_str}') {
		document.getElementById ("addressnetworkport").firstChild.data = "{$openvpn_user_str}";
		document.getElementById ("onecolumn").firstChild.data = "{$openvpn_str}";
		document.getElementById ("twocolumn").firstChild.data = "{$openvpn_freq}";
		document.getElementById ("threecolumn").firstChild.data = "{$description_str}";
		document.getElementById ("threecolumn").style.display = 'block';
		document.getElementById ("itemhelp").firstChild.data = "{$openvpn_help}";
		document.getElementById ("addrowbutton").style.display = 'block';
	} else if (selected == '{$urltable_str}') {
		if ((typeof(totalrows) == "undefined") || (totalrows < 1)) {
			addRowTo('maintable', 'formfldalias');
			typesel_change();
			add_alias_control(this);
		}
		document.getElementById ("addressnetworkport").firstChild.data = "{$url_str}";
		document.getElementById ("onecolumn").firstChild.data = "{$url_str}";
		document.getElementById ("twocolumn").firstChild.data = "{$update_freq_str}";
		document.getElementById ("threecolumn").firstChild.data = "";
		document.getElementById ("threecolumn").style.display = 'none';
		document.getElementById ("itemhelp").firstChild.data = "{$urltable_help}";
		document.getElementById ("addrowbutton").style.display = 'none';
	} else if (selected == '{$urltable_ports_str}') {
		if ((typeof(totalrows) == "undefined") || (totalrows < 1)) {
			addRowTo('maintable', 'formfldalias');
			typesel_change();
			add_alias_control(this);
		}
		document.getElementById ("addressnetworkport").firstChild.data = "{$url_str}";
		document.getElementById ("onecolumn").firstChild.data = "{$url_str}";
		document.getElementById ("twocolumn").firstChild.data = "{$update_freq_str}";
		document.getElementById ("threecolumn").firstChild.data = "";
		document.getElementById ("threecolumn").style.display = 'none';
		document.getElementById ("itemhelp").firstChild.data = "{$urltable_ports_help}";
		document.getElementById ("addrowbutton").style.display = 'none';
	}
}
//]]>
</script>

EOD;

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?= $jsevents["body"]["onload"] ?>">
<?php
	include("fbegin.inc");
	echo $jscriptstr;
?>

<script type="text/javascript" src="/javascript/jquery.ipv4v6ify.js"></script>
<script type="text/javascript" src="/javascript/row_helper.js"></script>
<script type="text/javascript" src="/javascript/autosuggest.js?rev=1"></script>
<script type="text/javascript" src="/javascript/suggestions.js"></script>

<input type='hidden' name='address_type' value='textbox' />
<input type='hidden' name='address_subnet_type' value='select' />

<script type="text/javascript">
//<![CDATA[
	rowname[0] = "address";
	rowtype[0] = "textbox,ipv4v6";
	rowsize[0] = "30";

	rowname[1] = "address_subnet";
	rowtype[1] = "select,ipv4v6";
	rowsize[1] = "1";

	rowname[2] = "detail";
	rowtype[2] = "textbox";
	rowsize[2] = "50";
//]]>
</script>

<?php pfSense_handle_custom_code("/usr/local/pkg/firewall_aliases_edit/pre_input_errors"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<div id="inputerrors"></div>

<form action="firewall_aliases_edit.php" method="post" name="iform" id="iform">
<?php
if (empty($tab)) {
	if (preg_match("/url/i", $pconfig['type'])) {
		$tab = 'url';
	} else if ($pconfig['type'] == 'host') {
		$tab = 'ip';
	} else {
		$tab = $pconfig['type'];
	}
}
?>
<input name="tab" type="hidden" id="tab" value="<?=htmlspecialchars($tab);?>" />
<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="firewall aliases edit">
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?=gettext("Alias Edit"); ?></td>
	</tr>
	<tr>
		<td valign="top" class="vncellreq"><?=gettext("Name"); ?></td>
		<td class="vtable">
			<input name="origname" type="hidden" id="origname" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['name']);?>" />
			<input name="name" type="text" id="name" class="formfld unknown" size="40" maxlength="31" value="<?=htmlspecialchars($pconfig['name']);?>" />
			<?php if (isset($id) && $a_aliases[$id]): ?>
				<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
			<?php endif; ?>
			<br />
			<span class="vexpl">
				<?=gettext("The name of the alias may only consist of the characters \"a-z, A-Z, 0-9 and _\"."); ?>
			</span>
		</td>
	</tr>
	<?php pfSense_handle_custom_code("/usr/local/pkg/firewall_aliases_edit/after_first_tr"); ?>
	<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
		<td width="78%" class="vtable">
			<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
			<br />
			<span class="vexpl">
				<?=gettext("You may enter a description here for your reference (not parsed)."); ?>
			</span>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncellreq"><?=gettext("Type"); ?></td>
		<td class="vtable">
			<select name="type" class="formselect" id="type" onchange="update_box_type(); typesel_change();">
				<option value="host" <?php if ($pconfig['type'] == "host") echo "selected=\"selected\""; ?>><?=gettext("Host(s)"); ?></option>
				<option value="network" <?php if ($pconfig['type'] == "network") echo "selected=\"selected\""; ?>><?=gettext("Network(s)"); ?></option>
				<option value="port" <?php if (($pconfig['type'] == "port") || (empty($pconfig['type']) && ($tab == "port"))) echo "selected=\"selected\""; ?>><?=gettext("Port(s)"); ?></option>
				<!--<option value="openvpn" <?php if ($pconfig['type'] == "openvpn") echo "selected=\"selected\""; ?>><?=gettext("OpenVPN Users"); ?></option> -->
				<option value="url" <?php if (($pconfig['type'] == "url") || (empty($pconfig['type']) && ($tab == "url"))) echo "selected=\"selected\""; ?>><?=gettext("URL (IPs)");?></option>
				<option value="url_ports" <?php if ($pconfig['type'] == "url_ports") echo "selected=\"selected\""; ?>><?=gettext("URL (Ports)");?></option>
				<option value="urltable" <?php if ($pconfig['type'] == "urltable") echo "selected=\"selected\""; ?>><?=gettext("URL Table (IPs)"); ?></option>
				<option value="urltable_ports" <?php if ($pconfig['type'] == "urltable_ports") echo "selected=\"selected\""; ?>><?=gettext("URL Table (Ports)"); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncellreq"><div id="addressnetworkport"><?=gettext("Host(s)"); ?></div></td>
		<td width="78%" class="vtable">
			<table id="maintable" summary="maintable">
				<tbody>
					<tr>
						<td colspan="4">
							<div style="padding:5px; margin-top: 16px; margin-bottom: 16px; border:1px dashed #000066; background-color: #ffffff; color: #000000; font-size: 8pt;" id="itemhelp"><?=gettext("Item information"); ?></div>
						</td>
					</tr>
					<tr>
						<td><div id="onecolumn"><?=gettext("Network"); ?></div></td>
						<td><div id="twocolumn">CIDR</div></td>
						<td><div id="threecolumn"><?=gettext("Description"); ?></div></td>
					</tr>

					<?php
					$counter = 0;
					if ($pconfig['address'] <> ""):
						$addresses = explode(" ", $pconfig['address']);
						$details = explode("||", $pconfig['detail']);
						while ($counter < count($addresses)):
							if (($pconfig['type'] != "host") && is_subnet($addresses[$counter])) {
								list($address, $address_subnet) = explode("/", $addresses[$counter]);
							} else {
								$address = $addresses[$counter];
								$address_subnet = "";
							}
					?>
					<tr>
						<td>
							<input autocomplete="off" name="address<?php echo $counter; ?>" type="text" class="formfldalias ipv4v6" id="address<?php echo $counter; ?>" size="30" value="<?=htmlspecialchars($address);?>" />
						</td>
						<td>
							<select name="address_subnet<?php echo $counter; ?>" class="formselect ipv4v6" id="address_subnet<?php echo $counter; ?>">
								<option></option>
								<?php for ($i = 128; $i >= 1; $i--): ?>
									<option value="<?=$i;?>" <?php if (($i == $address_subnet) || ($i == $pconfig['updatefreq'])) echo "selected=\"selected\""; ?>><?=$i;?></option>
								<?php endfor; ?>
							</select>
						</td>
						<td>
							<input name="detail<?php echo $counter; ?>" type="text" class="formfld unknown" id="detail<?php echo $counter; ?>" size="50" value="<?=htmlspecialchars($details[$counter]);?>" />
						</td>
						<td>
							<a onclick="removeRow(this); return false;" href="#"><img border="0" src="/themes/<?echo $g['theme'];?>/images/icons/icon_x.gif" alt="" title="<?=gettext("remove this entry"); ?>" /></a>
						</td>
					</tr>
					<?php
						$counter++;

						endwhile;
					endif;
					?>
				</tbody>
			</table>
			<div id="addrowbutton">
				<a onclick="javascript:addRowTo('maintable', 'formfldalias'); typesel_change(); add_alias_control(this); return false;" href="#">
					<img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" title="<?=gettext("add another entry"); ?>" />
				</a>
			</div>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top">&nbsp;</td>
		<td width="78%">
			<input id="submit" name="submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
			<input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
		</td>
	</tr>
</table>
</form>

<script type="text/javascript">
//<![CDATA[
	field_counter_js = 3;
	rows = 1;
	totalrows = <?php echo $counter; ?>;
	loaded = <?php echo $counter; ?>;
	typesel_change();
	update_box_type();

	var addressarray = <?= json_encode(array_exclude($pconfig['name'], get_alias_list($pconfig['type']))) ?>;

	function createAutoSuggest() {
		<?php
		for ($jv = 0; $jv < $counter; $jv++) {
			echo "objAlias[{$jv}] = new AutoSuggestControl(document.getElementById(\"address{$jv}\"), new StateSuggestions(addressarray));\n";
		}
		?>
	}

	setTimeout("createAutoSuggest();", 500);
//]]>
</script>

<?php include("fend.inc"); ?>
</body>
</html>
