<?php
/*
 * diag_packet_capture.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2023 Rubicon Communications, LLC (Netgate)
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

/**
 * Packet Capture Filter Section
 * The Filter Section represents the number of times a packet has been tagged.
 * It is used to indicate that a particular filter string search should be
 * offset 0 (untagged) or more (tagged once or more, e.g. QinQ) times. This is
 * needed because specifying the 'vlan' keyword offsets the search for any
 * input after it. The Filter Section offset directly relates to the number of
 * times the 'vlan' keyword is used.
 */
define('PCAP_FSECTION_UNTAGGED', 0);
define('PCAP_FSECTION_SINGLETAGGED', 1);
define('PCAP_FSECTION_DOUBLETAGGED', 2);
define('PCAP_FSECTION_PRESET', 9);

/**
 * Packet Capture Match
 * The Match represents the logical operator used between Attribute operands.
 * In the following filter string for example, 'and' is the Match operator
 * between the 'port' and 'host' Attribute operands:
 * 'port 80 and (host 10.0.0.1 or host 10.0.0.2)'
 * The Match operator is also used between Filter Sections. In the following
 * filter string for, 'or' is the Match operator between two Filter Sections:
 * 'arp or (vlan and icmp)'. The 'PCAP_MATCH_NONE' match is used when excluding
 * an entire Filter Section.
 */
define('PCAP_MATCH_NONE', 10);
define('PCAP_MATCH_NONEOF', 11);
define('PCAP_MATCH_AND_ANYOF', 12);
define('PCAP_MATCH_OR_ANYOF', 13);

/**
 * Packet Capture Filter Type
 * The Filter Type represents a preset or custom filter string. The 'ANY'
 * preset will capture both untagged and tagged packets. The 'CUSTOM' preset
 * allows for a user-specified filter based on the given Attribute(s).
 */
define('PCAP_FTYPE_ANY', 20);
define('PCAP_FTYPE_UNTAGGED', 21);
define('PCAP_FTYPE_TAGGED', 22);
define('PCAP_FTYPE_CUSTOM', 29);

/**
 * Packet Capture Filter Attribute
 * The Filter Attribute represents the search scope of the input value. For
 * example, in the filter string 'port 80', 'port' is the Filter Attribute,
 * and '80' is the value. The 'PCAP_FATTR_PRESET" attribute is used when
 * a Filter Type preset is selected. The 'PCAP_FATTR_FSECTION_MATCH" attribute
 * defines the logical operator for the respective filter section. It is used
 * when excluding the entire Filter Section.
 */
define('PCAP_FATTR_VLAN', 30);
define('PCAP_FATTR_ETHERTYPE', 31);
define('PCAP_FATTR_PROTOCOL', 32);
define('PCAP_FATTR_IPADDRESS', 33);
define('PCAP_FATTR_MACADDRESS', 34);
define('PCAP_FATTR_PORT', 35);
define('PCAP_FATTR_PRESET', 38);
define('PCAP_FATTR_FSECTION_MATCH', 39);

/**
 * The FilterAttribute class defines an Attribute and contains the
 * associated input value. It is used when generating a filter string.
 */
class FilterAttribute {
	public int $type = 0;
	private int $section = 0;
	private int $operator = 0;
	private string $input_string = '';
	private string $filter_string = '';
	private bool $exclude = false;
	private bool $required = false;

	/**
	 * @param int $section Which Filter Section this attribute applies to.
	 * @param int $operator The operator to use when multiple input values are given for this attribute.
	 * @param int $type The Attribute the input applies to.
	 * 
	 * @throws Exception For an invalid Attribute and operator combination.
	 * @return FilterAttribute The defined Attribute object.
	 */
	public function __construct(int $filter_section, int $attribute_operator, int $attribute_type) {
		// Validate and set the Attribute
		if (in_array($attribute_type, array(PCAP_FATTR_VLAN, PCAP_FATTR_ETHERTYPE, PCAP_FATTR_PROTOCOL,
		    PCAP_FATTR_IPADDRESS, PCAP_FATTR_MACADDRESS, PCAP_FATTR_PORT, PCAP_FATTR_PRESET,
		    PCAP_FATTR_FSECTION_MATCH))) {
			$this->type = $attribute_type;
		} else {
			throw new Exception("Invalid Attribute given: " .
			                    "{$filter_section}:{$attribute_operator}:{$attribute_type}");
		}

		// Validate and set the Filter Section
		if (in_array($filter_section, array(PCAP_FSECTION_UNTAGGED, PCAP_FSECTION_SINGLETAGGED,
		    PCAP_FSECTION_DOUBLETAGGED, PCAP_FSECTION_PRESET))) {
			$this->section = $filter_section;
		} else {
			throw new Exception("Invalid Filter Section given: " .
			                    "{$filter_section}:{$attribute_operator}:{$attribute_type}");
		}

		// Validate and set the Attribute Match
		if ($attribute_type == PCAP_FATTR_PRESET) {
			// A valid Filter Type preset must be used
			if ($filter_section == PCAP_FSECTION_PRESET && !in_array($attribute_operator,
			    array(PCAP_FTYPE_UNTAGGED, PCAP_FTYPE_TAGGED, PCAP_FTYPE_ANY))) {
				throw new Exception("Invalid Filter Type preset given: " .
				                    "{$filter_section}:{$attribute_operator}:{$attribute_type}");
			}
		} elseif ($attribute_type == PCAP_FATTR_FSECTION_MATCH) {
			// This Attribute Match is for a Filter Section
			if (in_array($attribute_operator, array(PCAP_MATCH_OR_ANYOF, PCAP_MATCH_NONE,
			    PCAP_MATCH_AND_ANYOF, PCAP_MATCH_NONEOF))) {
				if ($attribute_operator == PCAP_MATCH_NONEOF) {
					// Exclude the entire Filter Section
					$this->exclude = true;
				} elseif ($filter_section == PCAP_FSECTION_UNTAGGED && !in_array($attribute_operator,
				          (array(PCAP_MATCH_NONE, PCAP_MATCH_OR_ANYOF)))) {
					// A packet is either tagged or untagged; using 'and' signifies it's both and is invalid
					throw new Exception("Invalid match operator used between untagged and tagged filter section: " .
					                    "{$filter_section}:{$attribute_operator}:{$attribute_type}");
				}
			} else {
				throw new Exception("Invalid match operator given for filter section: " .
				                    "{$filter_section}:{$attribute_operator}:{$attribute_type}");
			}
		} else {
			switch ($attribute_operator) {
				case PCAP_MATCH_NONEOF:
					// Exclude this Attribute's input from the packet capture
					$this->required = true;
					$this->exclude = true;
					break;
				case PCAP_MATCH_AND_ANYOF:
					// The captured packets must match this Attribute's input
					$this->required = true;
					break;
				case PCAP_MATCH_OR_ANYOF:
					// The captures packetsOptionally include this attribute's input in capture
					$this->required = false;
					break;
				default:
					throw new Exception("Invalid match operator given for Attribute: " .
					                    "{$filter_section}:{$attribute_operator}:{$attribute_type}");
					break;
			}
		}
		$this->operator = $attribute_operator;
	}

	public function getFilterSection() {
		return $this->section;
	}

	public function getMatch() {
		return $this->operator;
	}

	public function getInputString() {
		return $this->input_string;
	}

	public function getFilterString() {
		return $this->filter_string;
	}

	public function getIsExcluded() {
		return $this->exclude;
	}

	public function getIsRequired() {
		return $this->required;
	}

	public function setInputString(string $input_string) {
		$this->input_string = $input_string;
		// Only set a filter string for a valid Attribute
		if (in_array($this->type, array(PCAP_FATTR_VLAN, PCAP_FATTR_ETHERTYPE, PCAP_FATTR_PROTOCOL,
		    PCAP_FATTR_IPADDRESS, PCAP_FATTR_MACADDRESS, PCAP_FATTR_PORT))) {
			$this->setFilterString();
		}
	}

	/**
	 * Constructs a filter string for a specific Attribute using this
	 * FilterAttribute object's properties.
	 * 
	 * @throws Exception If the input_string property contains invalid input.
	 */
	private function setFilterString() {
		// Set an empty string if the FilterAttribute has no input
		if (empty($this->input_string) && $this->input_string != '0') {
			$this->filter_string = '';
			return null;
		}

		// Determine if the FilterAttribute is being explicitly excluded
		$filter_string = '';
		if ($this->getIsExcluded()) {
			$prefix = 'not ';
			$infix = ' and not ';
		} else {
			$prefix = '';
			$infix = ' or ';
		}

		// Parse the FilterAttribute's input and construct a filter string
		$string_items = []; // e.g. ['port 80', 'port 443']
		$string_part = ''; // e.g. 'port 80'
		$string_values = preg_split('/\s+/', $this->getInputString(), -1, PREG_SPLIT_NO_EMPTY); // e.g. ['80', '443']
		$input_error = '';
		switch ($this->type) {
			case PCAP_FATTR_VLAN:
				// Determine where to look for the VLAN tag
				if ($this->getFilterSection() == PCAP_FSECTION_SINGLETAGGED) {
					$vlan_offset = 'ether[14:2]';
				} else {
					$vlan_offset = 'ether[18:2]';
				}
				foreach ($string_values as $value) {
					// Validate input values
					$vlan_tag = intval($value, 10);
					if ($vlan_tag >= 0 && $vlan_tag <= 4095) {
						$string_part = "{$vlan_offset}=={$vlan_tag}";
					} else {
						$input_error = "Invalid VLAN tag: {$value}";
						break;
					}
					if (!empty($string_part)) {
						$string_items[] = $string_part;
					}
				}
				break;
			case PCAP_FATTR_ETHERTYPE:
				foreach ($string_values as $value) {
					// Validate input values
					switch ($value) {
						case 'ipv4':
							$string_part = 'ip';
							break;
						case 'ipv6';
							$string_part = 'ip6';
							break;
						case 'arp';
							$string_part = 'arp';
							break;
						default:
							if (preg_match('/^(?:0x)?[0-9a-f]{4}$/i', $value)) {
								if (mb_stripos($value, '0x') === false) {
									$string_part = '0x' . strtolower($value);
								} else {
									$string_part = strtolower($value);
								}
							} else {
								$input_error = "Invalid ethertype: {$value}";
								break 2;
							}

							// Avoid specifying the same ethertype multiple times
							if (!in_array($string_part, array('0x8100', '0x88a8'))) {
								$string_part = "ether proto {$string_part}";
							} else {
								// Dedicated input fields exist for the VLAN ethertype
								$input_error = "Matching for the given ethertype must be done " .
								               "using the respective Filter Section: {$value}";
								break 2;
							}
							break;
					}
					if (!empty($string_part)) {
						$string_items[] = $string_part;
					}
				}
				break;
			case PCAP_FATTR_PROTOCOL:
				foreach ($string_values as $value) {
					// Validate input values
					switch ($value) {
						case 'icmp':
							$string_part = 'icmp';
							break;
						case 'icmp6';
							$string_part = 'icmp6';
							break;
						case 'tcp';
							$string_part = 'tcp';
							break;
						case 'udp';
							$string_part = 'udp';
							break;
						case 'ipsec';
							$string_part = '(esp or (udp port 4500 and udp[8:4]!=0))';
							break;
						case 'carp';
							$string_part = 'proto 112';
							break;
						case 'pfsync';
							$string_part = 'proto pfsync';
							break;
						case 'ospf';
							$string_part = 'proto ospf';
							break;
						default:
							if (ctype_digit($value) && ($value >= 0 && $value <= 255)) {
								$string_part = "proto {$value}";
							} else {
								$input_error = "Invalid protocol: {$value}";
								break 2;
							}
							break;
					}
					if (!empty($string_part)) {
						$string_items[] = $string_part;
					}
				}
				break;
			case PCAP_FATTR_IPADDRESS:
				foreach ($string_values as $value) {
					if (is_ipaddr($value)) {
						// Validate IPv4/IPv6 address input
						$string_part = "host {$value}";
					} elseif (is_subnet($value)) {
						// Validate IPv4/IPv6 subnet input
						$subnet_parts = explode('/', $value);
						$subnet_cidr = intval($subnet_parts[array_key_last($subnet_parts)], 10);
						if ($subnet_cidr >= 0 && $subnet_cidr <= 128) {
							$subnet_address = gen_subnet($subnet_parts[array_key_first($subnet_parts)],
														$subnet_cidr);
							$string_part = "net {$subnet_address}/{$subnet_cidr}";
						} else {
							$input_error = "Invalid subnet: {$value}";
							break;
						}
					} else {
						$input_error = "Invalid IP address or subnet: {$value}";
						break;
					}
					if (!empty($string_part)) {
						$string_items[] = $string_part;
					}
				}
				break;
			case PCAP_FATTR_MACADDRESS:
				foreach ($string_values as $value) {
					$mac_parts = [];
					// Pad MAC parts with 0 if needed to construct a valid partial match string
					foreach (explode(':', $value) as $macpart) {
						$mac_parts[] = str_pad($macpart, 2, '0', STR_PAD_LEFT);
					}

					$mac_segment_count = count($mac_parts);
					if (in_array($mac_segment_count, array(1, 2, 4))) {
						// Validate partial MAC address - tcpdump will only accept 1, 2, or 4 byte segments
						$mac_string = implode($mac_parts);
						if (is_macaddr($value, true)) {
							$string_part = "ether[0:{$mac_segment_count}]==0x{$mac_string}{$infix}" .
							               "ether[6:{$mac_segment_count}]==0x{$mac_string}";
						} else {
							$input_error = "Invalid partial MAC address: {$value}";
							break;
						}
					} elseif ($mac_segment_count == 6) {
						// Validate full MAC address
						$mac_string = implode(':', $mac_parts);
						if (is_macaddr($value, false)) {
							$string_part = "ether host {$mac_string}";
						} else {
							$input_error = "Invalid MAC address: {$value}";
							break;
						}
					} else {
						$input_error = "Invalid MAC address length - can only match the full " .
						               "address, or with 1, 2, or 4 segments: {$value}";
						break;
					}
					if (!empty($string_part)) {
						$string_items[] = $string_part;
					}
				}
				break;
			case PCAP_FATTR_PORT:
				foreach ($string_values as $value) {
					// Validate input values
					if (is_port($value)) {
						$string_part = "port {$value}";
					} else {
						$input_error = "Invalid port {$value}";
						break;
					}
					if (!empty($string_part)) {
						$string_items[] = $string_part;
					}
				}
				break;
			default:
				$input_error = 'Invalid Attribute.';
				break;
		}

		if (empty($string_items) || !empty($input_error)) {
			throw new Exception("{$input_error}");
		} else {
			// Construct the filter string for this FilterAttribute
			foreach ($string_items as $key => $string_part) {
				if ($key == array_key_first($string_items)) {
					$filter_string = "{$prefix}{$string_part}";
				} else {
					$filter_string .= "{$infix}{$string_part}";
				}
			}

			$this->filter_string = $filter_string;
		}
	}

}

/**
 * Constructs the full expression string used in the tcpdump command.
 * 
 * @param array $filterattributes A list of FilterAttribute objects
 * 
 * @throws Exception If the resulting expression string would be invalid.
 * @return string Expression string - an empty string means capture all.
 */
function get_expression_string(array $filterattributes) {
	$expression_string = '';
	$expression_preset = PCAP_FTYPE_CUSTOM;
	/* The index of the following arrays corresponds to the offset of the
	 * Filter Section - 0 is assumed to be untagged. */
	$fs_exclude = [];
	$fs_strings = [];

	/* Loop through all FilterAttribute objects and construct each Filter
	 * Section string. */
	foreach ($filterattributes as $fa) {
		// Skip all FilterAttribute objects when using a preset Filter Type
		if ($fa->type == PCAP_FATTR_PRESET) {
			$expression_preset = $fa->getMatch();
			break;
		}

		/* Make sure the respective Filter Section is included when all related
		 * FilterAttribute input is empty. */
		if (!array_key_exists($fa->getFilterSection(), $fs_strings)) {
			$fs_strings[$fa->getFilterSection()] = '';
		}

		// If $fs_exclude does not yet have this offset, add it
		if (!array_key_exists($fa->getFilterSection(), $fs_exclude)) {
			$fs_exclude[$fa->getFilterSection()] = false;
		}

		/* Skip this FilterAttribute if the respective Filter Section has
		 * already been excluded. */
		if ($fs_exclude[$fa->getFilterSection()]) {
			continue;
		} elseif ($fa->type == PCAP_FATTR_FSECTION_MATCH) {
			// Skip this FilterAttribute if it's a Filter Section
			if ($fa->getMatch() == PCAP_MATCH_NONE) {
				// Flag the respective Filter Section to be excluded
				$fs_exclude[$fa->getFilterSection()] = true;
			}
			continue;
		}

		// Skip this FilterAttribute if it does not have input to parse
		if (empty($fa->getFilterString()) && $fa->getFilterString() != '0') {
			continue;
		}

		// Don't add a logical operator at the beginning of the filter string
		if (empty($fs_strings[$fa->getFilterSection()])) {
			$fa_match = '';
		} else {
			$fa_match = ($fa->getIsRequired()) ? ' and ' : ' or ';
		}
		$fs_strings[$fa->getFilterSection()] .= sprintf('%1$s(%2$s)', $fa_match, $fa->getFilterString());
	}

	switch ($expression_preset) {
		case PCAP_FTYPE_ANY:
			$expression_string = '';
			break;
		case PCAP_FTYPE_UNTAGGED:
			$expression_string = 'not vlan';
			break;
		case PCAP_FTYPE_TAGGED:
			$expression_string = 'vlan';
			break;
		default:
			/* Save the offsets of the last Filter Sections to be excluded,
			 * included, or have some value to be filtered for; used to avoid
			 * incorrect or redundant Filter Section strings. */
			$last_excluded = null;
			$last_included = null;
			$last_filtered = null;
			foreach (array_reverse($fs_exclude, true) as $key => $is_excluded) {
				if ($is_excluded) {
					if (!isset($last_excluded)) {
						$last_excluded = $key;
					}
				} else {
					if (!isset($last_included)) {
						$last_included = $key;
					}
					if (!isset($last_filtered) && array_key_exists($key, $fs_strings) &&
					    !empty($fs_strings[$key])) {
						$last_filtered = $key;
					}
				}
				// Avoid further checks once all offsets have been saved
				if (isset($last_excluded, $last_included, $last_filtered)) {
					break;
				}
			}

			/* Loop through each Filter Section string, adjust it accordingly,
			 * and construct the final expression string. */
			$fs_last = false;
			$fs_match_next = null;
			foreach ($fs_strings as $fs => $string) {
				/* Assume the Filter Section is to be excluded if that has not
				 * yet been specified. */
				if (!array_key_exists($fs, $fs_exclude)) {
					$fs_exclude[$fs] = true;
				}

				/* Override this Filter Section's logical operator depending on
				 * the previous Filter Section's filter string. Also reset the
				 * variables for this loop. */
				$fs_match_current = $fs_match_next;
				$fs_match_next = null;
				$fs_string = null;
				if (isset($last_excluded) && $fs_exclude[$fs]) {
					// This Filter Section has been excluded
					if (isset($last_included) && $fs < $last_included) {
						if ($fs == 0) {
							/* Exclude this untagged Filter Section using other included
							 * tagged Filter Sections. */
							$fs_string = '';
						} else {
							/* Exclude this tagged Filter Section by specifying 'vlan and '
							 * before the next included tagged Filter Section. */
							$fs_string = 'vlan';
							$fs_match_next = ' and ';
						}
					} else {
						$fs_last = true;
						if ($fs == 0) {
							if ($fs == $last_excluded) {
								// Only this untagged Filter Section exists, and it's excluded.
								$fs_string = 'vlan';
							} else {
								// Cannot exclude both untagged and all tagged sections
								throw new Exception('Resulting expression would exclude all packets.');
							}
						} else {
							// No additional included tagged Filter Sections exist.
							$fs_string = 'not vlan';
						}
					}
				} else {
					// This Filter Section has been included
					if (empty($string)) {
						/* An empty Filter Section string indicates everything for this
						 * Filter Section should be included. */
						if ($fs == 0) {
							if ($fs == $last_included) {
								// This is the only included Filter Section.
								$fs_string = 'not vlan';
								$fs_last = true;
							} else {
								// Equivalent to 'not vlan' without offsetting the search
								$fs_string = 'not ether proto 0x8100 and not ether proto 0x88a8';
								$fs_match_next = ' or ';
							}
						} else {
							$fs_string = 'vlan';
							/* Specifying 'vlan' at the end of the expression string also includes
							 * any additional tagged Filter Sections. */
							if ($fs > $last_filtered && $fs > $last_excluded) {
								$fs_last = true;
							}
						}
					} else {
						// Offset the search for this Filter Section string accordingly.
						$fs_string = ($fs > 0) ? 'vlan and ' . $string : $string;
					}
				}

				/* Determine the logical operator to use between the previous
				 * Filter Section string and the current one. */
				if (empty($expression_string)) {
					// Don't add a logical operator at the beginning of the expression string
					$fs_match = '';
				} elseif (isset($fs_match_current)) {
					// Use the override match instead
					$fs_match = $fs_match_current;
					$fs_match_current = null;
				} elseif ($fs_exclude[$fs]) {
					// Excluded Filter Sections are always matched with 'and'
					$fs_match = ' and ';
				} else {
					$fs_match = ' or ';
				}

				// Add the Filter Section string to the expression string
				if (!empty($fs_string)) {
					$expression_string .= sprintf('%1$s(%2$s)', $fs_match, $fs_string);
				}

				// Break out of the loop if the expression string would be done
				if ($fs_last) {
					break;
				}
			}

			// Simplify the expression string
			if ($expression_string == '(not ether proto 0x8100 and not ether proto 0x88a8) or (vlan)') {
				$expression_string = '';
			}
			break;
	}

	return $expression_string;
}

/**
 * Constructs an sorted list of all (including unassigned) interfaces. The list
 * index/key is the port name, and its respective value is the interface
 * description and port name combined. The interface order consists of assigned,
 * VPN, loopback, and lastly unassigned.
 * 
 * @return array Ordered list of interfaces, e.g. "[igb0] => WAN (igb0)"
 */
function get_interfaces_sorted() {
	// Get all interfaces and their descriptions
	$i_ports = get_interface_arr();
	$i_names = convert_real_interface_to_friendly_interface_name_fast();
	$i_descriptions = get_configured_interface_with_descr(true);

	/* Group interfaces and their descriptions in a consistent order */
	foreach ($i_names as $i => $n) {
		if (array_search($i, $i_ports, true) === false) {
			// Ignore interfaces that are not found on the system
			continue;
		}
		$i_list_assigned[$i] = $n;
	}
	$i_list_assigned_append = $i_list_assigned;
	$i_list_unassigned = [];
	$i_list_unassigned_append = [];
	foreach ($i_ports as $i) {
		if (in_array($i, array('pfsync0', 'pflog0'))) {
			// Ignore special interfaces
			continue;
		}

		$append = false;
		$assigned = false;
		$description = "unassigned ({$i})";

		if (preg_match('/(enc0|lo0|ovpn[sc]\d+|tun_wg\d+|ipsec\d+)/i', $i)) {
			$append = true;
			if ($i == 'enc0') {
				$assigned = true;
				$description = 'IPsec (enc0)';
			} elseif ($i == 'lo0') {
				$assigned = true;
				$description = 'Localhost (lo0)';
			}
		}

		// Set the interface description
		if (!$assigned && array_key_exists($i, $i_names)) {
			if (array_key_exists($i_names[$i], $i_descriptions)) {
				$assigned = true;
				$description = sprintf('%1$s (%2$s)', $i_descriptions[$i_names[$i]], $i);
			}
		}

		// Save the interface to the respective group
		if ($append) {
			if ($assigned) {
				$i_list_assigned_append[$i] = $description;
				if (array_key_exists($i, $i_list_assigned)) {
					unset($i_list_assigned[$i]);
				}
			} else {
				$i_list_unassigned_append[$i] = $description;
			}
		} else {
			if ($assigned) {
				$i_list_assigned[$i] = $description;
				if (array_key_exists($i, $i_list_assigned_append)) {
					unset($i_list_assigned_append[$i]);
				}
			} else {
				$i_list_unassigned[$i] = $description;
			}
		}
	}

	/* return ordered interface list */
	return array_merge($i_list_assigned, $i_list_assigned_append, $i_list_unassigned, $i_list_unassigned_append);
}

/* Check for any matching processes currently running. Use of shell_exec() is
 * needed for proper output. */
function get_pgrep_output(string $expression) {
	$processes = [];
	$output = shell_exec("/bin/pgrep -fl '{$expression}'");
	if (!empty($output)) {
		foreach (explode(PHP_EOL, trim($output)) as $process) {
			$parr = explode(' ', $process, 2);
			if (preg_match('/^[0-9]+$/', $parr[array_key_first($parr)])) {
				$processes[$parr[array_key_first($parr)]] = $parr[array_key_last($parr)];
			}
		}
	}

	return $processes;
}
