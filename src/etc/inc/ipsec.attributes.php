<?php
/*
 * ipsec.attributes.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

global $attributes;

if (empty($common_name)) {
	$common_name = getenv("common_name");
	if (empty($common_name)) {
		$common_name = getenv("username");
	}
}

$rules = parse_cisco_acl($attributes, 'enc0');
if (!empty($rules)) {
	$pid = posix_getpid();
	@file_put_contents("/tmp/ipsec_{$pid}{$common_name}.rules", $rules);
	mwexec("/sbin/pfctl -a " . escapeshellarg("ipsec/{$common_name}") . " -f {$g['tmp_path']}/ipsec_{$pid}" . escapeshellarg($common_name) . ".rules");
	@unlink("{$g['tmp_path']}/ipsec_{$pid}{$common_name}.rules");
}

?>
