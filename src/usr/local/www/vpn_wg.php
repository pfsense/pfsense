<?php
/*
 * vpn_ipsec.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
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
##|*IDENT=page-vpn-ipsec
##|*NAME=VPN: IPsec
##|*DESCR=Allow access to the 'VPN: IPsec' page.
##|*MATCH=vpn_ipsec.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("wg.inc");

init_config_arr(array('wireguard', 'tunnel'));
$tunnels = &$config['wireguard']['tunnel'];

$pgtitle = array(gettext("VPN"), gettext("Wireguard"), gettext("Tunnels"));
$pglinks = array("", "@self", "@self");
$shortcut_section = "wireguard";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('wireguard')) {
	print_apply_box(gettext("The Wireguard tunnel configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}
?>

<form name="mainform" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Wireguard Tunnels')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th class="peer-entries"></th>
						<th><?=gettext("Name")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Address")?></th>
						<th><?=gettext("Port")?></th>
						<th><?=gettext("Private key")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
<?php

$i = 0; foreach ($tunnels as $tunnel):

	$iconfn = "pass";

	$entryStatus = (isset($tunnel['disabled']) ? 'disabled' : 'enabled');

	if ($entryStatus == 'disabled') {
		$iconfn .= "_d";
	}
?>
					<tr id="fr<?=$i?>" id="frd<?=$i?>"  class="<?= $entryStatus ?>">
						<td class="peer-entries"><?=gettext('Interface')?></td>
						<td>wg<?=$i?></td>
						<td><?=$tunnel['descr']?></td>
						<td><?=$tunnel['interface']['address']?></td>
						<td><?=$tunnel['interface']['listenport']?></td>
						<td><?=$tunnel['interface']['privatekey']?></td>
				
						<td style="cursor: pointer;">
							<a class="fa fa-pencil" href="vpn_wg_edit.php?index=<?=$i?>" title="<?=gettext("Edit tunnel"); ?>"></a>
							<a class="fa fa-trash" id="Xdel_<?=$i?>" title="<?=gettext('Delete tunnel'); ?>"></a>
						</td>
					</tr>

					<tr  class="peer-entries" style="background-color:#ccf2ff;"> <!-- Move to pfSense.css -->
						<td>Peers</td>
						<td colspan="6">
							<table class="table table-hover" style="background-color:#ccf2ff;"> <!-- Move to pfSense.css -->
								<thead>
									<tr>
										<th>Description</th>
										<th >Endpoint</th>
										<th>Allowed IPs</th>
										<th >Public key</th>
									</tr>
								</thead>
								<tbody>

<?php $idx=0; foreach ($tunnel['peers'] as $peer) { ?>
									<tr>
										<td><?=$peer['descr']?></td>
										<td><?=$peer['endpoint']?></td>
										<td><?=$peer['allowedips']?></td>
										<td><?=$peer['publickey']?></td>
									</tr>
<?php $idx++; } ?>
								</tbody>
							</table>
						</td>
					</tr>
<?php
					$i++;
				endforeach;	 // $a_phase1 as $ph1ent
?>
				</tbody>
			</table>
		</div>
	</div>

	<nav class="action-buttons">
		<a href="#" class="btn btn-info btn-sm" id="showpeers">
			<i class="fa fa-info icon-embed-btn"></i>
			<?=gettext("Show peers")?>
		</a>

		<a href="vpn_wg_edit.php?index=<?=$i?>" class="btn btn-success btn-sm">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Add Tunnel")?>
		</a>
	</nav>
</form>

<script type="text/javascript">
//<![CDATA[
function show_phase2(id, buttonid) {
	document.getElementById(buttonid).innerHTML='';
	document.getElementById(id).style.display = "block";
	var visible = id + '-visible';
	document.getElementById(visible).value = "1";
}

events.push(function() {
	var peershidden = true;

	hideClass('peer-entries', peershidden);

	// Toggle peer visibility
	$('#showpeers').click(function () {
		peershidden = !peershidden;
		hideClass('peer-entries', peershidden);
	})

	// Delete tunnel
	$('[id^=Xdel_]').click(function (event) {
		$('#' + event.target.id.slice(1)).click();
	});
});
//]]>
</script>

<?php
include("foot.inc");
