#!/usr/local/bin/php-cgi -q
<?php
/*
 * ipsec_keepalive.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021-2023 Rubicon Communications, LLC (Netgate)
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

include_once('ipsec.inc');
include_once('service-utils.inc');
include_once('gwlb.inc');
init_config_arr(array('ipsec', 'phase1'));
init_config_arr(array('ipsec', 'phase2'));
global $config;

$debug = false;

/* Check if there are any tunnels defined, bail if not */
/* Check if IPsec is enabled and running, bail if disabled or stopped */
if (empty($config['ipsec']['phase2']) ||
    !ipsec_enabled() ||
    !get_service_status(array('name' => 'ipsec'))) {
	if ($debug) {
		echo "IPsec is not configured and running.\n";
	}
	return false;
}

/* List of P2 conids we want to initiate */
$initiate = array();

/* Check all P2s for keepalive setting */
foreach ($config['ipsec']['phase2'] as $p2) {
	/* If enabled, form conid and store in list */
	if ($p2['keepalive'] == 'enabled') {
		$initiate[] = ipsec_conid(null, $p2);
	}
}

/* If the list is empty, nothing to do, so bail */

if (empty($initiate)) {
	if ($debug) {
		echo "No tunnels marked keepalive.\n";
	}
	return false;
}

/* Fetch IPsec status */
$status = ipsec_status();

/* Remove duplicates which may happen if multiple un-split IKEv2 P2s on the same
 * P1 have the Keep Alive option enabled. */
$initiate = array_unique($initiate);

/* get gateway groups array */
$a_groups = return_gateway_groups_array(true);

/* Check each connected entry to see if the one we want is enabled */
foreach ($initiate as $conid) {
	if ($debug) {
		echo "Checking {$conid}.\n";
	}
	if (is_array($status['disconnected']) &&
	    is_array($status['disconnected']['p2']) &&
	    in_array($conid, $status['disconnected']['p2'])) {
		/* Check if P1 is using a CARP VIP. If so, check CARP status. */
		list($ikeid, $reqid) = ipsec_id_by_conid($conid);
		if (((substr($status[$ikeid]['p1']['interface'], 0, 4) == "_vip") &&
		    in_array(get_carp_bind_status($status[$ikeid]['p1']['interface']), array('BACKUP', 'INIT'))) ||
		    (is_array($a_groups[$status[$ikeid]['p1']['interface']]) &&
		    !empty($a_groups[$status[$ikeid]['p1']['interface']][0]['vip']) &&
		    in_array(get_carp_bind_status($a_groups[$status[$ikeid]['p1']['interface']][0]['vip']), array('BACKUP', 'INIT')))) {
			if ($debug) {
				echo "{$conid} is disconnected but using a CARP VIP in BACKUP or INIT status. Skipping.\n";
			}
			continue;
		}
		if ($debug) {
			echo "{$conid} is disconnected. Attempting to initiate.\n";
		}
		ipsec_initiate_by_conid('child', $conid);
	} elseif ($debug) {
		echo "{$conid} is already connected.\n";
	}
}
