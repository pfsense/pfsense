<?php
/*
	firewall_aliases_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-firewall-alias-edit
##|*NAME=Firewall: Alias: Edit
##|*DESCR=Allow access to the 'Firewall: Alias: Edit' page.
##|*MATCH=firewall_aliases_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$pgtitle = array(gettext("Firewall"), gettext("Aliases"), gettext("Edit"));

if (isset($_POST['referer'])) {
	$referer = $_POST['referer'];
} else {
	$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_aliases.php');
}

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

$singular_types = array(
	'host'	=> gettext("host"),
	'network' => gettext("network"),
	'port' => gettext("port"),
	'url' => gettext("URL (IP)"),
	'url_ports' => gettext("URL (Port)"),
	'urltable' => gettext("URL Table (IP)"),
	'urltable_ports' => gettext("URL Table (Port)"),
);

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

	if (!is_validaliasname($_POST['name'])) {
		$input_errors[] = invalidaliasnamemsg($_POST['name']);
	}

	/* check for name conflicts */
	foreach ($a_aliases as $key => $alias) {
		if (($alias['name'] == $_POST['name']) && (empty($a_aliases[$id]) || ($key != $id))) {
			$input_errors[] = gettext("An alias with this name already exists.");
			break;
		}
	}

	/* Check for reserved keyword names */
	foreach ($reserved_keywords as $rk) {
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
				$input_errors[] = gettext("A valid URL must be provided.");
			} elseif (!process_alias_urltable($alias['name'], $alias['url'], 0, true, true)) {
				$input_errors[] = gettext("Unable to fetch usable data from URL") . " " . htmlspecialchars($alias['url']);
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
				if (!download_file($_POST['address' . $x], $temp_filename . "/aliases", $verify_ssl)) {
					continue;
				}

				/* if the item is tar gzipped then extract */
				if (stristr($_POST['address' . $x], ".tgz")) {
					process_alias_tgz($temp_filename);
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
					$address = parse_aliases_file("{$temp_filename}/aliases", $_POST['type'], 5000);
					if ($address == null) {
						/* nothing was found */
						$input_errors[] = sprintf(gettext("A valid URL must be provided. Could not fetch usable data from '%s'."), $_POST['address' . $x]);
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
		$input_addresses = array();

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
								$subnet_size = subnet_size($address_item);
								if ($subnet_size > 0 &&
								    $subnet_size <= ($max_alias_addresses - $alias_address_count)) {
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
					$input_errors[] = sprintf(gettext("%s is not a valid port or alias."), $input_address);
				}
			} else if ($_POST['type'] == "host" || $_POST['type'] == "network") {
				if (is_subnet($input_address) ||
				    (!is_ipaddr($input_address) && !is_hostname($input_address))) {
					$input_errors[] = sprintf(gettext('%1$s is not a valid %2$s address, FQDN or alias.'), $input_address, $singular_types[$_POST['type']]);
				}
			}
			$tmpaddress = $input_address;
			if ($_POST['type'] != "host" && is_ipaddr($input_address) && $input_address_subnet[$idx] <> "") {
				if (!is_subnet($input_address . "/" . $input_address_subnet[$idx])) {
					$input_errors[] = sprintf(gettext('%1$s/%2$s is not a valid subnet.'), $input_address, $input_address_subnet[$idx]);
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

		/*	 Check to see if alias name needs to be
		 *	 renamed on referenced rules and such
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

$section_str = array(
	'network' => gettext("Network(s)"),
	'host'	=> gettext("Host(s)"),
	'port' => gettext("Port(s)"),
	'url' => gettext("URL (IPs)"),
	'url_ports' => gettext("URL (Ports)"),
	'urltable' => gettext("URL Table (IPs)"),
	'urltable_ports' => gettext("URL Table (Ports)")
);

$btn_str = array(
	'network' => gettext("Add Network"),
	'host'	=> gettext("Add Host"),
	'port' => gettext("Add Port"),
	'url' => gettext("Add URL"),
	'url_ports' => gettext("Add URL"),
	'urltable' => gettext("Add URL Table"),
	'urltable_ports' => gettext("Add URL Table")
);

$label_str = array(
	'network' => gettext("Network or FQDN"),
	'host'	=> gettext("IP or FQDN"),
	'port' => gettext("Port"),
	'url' => gettext("URL (IPs)"),
	'url_ports' => gettext("URL (Ports)"),
	'urltable' => gettext("URL Table (IPs)"),
	'urltable_ports' => gettext("URL Table (Ports)")
);

$special_cidr_usage_text = gettext("The value after the \"/\" is the update frequency in days.");

$help = array(
	'network' => gettext("Networks are specified in CIDR format. Select the CIDR mask that pertains to each entry. /32 specifies a single IPv4 host, /128 specifies a single IPv6 host, /24 specifies 255.255.255.0, /64 specifies a normal IPv6 network, etc. Hostnames (FQDNs) may also be specified, using a /32 mask for IPv4 or /128 for IPv6. An IP range such as 192.168.1.1-192.168.1.254 may also be entered and a list of CIDR networks will be derived to fill the range."),
	'host' => gettext("Enter as many hosts as desired. Hosts must be specified by their IP address or fully qualified domain name (FQDN). FQDN hostnames are periodically re-resolved and updated. If multiple IPs are returned by a DNS query, all are used. An IP range such as 192.168.1.1-192.168.1.10 or a small subnet such as 192.168.1.16/28 may also be entered and a list of individual IP addresses will be generated."),
	'port' => gettext("Enter as many ports as desired. Port ranges can be expressed by separating with a colon."),
	'url' => gettext("Enter as many URLs as desired. After saving, the URLs will be downloaded and the items imported into the alias. Use only with small sets of IP addresses (less than 3000)."),
	'url_ports' => gettext("Enter as many URLs as desired. After saving, the URLs will be downloaded and the items imported into the alias. Use only with small sets of Ports (less than 3000)."),
	'urltable' => gettext("Enter a single URL containing a large number of IPs and/or Subnets. After saving, the URLs will be downloaded and a table file containing these addresses will be created. This will work with large numbers of addresses (30,000+) or small numbers.") .
		"<br /><b>" . $special_cidr_usage_text . "</b>",
	'urltable_ports' => gettext("Enter a single URL containing a list of Port numbers and/or Port ranges. After saving, the URL will be downloaded.") .
		"<br /><b>" . $special_cidr_usage_text . "</b>",
);

// Tab type specific patterns.
// Intentionally loose (valid character check only, no pattern recognition).
// Can be tightened up with pattern recognition as desired for each tab type.
// Network and host types allow an optional CIDR following the address or an address range using dash separator,
// and there may be multiple items separated by spaces - "192.168.1.0/24 192.168.2.4-192.168.2.19"
// On submit, strings like that are parsed and expanded into the appropriate individual entries and then validated.
$pattern_str = array(
	'network'			=> '[a-zA-Z0-9_:.-]+(/[0-9]+)?( [a-zA-Z0-9_:.-]+(/[0-9]+)?)*',	// Alias Name, Host Name, IP Address, FQDN, Network or IP Address Range
	'host'				=> '[a-zA-Z0-9_:.-]+(/[0-9]+)?( [a-zA-Z0-9_:.-]+(/[0-9]+)?)*',	// Alias Name, Host Name, IP Address, FQDN
	'port'				=> '[a-zA-Z0-9_:]+',	// Alias Name, Port Number, or Port Number Range
	'url'				=> '.*',				// Alias Name or URL
	'url_ports'			=> '.*',				// Alias Name or URL
	'urltable'			=> '.*',				// Alias Name or URL
	'urltable_ports'	=> '.*'					// Alias Name or URL
);

$types = array(
	'host'	=> gettext("Host(s)"),
	'network' => gettext("Network(s)"),
	'port' => gettext("Port(s)"),
	'url' => gettext("URL (IPs)"),
	'url_ports' => gettext("URL (Ports)"),
	'urltable' => gettext("URL Table (IPs)"),
	'urltable_ports' => gettext("URL Table (Ports)"),
);

if (empty($tab)) {
	if (preg_match("/url/i", $pconfig['type'])) {
		$tab = 'url';
	} else if ($pconfig['type'] == 'host') {
		$tab = 'ip';
	} else {
		$tab = $pconfig['type'];
	}
}

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

$form->addGlobal(new Form_Input(
	'origname',
	null,
	'hidden',
	$pconfig['name']
));

if (isset($id) && $a_aliases[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$section = new Form_Section('Properties');

$section->addInput(new Form_Input(
	'name',
	'Name',
	'text',
	$pconfig['name']
))->setPattern('[a-zA-Z0-9_]+')->setHelp('The name of the alias may only consist '.
	'of the characters "a-z, A-Z, 0-9 and _".');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_Select(
	'type',
	'Type',
	isset($pconfig['type']) ? $pconfig['type'] : $tab,
	$types
));

$form->add($section);

$section = new Form_Section($section_str[$tab]);
// Make somewhere to park the help text, and give it a class so we can update it later
$section->addInput(new Form_StaticText(
	'Hint',
	'<span class="helptext">' . $help[$tab] . '</span>'
));

// If no addresses have been defined, we'll make up a blank set
if ($pconfig['address'] == "") {
	$pconfig['address'] = '';
	$pconfig['address_subnet'] = '';
	$pconfig['detail'] = '';
}

$counter = 0;
$addresses = explode(" ", $pconfig['address']);
$details = explode("||", $pconfig['detail']);

while ($counter < count($addresses)) {
	if (($pconfig['type'] != "host") && is_subnet($addresses[$counter])) {
		list($address, $address_subnet) = explode("/", $addresses[$counter]);
	} else {
		$address = $addresses[$counter];
		if (isset($pconfig['updatefreq'])) {
			// Note: There is only 1 updatefreq possible.
			// The alias types that use updatefreq only allow a single entry.
			$address_subnet = $pconfig['updatefreq'];
		} else {
			$address_subnet = "";
		}
	}

	$group = new Form_Group($counter == 0 ? $label_str[$tab]:'');
	$group->addClass('repeatable');

	$group->add(new Form_IpAddress(
		'address' . $counter,
		$tab == 'port' ? 'Port':'Address',
		$address
	))->addMask('address_subnet' . $counter, $address_subnet)->setWidth(4)->setPattern($pattern_str[$tab]);

	$group->add(new Form_Input(
		'detail' . $counter,
		'Description',
		'text',
		$details[$counter]
	))->setWidth(4);

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-trash'
	))->addClass('btn-warning');

	$section->add($group);
	$counter++;
}

$form->addGlobal(new Form_Button(
	'addrow',
	$btn_str[$tab],
	null,
	'fa-plus'
))->addClass('btn-success addbtn');

$form->add($section);

print $form;
?>

<script type="text/javascript">
//<![CDATA[
addressarray = <?= json_encode(array_exclude($pconfig['name'], get_alias_list($pconfig['type']))) ?>;

events.push(function() {

	var disable_subnets;

	function typechange() {
		var tab = $('#type').find('option:selected').val();

		disable_subnets = (tab == 'host') || (tab == 'port') || (tab == 'url') || (tab == 'url_ports');

		// Enable/disable address_subnet so its value gets POSTed or not, as appropriate.
		$("[id^='address_subnet']").prop("disabled", disable_subnets);

		// Show or hide the slash plus address_subnet field so the user does not even see it if it is not relevant.
		hideMask('address_subnet', disable_subnets);

		// Set the help text to match the tab
		var helparray = <?=json_encode($help);?>;
		$('.helptext').html(helparray[tab]);

		// Set the section heading by tab type
		var sectionstr = <?=json_encode($section_str);?>;
		$('.panel-title:last').text(sectionstr[tab]);

		var buttonstr = <?=json_encode($btn_str);?>;
		$('.btn-success').prop('value', buttonstr[tab]);
		$('.btn-success').html('<i class="fa fa-plus icon-embed-btn"></i>' + buttonstr[tab]);

		// Set the input field label by tab
		var labelstr = <?=json_encode($label_str);?>;
		$('.repeatable:first').find('label').text(labelstr[tab]);

		// Set the input field pattern by tab type
		var patternstr = <?=json_encode($pattern_str);?>;
		for (i = 0; i < <?=$counter;?>; i++) {
			$('#address' + i).prop('pattern', patternstr[tab]);
		}

		// Hide and disable rows other than the first
		hideRowsAfter(1, (tab == 'urltable') || (tab == 'urltable_ports'));

		// The add button and delete buttons must not show on URL Table IP or URL table ports
		if ((tab == 'urltable') || (tab == 'urltable_ports')) {
			hideClass('addbtn', true);
			$('[id^=deleterow]').hide();
		} else {
			hideClass('addbtn', false);
			$('[id^=deleterow]').show();
		}
	}

	// Hide and disable all rows >= that specified
	function hideRowsAfter(row, hide) {
		var idx = 0;

		$('.repeatable').each(function(el) {
			if (idx >= row) {
				hideRow(idx, hide);
			}

			idx++;
		});
	}

	function hideRow(row, hide) {
		if (hide) {
			$('#deleterow' + row).parent('div').parent().addClass('hidden');
		} else {
			$('#deleterow' + row).parent('div').parent().removeClass('hidden');
		}

		// We need to disable the elements so they are not submitted in the POST
		$('#address' + row).prop("disabled", hide);
		$('#address_subnet' + row).prop("disabled", hide || disable_subnets);
		$('#detail' + row).prop("disabled", hide);
		$('#deleterow' + row).prop("disabled", hide);
	}

	// On load . .
	typechange();

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();

	// Autocomplete
	$('[id^=address]').each(function() {
		if (this.id.substring(0, 8) != "address_") {
			$(this).autocomplete({
				source: addressarray
			});
		}
	});

	// on click . .
	$('#type').on('change', function() {
		typechange();
	});

});
//]]>
</script>

<?php
include("foot.inc");
