<?php
/*
 * parser_dhcpv6_lease_tester.php
 *
 * Copyright (c) 2017 Anders Lind (anders.lind@gmail.com)
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
##|*IDENT=page-parser-dhcpv6-lease-tester
##|*NAME=DHCPv6 lease parser tester
##|*DESCR=Testing DHCPv6 leases of various kinds and nature also variations not found in a lease file.
##|*MATCH=dhcpv6_lease_tester.php*
##|-PRIV

require_once 'parser_dhcpv6_leases.inc';

$pfSense_lease_file='/var/dhcpd/var/db/dhcpd6.leases';
$pfSense_platform_file='/etc/platform';

$is_pfSense = file_exists ($pfSense_platform_file);
if ($is_pfSense) {
	if ( ! preg_match ('/^pfSense/i',file_get_contents($pfSense_platform_file))) {
		$is_pfSense = false;
	}
}

if (isset ($argv[1])) {
	$leasesfile = $argv[1];
}
else if ($is_pfSense) {
	$leasesfile = $pfSense_lease_file;
}
else {
	echo 'Please pass the filename of the lease file.' . PHP_EOL;
	exit (1);
}

if (file_exists ($leasesfile)) {
	$raw_leases=file_get_contents($leasesfile);
}
else {
	echo "Lease file '$leasesfile' does not exist!" . PHP_EOL;
	exit (2);
}

$lang_pack = [ 'online' =>  'online',
               'offline' => 'offline',
               'active' =>  'active',
               'expired' => 'expired',
               'reserved' => 'reserved',
               'released' => 'released',
               'dynamic' => 'dynamic',
               'static' =>  'static'];

$pools = array();
$leases = array();
$prefixes = array();
$mappings = array();
if ($is_pfSense) {
	$ndpdata = get_ndpdata ();
} else {
	$ndpdata = array(); // Empty for now - no test data!
}

gui_parse_leases ($pools, $leases, $prefixes, $mappings, $raw_leases, $ndpdata, $lang_pack);
