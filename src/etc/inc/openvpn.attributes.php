<?php
/*
 * openvpn.attributes.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2013 BSD Perimeter
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

global $attributes, $username, $dev, $untrusted_port;

if (empty($dev)) {
	$dev = "openvpn";
}

$rules = parse_cisco_acl($attributes, $dev);
$filename = "{$g['tmp_path']}/ovpn_{$dev}_{$username}_{$untrusted_port}.rules";
if (!empty($rules)) {
	@file_put_contents($filename, $rules);
} else {
	unlink_if_exists($filename);
}

?>
