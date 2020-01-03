<?php
/*
 * parser_dhcpv6_leases.inc
 *
 * Copyright (c) 2017-2019 Anders Lind (anders.lind@gmail.com)
 * All rights reserved.
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2011 Seth Mos
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

require_once 'tools_for_debugging_n_testing.inc';
require_once 'parser_ipv6.inc';

/*
 * Moved over from status_dhcpv6_leases.php
 */
function parse_duid($duid_string) {
	$parsed_duid = array();
	for ($i = 0; $i < strlen($duid_string); $i++) {
		$s = substr($duid_string, $i, 1);
		if ($s == '\\') {
			$n = substr($duid_string, $i+1, 1);
			if (($n == '\\') || ($n == '"')) {
				$parsed_duid[] = sprintf("%02x", ord($n));
				$i += 1;
			} else {
				$n = substr($duid_string, $i+1, 3);
				if (preg_match('/[0-3][0-7]{2}/', $n)) {
					$parsed_duid[] = sprintf("%02x", octdec($n));
					$i += 3;
				}
			}
		} else {
			$parsed_duid[] = sprintf("%02x", ord($s));
		}
	}
	$iaid = array_slice($parsed_duid, 0, 4);
	$duid = array_slice($parsed_duid, 4);
	return array($iaid, $duid);
}

/*
 * Moved over from status_dhcpv6_leases.php
 */
function get_ndpdata() {
	exec("/usr/sbin/ndp -an", $rawdata);
	$ndpdata = array();
	foreach ($rawdata as $line) {
		$elements = preg_split('/\s+/ ', $line);
		if ($elements[1] != "(incomplete)") {
			$ndpent = array();
			$ip = trim(str_replace(array('(', ')'), '', $elements[0]));
			$ndpent['mac'] = trim($elements[1]);
			$ndpent['interface'] = trim($elements[2]);
			$ndpdata[$ip] = $ndpent;
		}
	}

	return $ndpdata;
}

const IA_NA = 1;
const IA_PD = 2;
const IA_TA = 3;

/*
 * returns false if it was not possible to parse the leases.
 */
function gui_parse_leases(&$pools, &$leases, &$prefixes, &$mappings, $raw_leases, $ndpdata, $lang_pack) {
	$amount_of_matches = preg_match_all('/'.base_regex.'/i', (string) $raw_leases, $lease_array, PREG_SET_ORDER);

	if ($amount_of_matches == false) {
		return false;
	}

	$amount_of_leases = 0;
	foreach ($lease_array as $val) {
		general_debug('---LEASE '.++$amount_of_leases.' START---');

		$iaid_duid = parse_duid($val['IAID_DUID']);

		general_debug($val, 'IAID_DUID');

		$entry = [ 'iaid' => hexdec(implode("", array_reverse($iaid_duid[0]))),
			   'duid' => implode(":", $iaid_duid[1]) ];

		general_debug($entry, 'iaid');
		general_debug($entry, 'duid');

		if (preg_match('/^ia-na$/i', $val['IA_ASSOC'])) {
			gui_parse_single_lease(IA_NA, $val['NEXT_LEVEL'], $lang_pack, $ndpdata, $mappings, $entry);
			$leases[] = $entry;
		} elseif (preg_match('/^ia-pd$/i', $val['IA_ASSOC'])) {
			gui_parse_single_lease(IA_PD, $val['NEXT_LEVEL'], $lang_pack, $ndpdata, $mappings, $entry);
			$prefixes[] = $entry;
		} elseif (preg_match('/^ia-ta$/i', $val['IA_ASSOC'])) {
			// Not used in the pfSense UI at the moment.
			general_debug('Notice: ia-ta is unhandled for now.');
		}

		general_debug("---LEASE $amount_of_leases END---\n");
	}

	// Handle failover - NOTE Experimental;
	// has not been tested on a live configured system.
	$amount_of_matches = preg_match_all('/'.failover_base_regex.'/i', $raw_leases, $lease_array, PREG_SET_ORDER);
	$amount_of_leases = 0;
	foreach ($lease_array as $val) {
		general_debug('---FAILOVER '.++$amount_of_leases.' START---');
		$entry = ['name' => $val['FPN']];
		general_debug($val, 'FPN');
		gui_parse_single_failover($val['NEXT_LEVEL'], $entry);
		$pools[] = $entry;
		general_debug("---FAILOVER $amount_of_leases END---\n");
	}

	general_debug('Leases', $leases);
	general_debug('Prefixes', $prefixes);
	general_debug('Mappings', $mappings);
	general_debug('Pools', $pools);
}

function gui_parse_single_lease($type, $content, $lang_pack, $ndpdata, &$mappings, &$entry) {
	switch ($type) {
		case IA_NA:
			preg_match_all('/'.na_regex.'/i', $content, $matches, PREG_SET_ORDER);
			break;
		case IA_PD:
			preg_match_all('/'.pd_regex.'/i', $content, $matches, PREG_SET_ORDER);
			break;
		// case IA_TA: // Not implemented in pfSense.
		default:
			general_debug('Error: Unhandled lease type.');
			return -1;
	}

	$amount_of_matches = 0;

	foreach ($matches as $val) {
		general_debug("\n  --Match number " . ++$amount_of_matches . ' start.');
		general_debug("Matched: " . $val[0]);

		check_value_set_entry($val, 'CLTT', $entry, 'start', true, function () use (&$val) {
			general_debug($val, 'WEEKDAY', 'DATE', 'YEAR', 'MONTH', 'DAY', 'TIME');
			return $val['DATE'] . ' ' . $val['TIME'];});

		if (setnlen($val, 'NEXT_LEVEL')) {
			general_debug($val, 'NEXT_LEVEL');

			gui_handle_ia($val['NEXT_LEVEL'], $entry, $lang_pack);
		}

		// For now it does not matter that this goes last.
		if ($type === IA_NA) {
			gui_parse_single_na_lease($val, $lang_pack, $ndpdata, $mappings, $entry);
		} elseif ($type === IA_PD) {
			gui_parse_single_pd_lease($val, $lang_pack, $entry);
		}

		general_debug('  --Match number ' . $amount_of_matches . ' end.');
	}
}

function gui_parse_single_na_lease($val, $lang_pack, $ndpdata, &$mappings, &$entry) {
	if (setnlen($val, 'ADDRESS')) {
	  	general_debug($val, 'ADDRESS');

		$space_result = parse_all_ipv6_to_array($val['ADDRESS']);

		if (count($space_result) == 1) {
	    		if (setnlen($space_result[0], 'MATCH')) {
				general_debug("We have a valid ipv6 address");
				if (check_n_set($entry, 'ip', $space_result[0]['MATCH'], true)) {
					// In general we trust that $lang_pack exists with values so no checking (either
					// it works for a certain language/translation or it does not (for all entries.)
					check_n_set($entry, 'type', $lang_pack['dynamic'], true);

					in_array($entry['ip'], array_keys($ndpdata))
						? check_n_set($entry, 'online',	$lang_pack['online'], true)
						: check_n_set($entry, 'online',	$lang_pack['offline'], true);

					// mappings - using DUID and IAID
					if (setnlen($entry, 'duid') && setnlen($entry, 'iaid')) {
						// We assume $mappings array exist - else nothing works.
						general_debug('Setting the mappings array for DUID ' . $entry['duid'] .
							      ' with IAID ' . $entry['iaid'] . ' as index with value ' .
							      $entry['ip']);
						check_n_set($mappings[$entry['duid']], $entry['iaid'], $entry['ip'], true);
					} else {
						general_debug('WARNING: DUID or IAID is missing in entry. Likely either has zero length content.');
					}
				} // else { } - we use overwrite set to true and entry must have been set.
			} else {
				general_debug('Unknown problem with IPv6 address. See output just above.');
			}
		} else {
			general_debug('WARNING: Count of IPv6 addresses is: '.count($space_result));
		}
	}
}

function gui_parse_single_pd_lease($val, $lang_pack, &$entry) {
	if (setnlen($val, 'SPACE')) {
		general_debug($val, 'SPACE');

		$space_result = parse_all_ipv6_to_array($val['SPACE']);

		if (count($space_result) == 1) {
			if (setnlen($space_result[0], 'MATCH')) {
				general_debug("We have a valid ipv6 address");

				if (setnlen($space_result[0], 'CMR6') &&
				    preg_match('/::$/', $space_result[0]['CMR6'])) {
					general_debug("that is properly terminated with ::");
				} else {
					general_debug(", but not a proper subnet that ends with ::");
				}

				check_value_set_entry($val, 'PREFIX', $entry, 'prefix', true,
					function () use (&$val, &$lang_pack, &$entry, &$space_result) {
					check_n_set($entry, 'type', $lang_pack['dynamic'], true);
					return $space_result[0]['MATCH'].'/'.$val['PREFIX'];});
			} else {
				general_debug('Unknown problem with IPv6 address. See output just above.');
			}
		} else {
			general_debug('WARNING: Count of IPv6 addresses is: '.count($space_result));
		}
	}
}

function gui_handle_ia($content, &$entry, $lang_pack) {
	preg_match_all('/'.ia_regex.'/i', $content, $ia_matches, PREG_SET_ORDER);

	/* The reason why we use foreach and not simply just try lookup content is due to the
	 * fact that we match with alternation different sets of values from different
	 * regex groups.
	 * In theory one set/group could be repeated inside $ia_matches which means we want to
	 * know if that is the case. check_n_set makes sure to check if the value exists already
	 * and returns debug info about that.
	 */
	foreach ($ia_matches as $ia_val) {
		if (setnlen($ia_val, 'BINDINGSTATE')) {
			switch ($ia_val['BINDINGSTATE']) {
				case "active":
					check_n_set($entry, 'act', $lang_pack['active'], true);
					break;
				case "expired":
					check_n_set($entry, 'act', $lang_pack['expired'], true);
					break;
				case "free":
					check_n_set($entry, 'act', $lang_pack['expired'], true);
					check_n_set($entry, 'online', $lang_pack['offline'], true);
					break;
				case "backup":
					check_n_set($entry, 'act', $lang_pack['reserved'], true);
					check_n_set($entry, 'online', $lang_pack['offline'], true);
					break;
				case "released":
					check_n_set($entry, 'act', $lang_pack['released'], true);
					check_n_set($entry, 'online', $lang_pack['offline'], true);
					break;
				default:
					general_debug('Notice: Binding state "' . $ia_val['BINDINGSTATE'] .
						      '" is not handled.');
					break;
			}

			general_debug($ia_val, 'BINDINGSTATE');
		}

		// does not seem to be used by lease gui so we do not set anything; we just debug.
		check_value_set_entry($ia_val, 'PREFLIFE');

		// does not seem to be used by lease gui so we do not set anything; we just debug.
		check_value_set_entry($ia_val, 'MAXLIFE');

		check_value_set_entry($ia_val, 'ENDS', $entry, 'end', true, function () use (&$ia_val) {
			general_debug($ia_val, 'WEEKDAY', 'DATE', 'YEAR', 'MONTH', 'DAY', 'TIME');
			return $ia_val['DATE'] . ' ' . $ia_val['TIME'];});
	}
}

function gui_parse_single_failover($content, &$entry) {
	preg_match_all('/'.failover_level1_regex.'/i', $content, $matches, PREG_SET_ORDER);

	/* The reason why we use foreach and not simply just try lookup content is due to the
	 * fact that we match with alternation different sets of values from different
	 * regex groups.
	 * In theory one set/group could be repeated inside $matches which means we want to
	 * know if that is the case. check_value_set_entry makes sure to check if the value
	 * exists already and returns debug info about that.
	 */
	foreach ($matches as $val) {
		check_value_set_entry($val, 'MYSTATE', $entry, 'mystate', true);
		check_value_set_entry($val, 'MYDATE', $entry, 'mydate', true,
				      function () use (&$val) {return $val['DATE'] . ' ' . $val['TIME'];});
		check_value_set_entry($val, 'PEERSTATE', $entry, 'peerstate', true);
		check_value_set_entry($val, 'PEERDATE', $entry, 'peerdate', true,
				      function () use (&$val) {return $val['PDATE'] . ' ' . $val['PTIME'];});
		/* Does not seem to be used by lease gui so we do not set anything; we just debug.
		 * mclt (Maximum Client Lead Time):
		 * https://kb.isc.org/article/AA-00502/0/A-Basic-Guide-to-Configuring-DHCP-Failover.html
		 * https://kb.isc.org/article/AA-00268/0/DHCP-Failover-and-MCLT-configuration-implications.html
		 */
		check_value_set_entry($val, 'MCLT');
	}
}

/* Main leases - Level 0
 * regex matches the outer/top level of a lease
 * We allow free spacing and comments with (?x)
 * We capture:
 * . the identity association of a temporary address and non-temporary address
 * and delegated prefix (IA-TA, IA-NA and IA-PD)
 * . the partly octal encoded iaid + duid (Identity association identifier + DHCP Unique Identifier)
 * . the content at the next level,
 * but our matching also reuses our
 * current match (RECURSE) to make sure
 * there is a balance to the curly braces {}
 * semicolons where there are no curly braces
 * and sub-content to curly braces - but neither the
 * next level and sub levels are directly
 * individually matched/captured (that is for later
 * regex matches to achieve!)
 *
 *	 \x5C = \
 *	 Octal numbers are the characters that are found in the IAID and DUID with a
 *	 backslash \ in front of them.
 *	 Hex values in ranges are those characters that are printed as characters (not
 *	 octal values):
 *	 [\x20\x21\x23-\x5B\x5D-\x7E]
 *	 1. See ascii table that includes hex values:
 *	    https://en.wikipedia.org/wiki/ASCII#Printable_characters
 *	    https://en.wikipedia.org/wiki/ASCII#Character_set
 *	 2. Check: https://source.isc.org/cgi-bin/gitweb.cgi?p=dhcp.git;a=blob;f=common/print.c
 *	 3. isprint is more limited than isascii: http://www.cplusplus.com/reference/cctype/isprint/
 */

/* Notice (?x) must be a the beginning of the line - cannot be tabbed in,
 * because free spacing mode must start at the very beginning (^) of the
 * content.
 */
const base_regex = <<<'BASE'
(?x)
	(?'IA_ASSOC'ia-(?>ta|na|pd))
	\s+"
	(?'IAID_DUID'
		(?>
				[\x20\x21\x23-\x5B\x5D-\x7E]
			|
				\x5C
				(?>
						"
					|
						\x5C
					|
						(?>00[0-7]|0[1-3][0-7]|177|[2-3][0-7]{2})
				)
		)*
	)
	"\s+
	\{
		(?'NEXT_LEVEL'
			(?>
				\s*
				(?>
						[^\{\}\n]+;
					|
						[^\{\}\n]+
						\s*
						\{
							(?&NEXT_LEVEL)
						\}
						\s*
				)*
			)*
		)
	\}
BASE;
/* Inside the: (?'NEXT_LEVEL' ... )
 * various white space characters where substituted with \s* to match all white
 * space, but at different places before the substitutions some of the white
 * space characters were as mentioned below and their purpose:
 * *  (star) needed for when no space or newline e.g. nested {} is used in e.g.:
 *    something {dww w{} w {d;}}
 * \n (new line) needed for quicker matching (here)
 * \  (space) needed so we eat all space before a faulty double {}:
 *    something {} {}
 *    , is repeated - which we consider illegal.
 * \ \n has been substituted with \s to cover more white space characters!
 */

/* https://source.isc.org/cgi-bin/gitweb.cgi?p=dhcp.git;a=blob;f=includes/dhcpd.h
 * struct lease etc. and various write_lease methods
 *
 * https://source.isc.org/cgi-bin/gitweb.cgi?p=dhcp.git;a=blob;f=server/db.c
 * method for write lease file content is found here e.g. write_lease()
 *
 * Notice lease time entries are written with C gmtime() as UTC:
 * https://source.isc.org/cgi-bin/gitweb.cgi?p=dhcp.git;a=blob;f=common/print.c
 * const char * print_time(TIME t) last else clause:
 * if (strftime(buf, sizeof(buf), "%w %Y/%m/%d %H:%M:%S;",
 *                              gmtime(&t)) == 0)
 */
const date_time = <<<'DATETIME'
	(?'WEEKDAY'[0-6])\s+
	(?'DATE'
		(?'YEAR'\d+)\/(?'MONTH'(?>0[1-9]|1[0-2]))\/
		(?'DAY'(?>0[1-9]|[1-2][0-9]|30|31))
	)\s+
	(?'TIME'(?>[0-1][0-9]|2[0-3]):(?>[0-5][0-9]):(?>[0-5][0-9]|60))
DATETIME;

// Level 1
const common_napd_top =
'(?x)
cltt\s+(?\'CLTT\''.date_time.');|';

# Perform real check of the IPv6 address/space somewhere else.
const pd_specific = <<<'PD'
iaprefix\s+(?'SPACE'[\.\da-fA-F:]*)\/(?'PREFIX'\d{1,3})\s+\{
PD;
const na_specific = <<<'NA'
iaaddr\s+(?'ADDRESS'[\.\da-fA-F:]*)\s+\{
NA;

/* Level 1
 * This part handles matching properly placed curly braces and semicolons.
 * If in doubt just do experiments! To achieve matching the curly braces we need
 * to check at all levels however we do just capture it all since matching the
 * individual components needs to be handled separately at each level by other
 * regexes.
 * Same idea as with base_regex above, but not expanded to save space.
 */
const common_napd_bottom = <<<'COMMON'
(?'NEXT_LEVEL'(?>\s*
(?>[^\{\}\n]+;|
[^\{\}\n]+\s*\{
(?&NEXT_LEVEL)
\}\s*
)*)*)
\}
COMMON;

/* Level 2 stuff
 * binding state names defined in array binding_state_names
 * https://source.isc.org/cgi-bin/gitweb.cgi?p=dhcp.git;a=blob;f=server/stables.c
 * "free", "active", "expired", "released", "abandoned", "reset", "backup"
 * write_lease and write_ia use binding_state_names in:
 * https://source.isc.org/cgi-bin/gitweb.cgi?p=dhcp.git;a=blob;f=server/db.c
 * defined in https://source.isc.org/cgi-bin/gitweb.cgi?p=dhcp.git;a=blob;f=server/stables.c
 */
const ia_regex =
'(?x)
(binding\s+state\s+(?\'BINDINGSTATE\'free|active|expired|released|abandoned|reset|backup)|
preferred-life\s+(?\'PREFLIFE\'\d+)|
max-life\s+(?\'MAXLIFE\'\d+)|
ends\s+(?\'ENDS\''.date_time.'));\s*';

const pd_regex = common_napd_top."\n".pd_specific."\n".common_napd_bottom;
const na_regex = common_napd_top."\n".na_specific."\n".common_napd_bottom;

/* Print these out if you would like to test and debug with (online) tools such
   as https://regex101.com - you could also test and debug some parts mentioned
   above. Just use the raw lease file as input and see how they match. */

// NOTE THIS IS NOT TESTED ON A REAL FAILOVER CONFIGURED SYSTEM
/*
 * Handling of failover base - level 0
 * Documentation is misleading so the source of ISC DHCP server is the best
 * resource combined with this article:
 * https://deepthought.isc.org/article/AA-00252/31/Putting-the-working-server-in-a-failover-pair-into-partner-down-state.html
 * , that seems to be right! Whereas the documentation tells something different
 * in: https://www.isc.org/wp-content/uploads/2017/08/dhcp43leases.html
 * , it writes: peer state state at date;
 * For more about how we implement see under level 1 below.
 *
 * FPN = failover pair name
 * Relevant source places:
 * https://source.isc.org/cgi-bin/gitweb.cgi?p=dhcp.git;a=blob;f=server/db.c
 * method: write_failover_state
 *
 * Same idea as with base_regex above, but not expanded to save space.
 */
const failover_base_regex = <<<'FAILOVERBASE'
(?x)
	failover\s+peer\s+\"(?'FPN'.*)\"\s+state\s+\{
	(?'NEXT_LEVEL'(?>\s*
	(?>[^\{\}\n]+;|
	[^\{\}\n]+\s*\{
	(?&NEXT_LEVEL)
	\}\s*
	)*)*)
	\}
FAILOVERBASE;

/*
 * Handling of failover statements - level 1
 *
 * Definition of state names:
 * https://source.isc.org/cgi-bin/gitweb.cgi?p=dhcp.git;a=blob;f=server/failover.c;h=25e1b72b5ed1705981fa7867d7c6172daa27f5a0;hb=HEAD
 * const char *dhcp_failover_state_name_print (enum failover_state state)
 * https://source.isc.org/cgi-bin/gitweb.cgi?p=dhcp.git;a=blob;f=includes/failover.h
 * enum failover_state, that holds the enumerated state names and the typedef
 * of dhcp_failover_state_t with references to dhcp_failover_config_t me,
 * partner and the enumerated failover_state for saved_state that is used by
 * int write_failover_state (dhcp_failover_state_t *state) in:
 * https://source.isc.org/cgi-bin/gitweb.cgi?p=dhcp.git;a=blob;f=server/db.c
 * to write the actual failover info in our lease file!
 *
 */
const p_date_time = <<< 'PDATETIME'
	(?'PWEEKDAY'[0-6])\s+
	(?'PDATE'
		(?'PYEAR'\d+)\/(?'PMONTH'(?>0[1-9]|1[0-2]))\/
		(?'PDAY'(?>0[1-9]|[1-2][0-9]|30|31))
	)\s+
	(?'PTIME'(?>[0-1][0-9]|2[0-3]):(?>[0-5][0-9]):(?>[0-5][0-9]|60))
PDATETIME;

/* Layout of these matters. 'recover-...' must come before 'recover', because
 * we make use of atomic grouping in failover_level1 to save amount of steps to
 * match!
 */
const failover_states = 'unknown-state|partner-down|normal|conflict-done'.
			'|communications-interrupted|resolution-interrupted|potential-conflict'.
			'|recover-done|recover-wait|recover|shutdown|paused|startup';

const failover_level1_regex =
	'(?x)
	(
			my\s+state\s+(?\'MYSTATE\'(?>'.failover_states.'))\s+
			at\s+(?\'MYDATE\''.date_time.')
		|
			partner\s+state\s+(?\'PEERSTATE\'(?&MYSTATE))\s+
			at\s+(?\'PEERDATE\''.p_date_time.')
		|
			mclt\s+(?\'MCLT\'\d+)
	);\s*';
