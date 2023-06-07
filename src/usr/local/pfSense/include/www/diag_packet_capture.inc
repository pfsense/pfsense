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
 * The packet capture GUI code uses some arbitrary terms to build the filter
 * expression for tcpdump.
 * 
 * FILTER
 * This example filter expression will capture any untagged ICMP traffic from
 * either host, as well as any untagged ARP traffic:
 *   '((host 10.0.5.50 or host 10.0.5.51) and (proto 1) or (arp)) and (not vlan)'
 * This is the complete filter expression to be used. In this example, 'not vlan'
 * is superfluous.
 * 
 * SECTION
 * In the example above, Sections are:
 *   Section 0: '((host 10.0.5.50 or host 10.0.5.51) and (proto 1) or (arp))'
 *   Section 1: '(not vlan)'
 * The filter Section represents the number of times a packet has been tagged.
 * It is used to indicate that a particular filter string should be offset 0
 * (untagged) or more (tagged once or more, e.g. QinQ) times. This is needed
 * because specifying the 'vlan' Type offsets the search for any filter string
 * after it. The Section offset directly relates to the number of times the
 * 'vlan' Type is used.
 * 
 * TYPE
 * In the filter above, Types are:
 *   'host', 'proto', 'arp', and 'vlan'.
 * A Type represents the search scope of the input, it's essentially a 
 * pcap-filter(7) primitive. The same Type may be specified multiple times,
 * such as with the "host" Type.
 * 
 * ATTRIBUTE
 * In the example above, Attributes are:
 *   'host 10.0.5.50', 'host 10.0.5.51', 'proto 1' (aka icmp),
 *   'arp' (aka 'ether proto 0x0806'), and 'not vlan'.
 * A Type and its input together represent an Attribute.
 * 
 * PRESET
 * A Preset can be either a pre-defined filter expression, or a pre-defined
 * Attribute filter string, for example:
 *   Filter Preset (Only Untagged): 'not vlan'
 *   Attribute Preset (IPsec): '(esp or (udp port 4500 and udp[8:4]!=0))'
 * 
 * MATCH:
 * The Match represents the logical operator used between Sections, Types, and
 * Attributes. The possible Matches are as follows:
 *   Between Sections:   'not' ("exclude all"), 'and' ("include all of"), 'or' ("include any of")
 *   Between Types:      'and' ("all of"), 'or' ("any of")
 *   Between Attributes: 'or' ("any of"), 'and' ("all of"), 'not' ("none of")
*/

/**
 * Packet Capture Section
 * The Section "PCAP_SECTION_FPRESET" does not define a Section itself; rather
 *  it signifies that a Filter Preset is being used.
 */
define('PCAP_SECTION_UNTAGGED', 0);
define('PCAP_SECTION_TAGGED_MIN', 1);
define('PCAP_SECTION_TAGGED_MAX', 9);
define('PCAP_SECTION_FPRESET', 99);

/**
 * Packet Capture Match
 * The Match "PCAP_MATCH_SECT_NONE" is used when excluding an entire Section.
 */
define('PCAP_MATCH_SECT_NONE', 10);
define('PCAP_MATCH_SECT_ALLOF', 11);
define('PCAP_MATCH_SECT_ANYOF', 12);
define('PCAP_MATCH_TYPE_ALLOF', 13);
define('PCAP_MATCH_TYPE_ANYOF', 14);
define('PCAP_MATCH_ATTR_ANYOF', 15);
define('PCAP_MATCH_ATTR_ALLOF', 16);
define('PCAP_MATCH_ATTR_NONEOF', 17);
define('PCAP_LIST_MATCH', [
	PCAP_MATCH_ATTR_ANYOF,
	PCAP_MATCH_ATTR_ALLOF,
	PCAP_MATCH_ATTR_NONEOF,
	PCAP_MATCH_TYPE_ANYOF,
	PCAP_MATCH_TYPE_ALLOF
]);
define('PCAP_LIST_MATCH_SECTION', [
	PCAP_MATCH_SECT_NONE,
	PCAP_MATCH_SECT_ALLOF,
	PCAP_MATCH_SECT_ANYOF
]);

/**
 * Packet Capture Filter Preset
 * The Preset "PCAP_FPRESET_ANY" will capture both untagged and tagged packets.
 * The Preset "PCAP_FPRESET_CUSTOM" allows for a user-specified filter expression
 * based on the user's input.
 */
define('PCAP_FPRESET_ANY', 20);
define('PCAP_FPRESET_UNTAGGED', 21);
define('PCAP_FPRESET_TAGGED', 22);
define('PCAP_FPRESET_CUSTOM', 29);
define('PCAP_LIST_FPRESET', [
	PCAP_FPRESET_ANY,
	PCAP_FPRESET_UNTAGGED,
	PCAP_FPRESET_TAGGED
]);

/**
 * Packet Capture Types
 * The Type "PCAP_TYPE_APRESET" is used when an Attribute Preset is selected.
 * The Type "PCAP_TYPE_SMATCH" defines the logical operator for the respective
 * Section. It is used when excluding an entire Section from the packet capture.
 */
define('PCAP_TYPE_VLAN', 30);
define('PCAP_TYPE_ETHERTYPE', 31);
define('PCAP_TYPE_PROTOCOL', 32);
define('PCAP_TYPE_IPADDRESS', 33);
define('PCAP_TYPE_MACADDRESS', 34);
define('PCAP_TYPE_PORT', 35);
define('PCAP_TYPE_APRESET', 38);
define('PCAP_TYPE_SMATCH', 39);
define('PCAP_LIST_TYPE', [
	PCAP_TYPE_VLAN,
	PCAP_TYPE_ETHERTYPE,
	PCAP_TYPE_PROTOCOL,
	PCAP_TYPE_IPADDRESS,
	PCAP_TYPE_MACADDRESS,
	PCAP_TYPE_PORT
]);
define('PCAP_LIST_TYPE_ALL', array_merge(
    PCAP_LIST_TYPE, array(PCAP_TYPE_APRESET, PCAP_TYPE_SMATCH)));

/**
 * The FilterAttribute class defines an Attribute and contains the associated
 * input value. It is used when generating part of the filter expression.
 */
class FilterAttribute {
	private int $type = 0;
	private int $section = 0;
	private int $operator = 0;
	private string $input_string = '';
	private string $filter_string = '';
	private bool $exclude = false;
	private bool $required = false;

	/**
	 * @param int $section Which Section this Attribute applies to.
	 * @param int $operator The operator to use when multiple input values are given for this Attribute.
	 * @param int $type The Attribute the input applies to.
	 * 
	 * @throws Exception For an invalid Attribute and operator combination.
	 * @return FilterAttribute The defined Attribute object.
	 */
	public function __construct(int $filter_section, int $attribute_operator, int $attribute_type) {
		// Validate and set the Attribute's Type
		if (in_array($attribute_type, PCAP_LIST_TYPE_ALL)) {
			$this->type = $attribute_type;
		} else {
			throw new Exception("Invalid Type given: " .
			                    "{$filter_section}:{$attribute_operator}:{$attribute_type}");
		}

		// Validate and set the Attribute's Section
		if ($filter_section == PCAP_SECTION_UNTAGGED || $filter_section == PCAP_SECTION_FPRESET || (
		    $filter_section >= PCAP_SECTION_TAGGED_MIN && $filter_section <= PCAP_SECTION_TAGGED_MAX)) {
			$this->section = $filter_section;
		} else {
			throw new Exception("Invalid Section given: " .
			                    "{$filter_section}:{$attribute_operator}:{$attribute_type}");
		}

		// Validate and set the Attribute's Match
		if ($attribute_type == PCAP_TYPE_APRESET) {
			// Match is for an Attribute Preset
			if ($filter_section == PCAP_SECTION_FPRESET && !in_array($attribute_operator, PCAP_LIST_FPRESET)) {
				// A valid Attribute Preset must be used
				throw new Exception("Invalid Attribute Preset given: " .
				                    "{$filter_section}:{$attribute_operator}:{$attribute_type}");
			}
		} elseif ($attribute_type == PCAP_TYPE_SMATCH) {
			// Match is for a Section
			if (in_array($attribute_operator, PCAP_LIST_MATCH_SECTION)) {
				switch ($attribute_operator) {
					case PCAP_MATCH_SECT_NONE:
						// Exclude the entire Section
						$this->required = true;
						$this->exclude = true;
						break;
					case PCAP_MATCH_SECT_ALLOF:
						if ($filter_section == PCAP_SECTION_UNTAGGED) {
							// A packet is either tagged or untagged; using 'and' signifies it's both and is invalid
							throw new Exception("Invalid match operator used between an untagged and tagged Section: " .
							                    "{$filter_section}:{$attribute_operator}:{$attribute_type}");
						}
						$this->required = true;
						break;
					case PCAP_MATCH_SECT_ANYOF:
						$this->required = false;
						break;
					default:
						break;
				}
			} else {
				throw new Exception("Invalid match operator given for the Section: " .
				                    "{$filter_section}:{$attribute_operator}:{$attribute_type}");
			}
		} else {
			// Match is for the Type
			switch ($attribute_operator) {
				case PCAP_MATCH_ATTR_NONEOF:
					// Exclude this Type's input from the packet capture
					$this->required = true;
					$this->exclude = true;
					break;
				case PCAP_MATCH_ATTR_ANYOF:
				case PCAP_MATCH_ATTR_ALLOF:
					// The captured packets must match this Type's input
					$this->required = true;
					break;
				case PCAP_MATCH_TYPE_ANYOF:
				case PCAP_MATCH_TYPE_ALLOF:
					// Optionally include this Type's input in the packet capture
					$this->required = false;
					break;
				default:
					throw new Exception("Invalid match operator given for the Type: " .
					                    "{$filter_section}:{$attribute_operator}:{$attribute_type}");
					break;
			}
		}
		$this->operator = $attribute_operator;
	}

	public function getType() {
		return $this->type;
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
		// Only set a filter string for a valid Type
		if (in_array($this->type, PCAP_LIST_TYPE)) {
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

		// Operator between Attributes
		// Determine if the FilterAttribute is being explicitly excluded
		$filter_string = '';
		if ($this->getIsExcluded()) {
			$prefix = 'not ';
			$infix = ' and not ';
		} else {
			switch ($this->operator) {
				case PCAP_MATCH_ATTR_ALLOF:
				case PCAP_MATCH_TYPE_ALLOF:
					$prefix = '';
					$infix = ' and ';
					break;
				default:
					$prefix = '';
					$infix = ' or ';
					break;
			}
		}

		// Parse the FilterAttribute's input and construct a filter string
		$string_items = []; // e.g. ['port 80', 'port 443']
		$string_part = ''; // e.g. 'port 80'
		$string_values = preg_split('/\s+/', $this->getInputString(), -1, PREG_SPLIT_NO_EMPTY); // e.g. ['80', '443']
		$input_error = '';
		switch ($this->type) {
			case PCAP_TYPE_VLAN:
				// Determine search offset of the VLAN tag
				if ($this->getFilterSection() >= PCAP_SECTION_TAGGED_MIN) {
					$vlan_offset = 'ether['.(10+4*$this->section).':2]';
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
			case PCAP_TYPE_ETHERTYPE:
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
							$string_part = strtolower(str_starts_with($value, '0x') ? $value : '0x' . $value);
							if (!is_ethertype($string_part)) {
								$input_error = "Invalid ethertype: {$value}";
								break 2;
							}

							// Avoid specifying the same ethertype multiple times
							if (!in_array($string_part, array('0x8100', '0x88a8'))) {
								$string_part = "ether proto {$string_part}";
							} else {
								// Dedicated input fields exist for the VLAN ethertype
								$input_error = "Matching for the given ethertype must be done " .
								               "using the respective filter Section: {$value}";
								break 2;
							}
							break;
					}
					if (!empty($string_part)) {
						$string_items[] = $string_part;
					}
				}
				break;
			case PCAP_TYPE_PROTOCOL:
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
			case PCAP_TYPE_IPADDRESS:
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
			case PCAP_TYPE_MACADDRESS:
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
			case PCAP_TYPE_PORT:
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
				$input_error = 'Invalid Type.';
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
 * Constructs the full filter expression used in the tcpdump command.
 * 
 * @param array $filterattributes A list of FilterAttribute objects
 * @param bool $vlan_supported Set this to false if VLANs are not supported
 * 
 * @throws Exception If the resulting filter expression would be invalid.
 * @return string Filter expression - an empty string means capture all.
 */
function get_expression_string(array $filterattributes, bool $vlan_supported = true) {
	$expression_string = '';
	$expression_preset = PCAP_FPRESET_CUSTOM;
	/* The index of the following arrays corresponds to the offset of the
	 * Section - 0 is assumed to be untagged. */
	$fs_exclude = [];
	$fs_strings = [];

	// Loop through all FilterAttribute objects and construct each Section's filter string.
	foreach ($filterattributes as $fa) {
		// Skip all FilterAttribute objects when using a Filter Preset
		if ($fa->getFilterSection() == PCAP_SECTION_FPRESET) {
			$expression_preset = $fa->getMatch();
			break;
		}

		/* Make sure the respective Section is included when all related
		 * FilterAttribute input is empty. */
		if (!array_key_exists($fa->getFilterSection(), $fs_strings)) {
			$fs_strings[$fa->getFilterSection()] = '';
		}

		// If $fs_exclude does not yet have this offset, add it
		if (!array_key_exists($fa->getFilterSection(), $fs_exclude)) {
			$fs_exclude[$fa->getFilterSection()] = false;
		}

		/* Skip this FilterAttribute if the respective Section has
		 * already been excluded. */
		if ($fs_exclude[$fa->getFilterSection()]) {
			continue;
		} elseif ($fa->getType() == PCAP_TYPE_SMATCH) {
			// Skip this FilterAttribute if it's a Section
			if ($fa->getMatch() == PCAP_MATCH_SECT_NONE) {
				// Flag the respective Section to be excluded
				$fs_exclude[$fa->getFilterSection()] = true;
			}
			continue;
		}

		// Skip this FilterAttribute if it does not have input to parse
		if (empty($fa->getFilterString()) && $fa->getFilterString() != '0') {
			continue;
		}

		// Operator between Types
		// Don't add a logical operator at the beginning of the filter string
		if (empty($fs_strings[$fa->getFilterSection()])) {
			$fa_match = '';
		} else {
			$fa_match = ($fa->getIsRequired()) ? ' and ' : ' or ';
		}
		$fs_strings[$fa->getFilterSection()] .= sprintf('%1$s(%2$s)', $fa_match, $fa->getFilterString());
	}

	switch ($expression_preset) {
		case PCAP_FPRESET_ANY:
			$expression_string = '';
			break;
		case PCAP_FPRESET_UNTAGGED:
			$expression_string = $vlan_supported ? 'not vlan' : '';
			break;
		case PCAP_FPRESET_TAGGED:
			if (!$vlan_supported) {
				// Cannot filter for tagged packets
				throw new Exception('No VLAN support on loopback or encapsulated IP.');
			}
			$expression_string = 'vlan';
			break;
		default:
			// Fill in and exclude missing sections
			if (!empty($fs_strings)) {
				$last_section = max(array_keys($fs_strings));
				$fs_strings = array_replace(array_fill_keys(range(0, $last_section), ''), $fs_strings);
				$fs_exclude = array_replace(array_fill_keys(range(0, $last_section), true), $fs_exclude);
			}

			/* Save the offsets of the last Sections to be excluded, included,
			 * or have some value to be filtered for; used to avoid incorrect
			 * or redundant Section strings. */
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

			/* Loop through each Section filter string, adjust it accordingly,
			 * and construct the final expression string. */
			$fs_last = false;
			$fs_match_next = null;
			$fs_prepend = 0;
			foreach ($fs_strings as $fs => $string) {
				// Skip tagged sections when omitting vlan filters
				if (!$vlan_supported && $fs != 0) {
					continue;
				}

				/* Override this Section's logical operator depending on the
				 * previous Section's filter string. Also reset the variables
				 * for this loop. */
				$fs_match_current = $fs_match_next;
				$fs_match_next = null;
				$fs_string = null;
				if (isset($last_excluded) && $fs_exclude[$fs]) {
					// This Section has been excluded
					if (isset($last_included) && $fs < $last_included) {
						if ($fs == 0) {
							if (!$vlan_supported) {
								// Cannot filter for tagged packets
								throw new Exception('No VLAN support on loopback or encapsulated IP.');
							}
							/* Exclude this untagged Section using other included
							 * tagged Sections. */
							$fs_string = '';
						} else {
							/* Exclude this tagged Section by specifying 'vlan and '
							 * before the next included tagged Section. */
							$fs_string = 'vlan';
							$fs_match_next = ' and ';
						}
					} else {
						$fs_last = true;
						if ($fs == 0) {
							if ($fs == $last_excluded && $vlan_supported) {
								// Only this untagged Section exists, and it's excluded.
								$fs_string = 'vlan';
							} else {
								// Cannot exclude both untagged and all tagged Sections
								throw new Exception('Resulting expression would exclude all packets.');
							}
						} else {
							// No additional included tagged Sections exist.
							$fs_string = 'not vlan';
						}
					}
				} else {
					// This Section has been included
					if (empty($string)) {
						/* An empty Section filter string indicates everything for this
						 * Section should be included. */
						if ($fs == 0) {
							if ($fs == $last_included) {
								// This is the only included Section
								$fs_string = $vlan_supported ? 'not vlan' : '';
								$fs_last = true;
							} else {
								if (!$vlan_supported) {
									// Cannot filter for tagged packets
									throw new Exception('No VLAN support on loopback or encapsulated IP.');
								}
								// Equivalent to 'not vlan' without offsetting the search
								$fs_string = 'not ether proto 0x8100 and not ether proto 0x88a8';
								$fs_match_next = ' or ';
							}
						} else {
							$fs_string = 'vlan';
							/* Specifying 'vlan' at the end of the filter expression also includes
							 * any additional tagged Sections. */
							if ($fs > $last_filtered && $fs > $last_excluded) {
								$fs_last = true;
							}
						}
					} else {
						// Offset the search for this Section filter string accordingly.
						$fs_string = ($fs > 0) ? 'vlan and ' . $string : $string;
					}
				}

				// Operator between Sections
				/* Determine the logical operator to use between the previous
				 * Section filter string and the current one. */
				if (empty($expression_string)) {
					// Don't add a logical operator at the beginning of the filter string
					$fs_match = '';
				} elseif (isset($fs_match_current)) {
					// Use the override match instead
					$fs_match = $fs_match_current;
					$fs_match_current = null;
				} elseif ($fs_exclude[$fs] && !(!$fs_exclude[0] && $last_included > $fs)) {
					/* Excluded Sections are always matched with "and", unless the untagged
					 * section is included and there are included tagged sections after this. */
					$fs_match = ' and ';
				} else {
					$fs_match = ' or ';
				}

				// Add the Section filter string to the filter expression
				if (!empty($fs_string)) {
					// Logically separate unttaged and tagged sections
					if ($fs == 0) {
						$fs_prepend = 1;
					} elseif ($fs_prepend == 1) {
						// The previous section was untagged
						$fs_prepend = 2;
						$fs_string = '(' . $fs_string;
					}
					if ($fs_prepend == 2 && ($fs == $last_section || $fs_last)) {
						$fs_string = $fs_string . ')';
					}
					$expression_string .= sprintf('%1$s(%2$s)', $fs_match, $fs_string);
				}

				// Break out of the loop if the filter expression would be done
				if ($fs_last) {
					break;
				}
			}

			// Simplify the filter expression
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

		if (preg_match('/^(lo\d+|gif\d+|gre\d+|ppp\d+|pppoe\d+|pptp\d+|l2tp\d+|enc\d+|ipsec\d+|ovpn[sc]\d+|tun_wg\d+)/i', $i)) {
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
