<?php
/*
 * switch_vlans.php
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

##|+PRIV
##|*IDENT=page-switch-vlans-edit
##|*NAME=Switch: VLANs Edit
##|*DESCR=Allow access to the 'Switch: VLANs Edit' page.
##|*MATCH=switch_vlans_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("switch.inc");

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("VLANs"), gettext("Edit"));
$shortcut_section = "vlans";
include("head.inc");

print("<h3>Under construction</h3>");
print("<br />");

if ($_GET['act'] == "edit" ) {
	$vid = $_GET['vid'];
	$device = $_GET['swdevice'];

	print("Editing VLAN ID: " . $vid . " on device: " . $device);
}

include("foot.inc");
