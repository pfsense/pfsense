<?php
/*
 * bandwidth_by_ip.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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

require_once('auth_check.inc');
// bandwidth_by_ip functionality provided in include file to permit use by other processes
require_once("bandwidth_by_ip.inc");

// Gather the required parameters
$interface = $_REQUEST['if'];
$filter = $_REQUEST['filter'];
$sort = $_REQUEST['sort'];
$hostipformat = $_REQUEST['hostipformat'];
$mode = !empty($_REQUEST['mode']) ? $_REQUEST['mode'] : '';

// Print bandwidth as specified
printBandwidth($interface, $filter, $sort, $hostipformat, $mode);

?>
