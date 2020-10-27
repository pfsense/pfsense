<?php

/*
 * vpn_ipsec_phase1.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
##|*IDENT=page-vpn-iwg-editphase1
##|*NAME=VPN: Wireguard: Edit
##|*DESCR=Edit wireguard tunnele.
##|*MATCH=vpn_wg_edit.php*
##|-PRIV

require_once("functions.inc");
require_once("guiconfig.inc");
require_once("wg.inc");

init_config_arr(array('wireguard', 'tunnel'));
$tunnels = &$config['wireguard']['tunnel'];

if (is_numericint($_REQUEST['index'])) {
	$index = $_REQUEST['index'];
}

if (isset($index) && $tunnels[$index]) {
	$pconfig = &$tunnels[$index];
} else {
	/* defaults */
}


if ($_POST['save']) {
	// $input_errors = wg_do_post($POST);
}

$shortcut_section = "wireguard";

$pgtitle = array(gettext("VPN"), gettext("Wireguard"), gettext("Tunnel"));
$pglinks = array("", "vpn_wg.php", "vpn_wg.php", "@self");

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Interface wg' . $index);

$section->addInput(new Form_Checkbox(
	'enabled',
	'Enabled',
	'',
	$pconfig['enabled'] == 'yes'
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');


$section->addInput(new Form_Input(
	'address',
	'Address',
	'text',
	$pconfig['interface']['address']
))->setHelp('Addresses.');


$section->addInput(new Form_Input(
	'listenport',
	'Listen port',
	'text',
	$pconfig['interface']['listenport']
))->setHelp('Port to listen on.');


$section->addInput(new Form_Input(
	'privatekey',
	'Private key',
	'text',
	$pconfig['interface']['privatekey']
))->setHelp('Private, sorry.');

$form->add($section);


$section2 = new Form_Section('Peers');

$idx = 0;
foreach ($pconfig['peers'] as $peer) {
	$section2->addInput(new Form_Input(
		'descr_p' . $idx,
		'Description',
		'text',
		$peer['descr']
	))->setHelp('Optional.');

	$idx++;
}

$form->add($section2);

print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

});
//]]>
</script>

<?php

include("foot.inc");
