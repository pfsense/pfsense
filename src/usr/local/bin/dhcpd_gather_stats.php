#!/usr/local/bin/php-cgi -q
<?php
/*
 * dhcpd_gather_stats.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2013-2021 Rubicon Communications, LLC (Netgate)
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

require_once("config.inc");
require_once("interfaces.inc");
require_once("system.inc");
require_once("util.inc");

/* echo the rrd required syntax */
echo "N:";
$result = array();
$result['active'] = "NaN";
$result['static'] = "NaN";
$result['range'] = "NaN";

if (is_array($config['dhcpd'][$argv[1]])) {

	$dhcpif = $argv[1] ;
	$leases = system_get_dhcpleases(false);

	$ifcfgip = get_interface_ip($dhcpif);
	$ifcfgsn = get_interface_subnet($dhcpif);
	$subnet_start = gen_subnetv4($ifcfgip, $ifcfgsn);
	$subnet_end = gen_subnetv4_max($ifcfgip, $ifcfgsn);

	$result['range'] = ip_range_size_v4(
	    $config['dhcpd'][$dhcpif]['range']['from'],
	    $config['dhcpd'][$dhcpif]['range']['to']);

	/*
	 * Translate these once so we don't do it over and over in the loops
	 * below.
	 */
	$active_string = gettext("active");
	$static_string = gettext("static");

	foreach ($leases['lease'] as $data) {
		if ($data['act'] != $active_string &&
		    $data['act'] != $static_string &&
		    $_GET['all'] != 1) {
			continue;
		}
		if ($data['act'] != $static_string) {
			if (is_inrange_v4($data['ip'],
			    $config['dhcpd'][$dhcpif]['range']['from'],
			    $config['dhcpd'][$dhcpif]['range']['to'])) {
				$result['active'] = intval($result['active']) +
				    1;
			}
		} else if (is_inrange_v4($data['ip'], $subnet_start,
		    $subnet_end)) {
			$result['static'] = intval($result['static']) + 1;
		}
	}
}
echo $result['active'].":".$result['static'].":".$result['range'] ;
