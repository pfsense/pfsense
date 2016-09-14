/*
 * ipsec.js
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

function updateIpsec() {
	selectIntLink = "ipsecDetailed";
	ipsecsettings = "ipsecDetail=";
	ipsecsettings += d.getElementById(selectIntLink).checked;

	selectIntLink = "ipsec-config";
	textlink = d.getElementById(selectIntLink);
	textlink.value = ipsecsettings;
}
