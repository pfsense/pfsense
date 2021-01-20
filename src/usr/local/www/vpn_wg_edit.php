<?php

/*
 * vpn_wg_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-vpn-wg-edit
##|*NAME=VPN: WireGuard: Edit
##|*DESCR=Edit WireGuard tunnele.
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
	switch ($_REQUEST['action']) {
		case "genpsk" : { 
			print(gerneratePSK());
			exit;
		}
	}
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
} elseif ($_POST['action'] == 'genkeys') {
	// Process ajax call requesting new key pair
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

$pgtitle = array(gettext("VPN"), gettext("WireGuard"), gettext("Tunnel"));
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
))->setHelp('Comma separated list of CIDR addresses assigned to interface.');


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

// ============ Peer edit modal ==================================
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
	'Endpoint',
	'text'
))->setHelp("Hostname, IPv4 or IP46 address");

$section2->addInput(new Form_Input(
	'port',
	'Endpoint port',
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
))->setHelp("List of CIDR IPs allowed to connect");

$section2->addInput(new Form_Input(
	'peerwgaddr',
	'Peer WireGuard Address',
	'text'
))->setHelp("IPv4/IPv6 specifies the WireGuard interface address of the peer, since it can differ from AllowedIPs");

$section2->addInput(new Form_Input(
	'peernwks',
	'Peer Routed Networks',
	'text'
))->setHelp("Comma separated list of networks to route using the automatic gateway provided by \"Peer WireGuard Address\"");

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
			<button type="submit" id="closemodal" class="btn btn-sm btn-info" title="<?=gettext('Cancel')?>">
				<?=gettext("Cancel")?>
			</button>

			<button type="submit" id="savemodal" class="btn btn-sm btn-primary" title="<?=gettext('Update peer')?>">
				<?=gettext("Update")?>
			</button>
		</nav>
      </div>
    </div>

  </div>
</div>

<div class="panel panel-default">
	<div id="mainarea" class="table-responsive panel-body">
		<table id="peertable" class="table table-hover table-striped table-condensed" style="overflow-x: 'visible'">
			<thead>
				<tr>
					<th><?=gettext("Peer")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Endpoint")?></th>
					<th><?=gettext("Port")?></th>
					<th><?=gettext("Public key")?></th>
					<th style="display:none;"><?=gettext("Keepalive")?></th>
					<th style="display:none;"><?=gettext("Allowed IPs")?></th>
					<th style="display:none;"><?=gettext("PSK")?></th>
					<th></th>

				</tr>
			</thead>

			<tbody>
<?php
				$peer_num = 0;
				if (!empty($pconfig['peers']['wgpeer'])) {
					foreach ($pconfig['peers']['wgpeer'] as $peer) {
						print('<tr class="peer_group_' . $peer_num . '">');
						print("<td>{$peer_num}</td>\n");
						print("<td>{$peer['descr']}</td>\n");
						print("<td>{$peer['endpoint']}</td>\n");
						print("<td>{$peer['port']}</td>\n");
						print("<td>{$peer['publickey']}</td>\n");

						// hidden columns
						print("<td style=\"display:none;\">{$peer['persistenkeepalive']}</td>\n");
						print("<td style=\"display:none;\">{$peer['allowedips']}</td>\n");
						print("<td style=\"display:none;\">{$peer['presharedkey']}</td>\n");
						print("<td style=\"display:none;\">{$peer['peerwgaddr']}</td>\n");
						print("<td style=\"display:none;\">{$peer['peernwks']}</td>\n");
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
<?php $genkeywarning = gettext("Are you sure you want to overwrite keys?"); ?>

<!-- ============== JavaScript =================================================================================================-->
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var pconfig = JSON.parse('<?=$jpconfig?>');

	// Eliminate ghost lines in modal
	$('.form-group').css({"border-bottom-width" : "0"});

	// Return text from peer table cell
	function tabletext (row, col) {
		row++; col++;
		return $('#peertable tr:nth-child(' + row + ') td:nth-child('+ col + ')').text();
	}

	$('#addpeer').click(function () {
		$('#peermodal').modal('show');
		incrementPeer($('#address').val());
	});

	$('#closemodal').click(function () {
		$('#peermodal').modal('hide');
	});

	$('#savemodal').click(function () {
		var errmsg = [];

		if ($('#ppublickey').val().length === 0) {
			errmsg.push("A public key is required");
		}

		if (errmsg.length > 0) {
			var errstr = "";

			for(var i=0; i<errmsg.length; i++) {
				errstr += (errmsg[i] + "\n");
			}

			alert(errstr);
			return;
		}

		var peernum = $('#peer_num').val();

		if (peernum == 'new') {
			if ($('#peertable tbody').find('tr').length === 0) { // Now entries
				peernum = 0;
				$('#peer_num').val(0);

				$('#peertable tbody').append('<tr class="peer_group_' + peernum + '"> <td></td> <td></td> <td></td> <td></td> <td></td> <td style="display:none;"></td> <td style="display:none;"></td> <td style="display:none;"></td> <td style="display:none;"></td> <td style="display:none;"></td> <td style="cursor: pointer;"><a class="fa fa-pencil" href="#" id="editpeer_' + peernum + '"title="<?=gettext("Edit peer"); ?>"></a> <a class="fa fa-trash text-danger no-confirm" href="#" id="killpeer_' + peernum + '" title="<?=gettext('Delete peer');?>"></a></td> </tr>');
			} else {
				var lastrow = $('#peertable tr:last td:nth-child(1)').text()

				$('#peer_num').val(++lastrow)
				peernum = lastrow

				$('#peertable tr:last').after('<tr class="peer_group_' + peernum + '"> <td></td> <td></td> <td></td> <td></td> <td></td> <td style="display:none;"></td> <td style="display:none;"></td> <td style="display:none;"></td> <td style="display:none;"></td> <td style="display:none;"></td><td style="cursor: pointer;"><a class="fa fa-pencil" href="#" id="editpeer_' + peernum + '"title="<?=gettext("Edit peer"); ?>"></a> <a class="fa fa-trash text-danger no-confirm" href="#" id="killpeer_' + peernum + '" title="<?=gettext('Delete peer');?>"></a></td> </tr>');
			}

			attachhandlers()

		}

		$('.peer_group_' + peernum).find('td').eq(0).text(peernum)
		$('.peer_group_' + peernum).find('td').eq(1).text($('#pdescr').val())
		$('.peer_group_' + peernum).find('td').eq(2).text($('#endpoint').val())
		$('.peer_group_' + peernum).find('td').eq(3).text($('#port').val())
		$('.peer_group_' + peernum).find('td').eq(4).text($('#ppublickey').val())
		$('.peer_group_' + peernum).find('td').eq(5).text($('#persistentkeepalive').val())
		$('.peer_group_' + peernum).find('td').eq(6).text($('#allowedips').val())
		$('.peer_group_' + peernum).find('td').eq(7).text($('#presharedkey').val())
		$('.peer_group_' + peernum).find('td').eq(8).text($('#peerwgaddr').val())
		$('.peer_group_' + peernum).find('td').eq(9).text($('#peernwks').val())

		$('#peermodal').modal('hide');
	});

	// Save the form
	$('#saveform').click(function () {
		// For each row in the peers table, construct an array of inputs with the values from the row
		$('#peertable > tbody').find('tr').each(function (idx) {
			console.log('Descr: ' + $(this).find('td').eq(1).text())
			$('<input>').attr({type: 'hidden',name: 'descp' + idx, value: $(this).find('td').eq(1).text()}).appendTo(form);
			$('<input>').attr({type: 'hidden',name: 'endpoint' + idx, value: $(this).find('td').eq(2).text()}).appendTo(form);
			$('<input>').attr({type: 'hidden',name: 'port' + idx, value: $(this).find('td').eq(3).text()}).appendTo(form);
			$('<input>').attr({type: 'hidden',name: 'publickeyp' + idx, value: $(this).find('td').eq(4).text()}).appendTo(form);
			$('<input>').attr({type: 'hidden',name: 'persistentkeepalive' + idx, value: $(this).find('td').eq(5).text()}).appendTo(form);
			$('<input>').attr({type: 'hidden',name: 'allowedips' + idx, value: $(this).find('td').eq(6).text()}).appendTo(form);
			$('<input>').attr({type: 'hidden',name: 'presharedkey' + idx, value: $(this).find('td').eq(7).text()}).appendTo(form);
			$('<input>').attr({type: 'hidden',name: 'peerwgaddr' + idx, value: $(this).find('td').eq(8).text()}).appendTo(form);
			$('<input>').attr({type: 'hidden',name: 'peernwks' + idx, value: $(this).find('td').eq(9).text()}).appendTo(form);
		});

		$('<input>').attr({type: 'hidden',name: 'save',value: 'save'}).appendTo(form);

		// Recaculate the table has so the browser doesn't intercept the save
		tableHash = hashCode($('#peertable').html());
		$(form).submit();
	});

	attachhandlers()

	function attachhandlers() {
		// Delete a peer
		$('[id^=killpeer_]').click(function () {
			var row = this.id.slice('killpeer_'.length)
			if (confirm('Are you sure you want to delete peer ' + row + '?')) {
				var target = '.peer_group_' + row
				$(target).remove();
			}
		});

		// Edit peer - Copy a row from the table to the edit form
		$('[id^=editpeer_]').click(function () {
			var peernum = this.id.slice('editpeer_'.length);

			$('#peer_num').val(peernum);

			// peer -1 means creating a new peer
			if (peernum != "new") {
				$('#pdescr').val(tabletext(peernum, 1));
				$('#endpoint').val(tabletext(peernum, 2));
				$('#port').val(tabletext(peernum, 3));
				$('#ppublickey').val(tabletext(peernum, 4));
				$('#persistentkeepalive').val(tabletext(peernum, 7));
				$('#allowedips').val(tabletext(peernum, 6));
				$('#presharedkey').val(tabletext(peernum, 7));
				$('#peerwgaddr').val(tabletext(peernum, 8));
				$('#peernwks').val(tabletext(peernum, 9));
			} else { // Clear all the fields
				$('#pdescr').val("");
				$('#endpoint').val("");
				$('#port').val('');
				$('#persistentkeepalive').val('');
				$('#ppublickey').val('');
				$('#allowedips').val('');
				$('#presharedkey').val('');
				$('#peerwgaddr').val('');
				$('#peernwks').val('');
			}

			$('#peermodal').modal('show');
		});
	}

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
					ajax: 	"ajax",
					action: "genpsk"
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

	// Warn the user if the peer table has been updated, but the form has not yet been saved ----------------------------
	// SAev te htable state on page load
	var tableHash = hashCode($('#peertable').html());

	window.addEventListener('beforeunload', (event) => {
		// If the table has changed since page load . .
		if (hashCode($('#peertable').html()) !== tableHash) {
			// Cause the browser to display "Are you sure" message)
			// Unfortunately it is no longer possible to customize the browser message
			event.returnValue = '';
		}
	});

	function hashCode(s){
		return s.split("").reduce(function(a,b){a=((a<<5)-a)+b.charCodeAt(0);return a&a},0);
	}
});
//]]>
</script>

<?php

include("foot.inc");
