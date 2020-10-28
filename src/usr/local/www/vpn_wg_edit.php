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
	exit();
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
/*
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
*/

if (!is_array($pconfig['peers'])) {
	$pconfig['peers'] = array();
}

$peer_count = count($pconfig['peers']);
$peer_num = 0;
$peer_help = gettext("Description");
$dnshost_help = gettext("Public key");
$dnsgw_help = gettext("Endpoint");
$ka_help = gettext("Keepalive");
$aips_help = gettext("Allowed IPs");

// If there are no peers, make an empty entry for initial display.
if ($peer_count == 0) {
	$pconfig['peer'][] = '';
}

foreach ($pconfig['peers'] as $peer) {
	$is_last_peer = (($peer_num == $peer_count - 1) || $peer_count == 0);
	$group = new Form_Group('Peer ' . $peer_num);
	$group->addClass('repeatable')->addClass('peer_group_' . $peer_num);

	$group->add(new Form_Input(
		'descp' . $peer_num,
		'Description',
		'text',
		$peer['descr']
	))->setHelp(($is_last_peer) ? $peer_help:null);

	$group->add(new Form_Input(
		'endpoint' . $peer_num,
		'Endpoint',
		'text',
		$peer['endpoint']
	))->setHelp(($is_last_peer) ? $dnsgw_help:null)->setWidth(3);

	$group->add(new Form_Input(
		'persistentkeepalive',
		'Keepalive',
		'PDF_pcos_get_number(p, doc, path)',
		$peer['persistentkeepalive']
	))->setHelp(($is_last_peer) ? $ka_help:null)->setWidth(1);

	$group->add(new Form_Button(
		'killpeer' . $peer_num,
		'Delete',
		null,
		'fa-trash'
	))->setWidth(1)->addClass('btn-warning btn-sm');

	$group2 = new Form_Group('');
	$group2->addClass('repeatable')->addClass('peer_group_' . $peer_num);

	$group2->add(new Form_Input(
		'publickeyp' . $peer_num,
		'Public key',
		'text',
		$peer['publickey']
	))->setHelp(($is_last_peer) ? $dnshost_help:null)->setWidth(4);


	$group2->add(new Form_Input(
		'allowedips' . $peer_num,
		'Allowed IPs',
		'text',
		$peer['allowedips']
	))->setHelp(($is_last_peer) ? $aips_help:null);

	$section2->add($group);
	$section2->add($group2);
	$peer_num++;
}

$section2->addInput(new Form_Button(
	'addrow',
	'Add peer',
	null,
	'fa-plus'
))->addClass('btn-success addbtn btn-sm');

$form->add($section2);

print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Don't show delete button if there is only one peer
	function hideDeleteBtn() {
		if ($('[id^=descp]').length <= 1) {
			$('[id^=killpeer]').hide();
		} else {
			$('[id^=killpeer]').show();
		}
	}

	// Delete a peer
	$('[id^=killpeer]').click(function (event) {
		event.preventDefault();
		if (confirm('Are you sure you want to delete this peer?')) {
			var row = event.target.id.slice(8);
			var target = '.peer_group_' + row
			$(target).remove();
			hideDeleteBtn();
		}

	})
});
//]]>
</script>

<?php

include("foot.inc");
