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

if ($_REQUEST['ajax']) {
	print(gerneratePSK());
	exit;
}

// All form save logic is in /etc/inc/wg.inc
if ($_POST['save']) {
	$res = wg_do_post($_POST);
	$input_errors = $res['input_errors'];
	$pconfig = $res['pconfig'];

	if (!$input_errors) {
		// Create the new WG config files
		wg_create_config_files();

		// Setup and start the new WG tunnel
		wg_configure_if($pconfig['name']);

		// Go back to the tunnel table
		header("Location: vpn_wg.php");
	}
} else if ($_POST['action'] == 'genkeys') { // Process ajax call requesting new key pair
	print(genKeyPair(true));
	exit;
} else {
	if (isset($index)) {
		if ($tunnels[$index]) {
			$pconfig = &$tunnels[$index];
		}
	}
}

$shortcut_section = "wireguard";

$pgtitle = array(gettext("VPN"), gettext("Wireguard"), gettext("Tunnel"));
$pglinks = array("", "vpn_wg.php", "vpn_wg.php", "@self");

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form(false);

// First row
$section = new Form_Section('Interface wg' . $index);

$section->addInput(new Form_Input(
	'index',
	'',
	'hidden',
	$index
));

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
	'*Address',
	'text',
	$pconfig['interface']['address']
))->setHelp('Comma separated list of addresses assigned to interface.');


$section->addInput(new Form_Input(
	'listenport',
	'*Listen port',
	'text',
	$pconfig['interface']['listenport'],
	['placeholder' => next_wg_port()]
))->setHelp('Port to listen on.');

$group = new Form_Group('*Interface keys');

$group->add(new Form_Input(
	'privatekey',
	'Private key',
	'text',
	$pconfig['interface']['privatekey']
))->setHelp('Private key for this interface.');

$group->add(new Form_Input(
	'publickey',
	'Public key',
	'text',
	$pconfig['interface']['publickey']
))->setHelp('Public key for this interface.');

$group->add(new Form_Button(
	'genkeys',
	'Generate',
	null,
	'fa-key'
))->setWidth(1)->addClass('btn-primary btn-xs')->setHelp('New');

$section->add($group);
$form->add($section);

print($form);

// ============ Peer edit modal ===================================================================================================
$section2 = new Form_Section('Peer');

$section2->addInput(new Form_Input(
	'peer_num',
	'',
	'hidden'
));

$section2->addInput(new Form_Input(
	'pdescr',
	'Description',
	'text'
))->setHelp("Peer description - not parsed");

$section2->addInput(new Form_Input(
	'endpoint',
	'*Endpoint',
	'text'
))->setHelp("Hostname, IPv4 or IP46 address");

$section2->addInput(new Form_Input(
	'port',
	'*Endpoint port',
	'text'
));

$section2->addInput(new Form_Input(
	'persistentkeepalive',
	'Keepalive',
	'text'
))->setHelp("Keep alive value in seconds");

$section2->addInput(new Form_Input(
	'ppublickey',
	'*Public key',
	'text'
));

$section2->addInput(new Form_Input(
	'allowedips',
	'Allowed IPs',
	'text'
))->setHelp("List of IPs allowed to connect");

$group2 = new Form_Group('Pre-shared key');

$group2->add(new Form_Input(
	'presharedkey',
	'Preshared key',
	'text'
))->setHelp("Optional pre-shared key");

$group2->add(new Form_Button(
	'genpsk',
	'Generate key',
	null
))->addClass('btn btn-xs success');

$section2->add($group2);

?>

<!-- Modal -->
<div id="peermodal" class="modal fade" role="dialog" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-lg">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-body">
        <?=$section2?>

        <nav class="action-buttons">
			<button type="submit" id="savemodal" class="btn btn-sm btn-primary" title="<?=gettext('Update peer')?>">
				<?=gettext("Update")?>
			</button>

			<button type="submit" id="closemodal" class="btn btn-sm btn-info" title="<?=gettext('Cancel')?>">
				<?=gettext("Cancel")?>
			</button>
		</nav>
      </div>
    </div>

  </div>
</div>

<div class="panel panel-default">
	<div id="mainarea" class="table-responsive panel-body">
		<table id="ruletable" class="table table-hover table-striped table-condensed" style="overflow-x: 'visible'">
			<thead>
				<tr>
					<th><?=gettext("Peer")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Endpoint")?></th>
					<th><?=gettext("Port")?></th>
					<th><?=gettext("Public key")?></th>
					<th></th>

				</tr>
			</thead>

			<tbody>
<?php
				$peer_num = 0;
				if (!empty($pconfig['peers']['peer'])) {
					foreach ($pconfig['peers']['peer'] as $peer) {
						print('<tr class="peer_group_' . $peer_num . '">');
						print("<td>{$peer_num}</td>\n");
						print("<td>{$peer['descr']}</td>\n");
						print("<td>{$peer['endpoint']}</td>\n");
						print("<td>{$peer['port']}</td>\n");
						print("<td>{$peer['publickey']}</td>\n");
?>
						<td style="cursor: pointer;">
							<a class="fa fa-pencil" href="#" id="editpeer_<?=$peer_num?>"title="<?=gettext("Edit peer"); ?>"></a>
							<a class="fa fa-trash text-danger no-confirm" href="#" id="killpeer_<?=$peer_num?>" title="<?=gettext('Delete peer');?>"></a>
						</td>
<?php
						print("</tr>");
						$peer_num++;
					}
				}
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<button type="submit" id="editpeer_new" class="btn btn-sm btn-success" title="<?=gettext('Add new peer')?>">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add peer")?>
	</button>

	<button type="submit" id="saveform" name="saveform" class="btn btn-sm btn-primary" value="save" title="<?=gettext('Save tunnel')?>">
		<i class="fa fa-save icon-embed-btn"></i>
		<?=gettext("Save")?>
	</button>
</nav>

<?php $jpconfig = json_encode($pconfig); ?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var pconfig = JSON.parse('<?=$jpconfig?>');

	// Eliminate ghost lines in modal
	$('.form-group').css({"border-bottom-width" : "0"});

	// Edit peer
	$('[id^=editpeer_]').click(function () {
		var peernum =  this.id.slice('editpeer_'.length);
		$('#peer_num').val(peernum);

		// peer -1 means creating a new peer
		if (peernum != "new") {
			$('#pdescr').val(pconfig.peers.peer[peernum].descr);
			$('#endpoint').val(pconfig.peers.peer[peernum].endpoint);
			$('#port').val(pconfig.peers.peer[peernum].port);
			$('#persistentkeepalive').val(pconfig.peers.peer[peernum].persistentkeepalive);
			$('#ppublickey').val(pconfig.peers.peer[peernum].publickey);
			$('#allowedips').val(pconfig.peers.peer[peernum].allowedips);
			$('#presharedkey').val(pconfig.peers.peer[peernum].presharedkey);
		} else {
			$('#pdescr').val("");
			$('#endpoint').val("");
			$('#port').val('');
			$('#persistentkeepalive').val('');
			$('#ppublickey').val('');
			$('#allowedips').val('');
			$('#presharedkey').val('');
		}

		$('#peermodal').modal('show');
	});

	$('#addpeer').click(function () {
		$('#peermodal').modal('show');
	});

	$('#closemodal').click(function () {
		$('#peermodal').modal('hide');
	});

	$('#savemodal').click(function () {
		var peernum = $('#peer_num').val();

		$('.peer_group_' + peernum).find('td').eq(1).text($('#pdescr').val())
		$('.peer_group_' + peernum).find('td').eq(2).text($('#endpoint').val())
		$('.peer_group_' + peernum).find('td').eq(3).text($('#port').val())
		$('.peer_group_' + peernum).find('td').eq(4).text($('#ppublickey').val())

		$('#peermodal').modal('hide');
	});

	// Save the form
	$('#saveform').click(function () {
		$('<input>').attr({
			type: 'hidden',
			name: 'save',
			value: 'save'
		}).appendTo(form);
		$(form).submit();
	});

	// Delete a peer
	$('[id^=killpeer_]').click(function () {
		var row = this.id.slice('killpeer_'.length)
		if (confirm('Are you sure you want to delete peer ' + row + '?')) {
			var target = '.peer_group_' + row
			$(target).remove();
		}
	});

	// These are action buttons, not submit buttons
	$('#genpsk').prop('type','button');
	$("#genkeys").prop('type' ,'button');
	$("#savemodal").prop('type' ,'button');

	$('#genpsk').click(function () {
		ajaxRequest = $.ajax(
			{
				url: "/vpn_wg_edit.php",
				type: "post",
				data: {
					ajax: 	"ajax"
				}
			}
		);

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {
			if (response.length > 0) {
				$('#presharedkey').val(response);
			}
		});
	});

	// Request a new public/private key pair
	$('#genkeys').click(function(event) {
		if ($('#privatekey').val().length == 0 || confirm("<?=$genkeywarning?>")) {
			ajaxRequest = $.ajax('/vpn_wg_edit.php',
				{
				type: 'post',
				data: {
					action: 'genkeys'
				},
				success: function(response, textStatus, jqXHR) {
					resp = JSON.parse(response);
					// console.log(response);
					$('#publickey').val(resp.pubkey);
					$('#privatekey').val(resp.privkey);
				}
			});
		}
	});
});
//]]>
</script>

<?php

include("foot.inc");
