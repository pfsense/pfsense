<?php
/*
	vpn_ipsec_phase2.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

##|+PRIV
##|*IDENT=page-vpn-ipsec-editphase2
##|*NAME=VPN: IPsec: Edit Phase 2 page
##|*DESCR=Allow access to the 'VPN: IPsec: Edit Phase 2' page.
##|*MATCH=vpn_ipsec_phase2.php*
##|-PRIV

require("functions.inc");
require("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

if (!is_array($config['ipsec']['client']))
	$config['ipsec']['client'] = array();

$a_client = &$config['ipsec']['client'];

if (!is_array($config['ipsec']['phase2']))
	$config['ipsec']['phase2'] = array();

$a_phase2 = &$config['ipsec']['phase2'];

$p2index = $_GET['p2index'];
if (isset($_POST['p2index']))
	$p2index = $_POST['p2index'];

if (isset($_GET['dup']))
	$p2index = $_GET['dup'];

if (isset($p2index) && $a_phase2[$p2index])
{
	$pconfig['ikeid'] = $a_phase2[$p2index]['ikeid'];
	$pconfig['disabled'] = isset($a_phase2[$p2index]['disabled']);
	$pconfig['mode'] = $a_phase2[$p2index]['mode'];
	$pconfig['cisco'] = isset($a_phase2[$p2index]['cisco']);
	$pconfig['protocal'] = $a_phase2[$p2index]['protocal'];
	$pconfig['descr'] = $a_phase2[$p2index]['descr'];
	$old_ph2ent = $a_phase2[$p2index];

	idinfo_to_pconfig("local",$a_phase2[$p2index]['localid'],$pconfig);
	idinfo_to_pconfig("remote",$a_phase2[$p2index]['remoteid'],$pconfig);

	$pconfig['proto'] = $a_phase2[$p2index]['protocol'];
	ealgos_to_pconfig($a_phase2[$p2index]['encryption-algorithm-option'],$pconfig);
	$pconfig['halgos'] = $a_phase2[$p2index]['hash-algorithm-option'];
	$pconfig['pfsgroup'] = $a_phase2[$p2index]['pfsgroup'];
	$pconfig['lifetime'] = $a_phase2[$p2index]['lifetime'];
	$pconfig['pinghost'] = $a_phase2[$p2index]['pinghost'];

	if (isset($a_phase2[$p2index]['mobile']))
		$pconfig['mobile'] = true;
}
else
{
	$pconfig['ikeid'] = $_GET['ikeid'];

	/* defaults */
	$pconfig['localid_type'] = "lan";
	$pconfig['remoteid_type'] = "network";
	$pconfig['proto'] = "esp";
	$pconfig['ealgos'] = explode(",", "3des,blowfish,cast128,aes");
	$pconfig['halgos'] = explode(",", "hmac_sha1,hmac_md5");
	$pconfig['pfsgroup'] = "0";
	$pconfig['lifetime'] = "3600";

    /* mobile client */
    if($_GET['mobile'])
        $pconfig['mobile']=true;
}

if (isset($_GET['dup']))
	unset($p2index);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (!isset( $_POST['ikeid']))
		$input_errors[] = gettext("A valid ikeid must be specified.");

	/* input validation */
	$reqdfields = explode(" ", "localid_type halgos");
	$reqdfieldsn = array(gettext("Local network type"),gettext("P2 Hash Algorithms"));
	if (!isset($pconfig['mobile'])){
		$reqdfields[] = "remoteid_type";
		$reqdfieldsn[] = gettext("Remote network type");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if($pconfig['mode'] == "tunnel")
	{
		switch ($pconfig['localid_type']) {
			case "network":
				if (($pconfig['localid_netbits'] != 0 && !$pconfig['localid_netbits']) || !is_numeric($pconfig['localid_netbits']))
					$input_errors[] = gettext("A valid local network bit count must be specified.");
			case "address":
				if (!$pconfig['localid_address'] || !is_ipaddr($pconfig['localid_address']))
					$input_errors[] = gettext("A valid local network IP address must be specified.");
				break;
		}

		switch ($pconfig['remoteid_type']) {
			case "network":
				if (($pconfig['remoteid_netbits'] != 0 && !$pconfig['remoteid_netbits']) || !is_numeric($pconfig['remoteid_netbits']))
					$input_errors[] = gettext("A valid remote network bit count must be specified.");
			case "address":
				if (!$pconfig['remoteid_address'] || !is_ipaddr($pconfig['remoteid_address']))
					$input_errors[] = gettext("A valid remote network IP address must be specified.");
				break;
		}
	}
	/* Validate enabled phase2's are not duplicates */
	if (isset($pconfig['mobile'])){
		/* User is adding phase 2 for mobile phase1 */
		foreach($a_phase2 as $key => $name){
			if (isset($name['mobile'])){
				/* check duplicate localids only for mobile clents */
				$localid_data = ipsec_idinfo_to_cidr($name['localid']);
				$entered = array();
				$entered['type'] = $pconfig['localid_type'];
				if (isset($pconfig['localid_address'])) $entered['address'] = $pconfig['localid_address'];
				if (isset($pconfig['localid_netbits'])) $entered['netbits'] = $pconfig['localid_netbits'];
				$entered_localid_data = ipsec_idinfo_to_cidr($entered);
				if ($localid_data == $entered_localid_data){
					if (!isset($pconfig['p2index'])){
						/* adding new p2 entry */
						$input_errors[] = gettext("Phase2 with this Local Network is already defined for mobile clients.");
						break;
					}else if ($pconfig['p2index'] != $key){
						/* editing p2 and entered p2 networks match with different p2 for given p1 */
						$input_errors[] = gettext("Phase2 with this Local Network is already defined for mobile clients.");
						break;
					}
				}
			}
		}
	}else{
		/* User is adding phase 2 for site-to-site phase1 */
		$input_error = 0;
		foreach($a_phase2 as $key => $name){
			if (!isset($name['mobile']) && $pconfig['ikeid'] == $name['ikeid']){
				/* check duplicate subnets only for given phase1 */
				$localid_data = ipsec_idinfo_to_cidr($name['localid']);
				$remoteid_data = ipsec_idinfo_to_cidr($name['remoteid']);
				$entered_local = array();
				$entered_local['type'] = $pconfig['localid_type'];
				if (isset($pconfig['localid_address'])) $entered_local['address'] = $pconfig['localid_address'];
				if (isset($pconfig['localid_netbits'])) $entered_local['netbits'] = $pconfig['localid_netbits'];
				$entered_localid_data = ipsec_idinfo_to_cidr($entered_local);
				$entered_remote = array();
				$entered_remote['type'] = $pconfig['remoteid_type'];
				if (isset($pconfig['remoteid_address'])) $entered_remote['address'] = $pconfig['remoteid_address'];
				if (isset($pconfig['remoteid_netbits'])) $entered_remote['netbits'] = $pconfig['remoteid_netbits'];
				$entered_remoteid_data = ipsec_idinfo_to_cidr($entered_remote);
				if ($localid_data == $entered_localid_data && $remoteid_data == $entered_remoteid_data) { 
					if (!isset($pconfig['p2index'])){
						/* adding new p2 entry */
						$input_errors[] = gettext("Phase2 with this Local/Remote networks combination is already defined for this Phase1.");
						break;
					}else if ($pconfig['p2index'] != $key){
						/* editing p2 and entered p2 networks match with different p2 for given p1 */
						$input_errors[] = gettext("Phase2 with this Local/Remote networks combination is already defined for this Phase1.");
						break;
					}
				}
			}
		}
        }
	
	$ealgos = pconfig_to_ealgos($pconfig);

	if (!count($ealgos)) {
		$input_errors[] = gettext("At least one encryption algorithm must be selected.");
	}
	if (($_POST['lifetime'] && !is_numeric($_POST['lifetime']))) {
		$input_errors[] = gettext("The P2 lifetime must be an integer.");
	}

	if (!$input_errors) {

		$ph2ent['ikeid'] = $pconfig['ikeid'];
		$ph2ent['mode'] = $pconfig['mode'];
		$ph2ent['disabled'] = $pconfig['disabled'] ? true : false;

		if($ph2ent['mode'] == "tunnel") {
			$ph2ent['localid'] = pconfig_to_idinfo("local",$pconfig);
			$ph2ent['remoteid'] = pconfig_to_idinfo("remote",$pconfig);
		}
		else {
			$ph2ent['protocal'] = $pconfig['protocal'];
		}
		$ph2ent['cisco'] = $pconfig['cisco'] ? true : false;
		$ph2ent['protocol'] = $pconfig['proto'];
		$ph2ent['encryption-algorithm-option'] = $ealgos;
		$ph2ent['hash-algorithm-option'] = $pconfig['halgos'];
		$ph2ent['pfsgroup'] = $pconfig['pfsgroup'];
		$ph2ent['lifetime'] = $pconfig['lifetime'];
		$ph2ent['pinghost'] = $pconfig['pinghost'];
		$ph2ent['descr'] = $pconfig['descr'];

		if (isset($pconfig['mobile']))
			$ph2ent['mobile'] = true;

		if (isset($p2index) && $a_phase2[$p2index])
			$a_phase2[$p2index] = $ph2ent;
		else
			$a_phase2[] = $ph2ent;


		/* now we need to find all phase2 entries for this host */
		if(is_array($ph2ent)) {
			ipsec_lookup_phase1($ph2ent, $ph1ent);
			$old_ph1ent = $ph1ent;
			$old_ph1ent['remote-gateway'] = resolve_retry($old_ph1ent['remote-gateway']);
			reload_tunnel_spd_policy ($ph1ent, $ph2ent, $old_ph1ent, $old_ph2ent);
		}

		write_config();
		mark_subsystem_dirty('ipsec');

		header("Location: vpn_ipsec.php");
		exit;
	}
}

if ($pconfig['mobile'])
    $pgtitle = array(gettext("VPN"),gettext("IPsec"),gettext("Edit Phase 2"), gettext("Mobile Client"));
else
    $pgtitle = array(gettext("VPN"),gettext("IPsec"),gettext("Edit Phase 2"));
$statusurl = "diag_ipsec.php";
$logurl = "diag_logs_ipsec.php";


include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script language="JavaScript">
<!--

function change_protocal() {
	
}
function change_mode() {
	index = document.iform.mode.selectedIndex;
	value = document.iform.mode.options[index].value;
	if (value == 'tunnel') {
		document.getElementById('opt_localid').style.display = '';
<?php if (!isset($pconfig['mobile'])): ?>
		document.getElementById('opt_remoteid').style.display = '';
<?php endif; ?>
		document.getElementById('opt_protocal').style.display = 'none';
	} else {
		document.getElementById('opt_localid').style.display = 'none';
		document.getElementById('opt_protocal').style.display = '';
<?php if (!isset($pconfig['mobile'])): ?>
		document.getElementById('opt_remoteid').style.display = 'none';
<?php endif; ?>
	}
}

function typesel_change_local(bits) {

	if (typeof(bits)=="undefined")
		bits = 24;

	switch (document.iform.localid_type.selectedIndex) {
		case 0:	/* single */
			document.iform.localid_address.disabled = 0;
			document.iform.localid_netbits.value = 0;
			document.iform.localid_netbits.disabled = 1;
			break;
		case 1:	/* network */
			document.iform.localid_address.disabled = 0;
			document.iform.localid_netbits.value = bits;
			document.iform.localid_netbits.disabled = 0;
			break;
		case 3:	/* none */
			document.iform.localid_address.disabled = 1;
			document.iform.localid_netbits.disabled = 1;
			break;
		default:
			document.iform.localid_address.value = "";
			document.iform.localid_address.disabled = 1;
			document.iform.localid_netbits.value = 0;
			document.iform.localid_netbits.disabled = 1;
			break;
	}
}

<?php if (!isset($pconfig['mobile'])): ?>

function typesel_change_remote(bits) {

	if (typeof(bits)=="undefined")
		bits = 24;

	switch (document.iform.remoteid_type.selectedIndex) {
		case 0:	/* single */
			document.iform.remoteid_address.disabled = 0;
			document.iform.remoteid_netbits.value = 0;
			document.iform.remoteid_netbits.disabled = 1;
			break;
		case 1:	/* network */
			document.iform.remoteid_address.disabled = 0;
			document.iform.remoteid_netbits.value = bits;
			document.iform.remoteid_netbits.disabled = 0;
			break;
		default:
			document.iform.remoteid_address.value = "";
			document.iform.remoteid_address.disabled = 1;
			document.iform.remoteid_netbits.value = 0;
			document.iform.remoteid_netbits.disabled = 1;
			break;
	}
}

<?php endif; ?>

function change_protocol() {
	index = document.iform.proto.selectedIndex;
	value = document.iform.proto.options[index].value;
	if (value == 'esp')
		document.getElementById('opt_enc').style.display = '';
	else
		document.getElementById('opt_enc').style.display = 'none';
}

//-->
</script>

<form action="vpn_ipsec_phase2.php" method="post" name="iform" id="iform">

<?php
	if ($input_errors)
		print_input_errors($input_errors);
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr class="tabnavtbl">
		<td id="tabnav">
			<?php
				$tab_array = array();
				$tab_array[0] = array(gettext("Tunnels"), true, "vpn_ipsec.php");
				$tab_array[1] = array(gettext("Mobile clients"), false, "vpn_ipsec_mobile.php");
				$tab_array[2] = array(gettext("Pre-shared keys"), false, "vpn_ipsec_keys.php");
				display_top_tabs($tab_array);
			?>
		</td>
	</tr>
	<tr>
		<td id="mainarea">
			<div class="tabcont">
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Disabled"); ?></td>
						<td width="78%" class="vtable">
							<input name="disabled" type="checkbox" id="disabled" value="yes" <?php if ($pconfig['disabled']) echo "checked"; ?>>
							<strong><?=gettext("Disable this phase2 entry"); ?></strong>
							<br>
							<span class="vexpl"><?=gettext("Set this option to disable this phase2 entry without " .
							  "removing it from the list"); ?>.
							</span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Cisco Mode"); ?></td>
						<td width="78%" class="vtable">
							<input name="cisco" type="checkbox" id="cisco" value="yes" <?php if ($pconfig['cisco']) echo "checked"; ?>>
							<strong><?=gettext("Enable Cisco Compatibility Mode"); ?></strong>
							<br>
							<span class="vexpl"><?=gettext("Set this option to enable cisco " .
							  "compatible IPSEC configuration (ADVANCED)"); ?>.
							</span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Mode"); ?></td>
						<td width="78%" class="vtable">
							<select name="mode" class="formselect" onChange="change_mode()">
								<?php
									foreach($p2_modes as $name => $value):
										$selected = "";
										if ($name == $pconfig['mode'])
											$selected = "selected";
								?>
								<option value="<?=$name;?>" <?=$selected;?>><?=$value;?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="opt_protocal">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Protocal"); ?></td>
						<td width ="78%" class="vtable">
								<select name="protocal" class="formselect" onChange="change_protocal()">
									<?php
										foreach($p2_protocal as $name => $value):
											$selected = "";
											if ($name == $pconfig['protocal'])
												$selected = "selected";
									?>
									<option value="<?=$name;?>" <?=$selected;?>><?=$value;?></option>
									<?php endforeach; ?>
								</select>
						</td>
					</tr>			
					<tr id="opt_localid">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Local Network"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="0" cellpadding="0">
								<tr>
									<td><?=gettext("Type"); ?>:&nbsp;&nbsp;</td>
									<td></td>
									<td>
										<select name="localid_type" class="formselect" onChange="typesel_change_local()">
											<option value="address" <?php if ($pconfig['localid_type'] == "address") echo "selected";?>><?=gettext("Address"); ?></option>
											<option value="network" <?php if ($pconfig['localid_type'] == "network") echo "selected";?>><?=gettext("Network"); ?></option>
											<?php
												$iflist = get_configured_interface_with_descr();
												foreach ($iflist as $ifname => $ifdescr):
											?>
											<option value="<?=$ifname; ?>" <?php if ($pconfig['localid_type'] == $ifname ) echo "selected";?>><?=sprintf(gettext("%s subnet"), $ifdescr); ?></option>
											<?php endforeach; ?>
											<option value="none" <?php if ($pconfig['localid_type'] == "none" ) echo "selected";?>><?=gettext("None"); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<td><?=gettext("Address:");?>&nbsp;&nbsp;</td>
									<td><?=$mandfldhtmlspc;?></td>
									<td>
										<input name="localid_address" type="text" class="formfld unknown" id="localid_address" size="20" value="<?=htmlspecialchars($pconfig['localid_address']);?>">
										/
										<select name="localid_netbits" class="formselect" id="localid_netbits">
										<?php for ($i = 32; $i >= 0; $i--): ?>
											<option value="<?=$i;?>" <?php if ($i == $pconfig['localid_netbits']) echo "selected"; ?>>
												<?=$i;?>
											</option>
										<?php endfor; ?>
										</select>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<?php if (!isset($pconfig['mobile'])): ?>
					
					<tr id="opt_remoteid">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Remote Network"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="0" cellpadding="0">
								<tr>
									<td><?=gettext("Type"); ?>:&nbsp;&nbsp;</td>
									<td></td>
									<td>
										<select name="remoteid_type" class="formselect" onChange="typesel_change_remote()">
											<option value="address" <?php if ($pconfig['remoteid_type'] == "address") echo "selected"; ?>><?=gettext("Address"); ?></option>
											<option value="network" <?php if ($pconfig['remoteid_type'] == "network") echo "selected"; ?>><?=gettext("Network"); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<td><?=gettext("Address"); ?>:&nbsp;&nbsp;</td>
									<td><?=$mandfldhtmlspc;?></td>
									<td>
										<input name="remoteid_address" type="text" class="formfld unknown" id="remoteid_address" size="20" value="<?=htmlspecialchars($pconfig['remoteid_address']);?>">
										/
										<select name="remoteid_netbits" class="formselect" id="remoteid_netbits">
										<?php for ($i = 32; $i >= 0; $i--) { 
											
											echo "<option value=\"{$i}\"";
											if ($i == $pconfig['remoteid_netbits']) echo " selected";
											echo ">{$i}</option>\n";
											} ?>
										</select>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					
					<?php endif; ?>
					
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
						<td width="78%" class="vtable">
							<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
							<br>
							<span class="vexpl">
								<?=gettext("You may enter a description here " .
								"for your reference (not parsed)"); ?>.
							</span>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic">
							<?=gettext("Phase 2 proposal (SA/Key Exchange)"); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Protocol"); ?></td>
						<td width="78%" class="vtable">
							<select name="proto" class="formselect" onChange="change_protocol()">
							<?php foreach ($p2_protos as $proto => $protoname): ?>
								<option value="<?=$proto;?>" <?php if ($proto == $pconfig['proto']) echo "selected"; ?>>
									<?=htmlspecialchars($protoname);?>
								</option>
							<?php endforeach; ?>
							</select>
							<br>
							<span class="vexpl">
								<?=gettext("ESP is encryption, AH is authentication only"); ?>
							</span>
						</td>
					</tr>
					<tr id="opt_enc">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Encryption algorithms"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="0" cellpadding="0">
							<?php
								foreach ($p2_ealgos as $algo => $algodata):
									$checked = '';
									if (in_array($algo,$pconfig['ealgos']))
										$checked = " checked";
								?>
								<tr>
									<td>
										<input type="checkbox" name="ealgos[]" value="<?=$algo;?>"<?=$checked?>>
									</td>
									<td>
										<?=htmlspecialchars($algodata['name']);?>
									</td>
									<td>
										<?php if(is_array($algodata['keysel'])): ?>
										&nbsp;&nbsp;
										<select name="keylen_<?=$algo;?>" class="formselect">
											<option value="auto"><?=gettext("auto"); ?></option>
											<?php
												$key_hi = $algodata['keysel']['hi'];
												$key_lo = $algodata['keysel']['lo'];
												$key_step = $algodata['keysel']['step'];
												for ($keylen = $key_hi; $keylen >= $key_lo; $keylen -= $key_step):
													$selected = '';
				//									if ($checked && in_array("keylen_".$algo,$pconfig))
													if ($keylen == $pconfig["keylen_".$algo])
														$selected = " selected";
											?>
											<option value="<?=$keylen;?>"<?=$selected;?>><?=$keylen;?> <?=gettext("bits"); ?></option>
											<?php endfor; ?>
										</select>
										<?php endif; ?>
									</td>
								</tr>
								
								<?php endforeach; ?>
								
							</table>
							<br>
							<?=gettext("Hint: use 3DES for best compatibility or if you have a hardware " . 
							"crypto accelerator card. Blowfish is usually the fastest in " .
							"software encryption"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Hash algorithms"); ?></td>
						<td width="78%" class="vtable">
						<?php foreach ($p2_halgos as $algo => $algoname): ?>
							<input type="checkbox" name="halgos[]" value="<?=$algo;?>" <?php if (in_array($algo, $pconfig['halgos'])) echo "checked"; ?>>
							<?=htmlspecialchars($algoname);?>
							<br>
						<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("PFS key group"); ?></td>
						<td width="78%" class="vtable">
						<?php if (!isset($pconfig['mobile']) || !isset($a_client['pfs_group'])): ?>
							<select name="pfsgroup" class="formselect">
							<?php foreach ($p2_pfskeygroups as $keygroup => $keygroupname): ?>
								<option value="<?=$keygroup;?>" <?php if ($keygroup == $pconfig['pfsgroup']) echo "selected"; ?>>
									<?=htmlspecialchars($keygroupname);?>
								</option>
							<?php endforeach; ?>
							</select>
							<br>
							<span class="vexpl">
								<em>
									<?=gettext("1 = 768 bit, 2 = 1024 bit, 5 = 1536 bit"); ?>
								</em>
							</span>
							
							<?php else: ?>

							<select class="formselect" disabled>
								<option selected><?=$p2_pfskeygroups[$a_client['pfs_group']];?></option>
							</select>
							<input name="pfsgroup" type="hidden" value="<?=htmlspecialchars($pconfig['pfsgroup']);?>">
							<br>
							<span class="vexpl"><em><?=gettext("Set globally in mobile client options"); ?></em></span>
						<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Lifetime"); ?></td>
						<td width="78%" class="vtable">
							<input name="lifetime" type="text" class="formfld unknown" id="lifetime" size="20" value="<?=htmlspecialchars($pconfig['lifetime']);?>">
							<?=gettext("seconds"); ?>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Advanced Options"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Automatically ping host"); ?></td>
						<td width="78%" class="vtable">
							<input name="pinghost" type="text" class="formfld unknown" id="pinghost" size="20" value="<?=htmlspecialchars($pconfig['pinghost']);?>">
							<?=gettext("IP address"); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%">
						<?php if (isset($p2index) && $a_phase2[$p2index]): ?>
							<input name="p2index" type="hidden" value="<?=$p2index;?>">
						<?php endif; ?>
						<?php if ($pconfig['mobile']): ?>
							<input name="mobile" type="hidden" value="true">
							<input name="remoteid_type" type="hidden" value="mobile">
						<?php endif; ?>
							<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>">
							<input name="ikeid" type="hidden" value="<?=htmlspecialchars($pconfig['ikeid']);?>">
						</td>
					</tr>
				</table>
			</div>
		</td>
	</tr>
</table>
</form>
<script lannguage="JavaScript">
<!--
change_mode('<?=htmlspecialchars($pconfig['mode'])?>');
change_protocol('<?=htmlspecialchars($pconfig['proto'])?>');
typesel_change_local(<?=htmlspecialchars($pconfig['localid_netbits'])?>);
<?php if (!isset($pconfig['mobile'])): ?>
typesel_change_remote(<?=htmlspecialchars($pconfig['remoteid_netbits'])?>);
<?php endif; ?>
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>

<?php

/* local utility functions */

function pconfig_to_ealgos(& $pconfig) {

	global $p2_ealgos;

	$ealgos = array();
	foreach ($p2_ealgos as $algo_name => $algo_data) {
		if (in_array($algo_name,$pconfig['ealgos'])) {
			$ealg = array();
			$ealg['name'] = $algo_name;
			if (is_array($algo_data['keysel']))
				$ealg['keylen'] = $_POST["keylen_".$algo_name];
			$ealgos[] = $ealg;
		}
	}

	return $ealgos;
}

function ealgos_to_pconfig(& $ealgos,& $pconfig) {

	$pconfig['ealgos'] = array();
	foreach ($ealgos as $algo_data) {
		$pconfig['ealgos'][] = $algo_data['name'];
		if (isset($algo_data['keylen']))
			$pconfig["keylen_".$algo_data['name']] = $algo_data['keylen'];
	}

	return $ealgos;
}

function pconfig_to_idinfo($prefix,& $pconfig) {

	$type = $pconfig[$prefix."id_type"];
	$address = $pconfig[$prefix."id_address"];
	$netbits = $pconfig[$prefix."id_netbits"];

	switch( $type )
	{
		case "address":
			return array('type' => $type, 'address' => $address);
		case "network":
			return array('type' => $type, 'address' => $address, 'netbits' => $netbits);
		default:
			return array('type' => $type );
	}
}

function idinfo_to_pconfig($prefix,& $idinfo,& $pconfig) {

	switch( $idinfo['type'] )
	{
		case "address":
			$pconfig[$prefix."id_type"] = $idinfo['type'];
			$pconfig[$prefix."id_address"] = $idinfo['address'];
			break;
		case "network":
			$pconfig[$prefix."id_type"] = $idinfo['type'];
			$pconfig[$prefix."id_address"] = $idinfo['address'];
			$pconfig[$prefix."id_netbits"] = $idinfo['netbits'];
			break;
		default:
			$pconfig[$prefix."id_type"] = $idinfo['type'];
			break;
	}
}

?>

