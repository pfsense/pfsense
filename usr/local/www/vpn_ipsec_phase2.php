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

if (!empty($_GET['p2index']))
	$uindex = $_GET['p2index'];
if (!empty($_POST['uniqid']))
	$uindex = $_POST['uniqid'];

if (!empty($_GET['dup']))
	$uindex = $_GET['dup'];

$ph2found = false;
if (isset($uindex)) {
	foreach ($a_phase2 as $p2index => $ph2) {
		if ($ph2['uniqid'] == $uindex) {
			$ph2found = true;
			break;
		}
	}
}

if ($ph2found === true)
{
	$pconfig['ikeid'] = $ph2['ikeid'];
	$pconfig['disabled'] = isset($ph2['disabled']);
	$pconfig['mode'] = $ph2['mode'];
	$pconfig['descr'] = $ph2['descr'];
	$pconfig['uniqid'] = $ph2['uniqid'];

	if (!empty($ph2['natlocalid']))
		idinfo_to_pconfig("natlocal",$ph2['natlocalid'],$pconfig);
	idinfo_to_pconfig("local",$ph2['localid'],$pconfig);
	idinfo_to_pconfig("remote",$ph2['remoteid'],$pconfig);

	$pconfig['proto'] = $ph2['protocol'];
	ealgos_to_pconfig($ph2['encryption-algorithm-option'],$pconfig);
	$pconfig['halgos'] = $ph2['hash-algorithm-option'];
	$pconfig['pfsgroup'] = $ph2['pfsgroup'];
	$pconfig['lifetime'] = $ph2['lifetime'];
	$pconfig['pinghost'] = $ph2['pinghost'];

	if (isset($ph2['mobile']))
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
	$pconfig['uniqid'] = uniqid();

	/* mobile client */
	if($_GET['mobile'])
		$pconfig['mobile']=true;
}

unset($ph2);
if (!empty($_GET['dup'])) {
	unset($uindex);
	unset($p2index);
	$pconfig['uniqid'] = uniqid();
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (!isset( $_POST['ikeid']))
		$input_errors[] = gettext("A valid ikeid must be specified.");

	/* input validation */
	$reqdfields = explode(" ", "localid_type uniqid");
	$reqdfieldsn = array(gettext("Local network type"), gettext("Unique Identifier"));
	if (!isset($pconfig['mobile'])){
		$reqdfields[] = "remoteid_type";
		$reqdfieldsn[] = gettext("Remote network type");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if(($pconfig['mode'] == "tunnel") || ($pconfig['mode'] == "tunnel6")) 
	{
		switch ($pconfig['localid_type']) {
			case "network":
				if (($pconfig['localid_netbits'] != 0 && !$pconfig['localid_netbits']) || !is_numeric($pconfig['localid_netbits']))
					$input_errors[] = gettext("A valid local network bit count must be specified.");
			case "address":
				if (!$pconfig['localid_address'] || !is_ipaddr($pconfig['localid_address']))
					$input_errors[] = gettext("A valid local network IP address must be specified.");
				elseif (is_ipaddrv4($pconfig['localid_address']) && ($pconfig['mode'] != "tunnel"))
					$input_errors[] = gettext("A valid local network IPv4 address must be specified or you need to change Mode to IPv6");
				elseif (is_ipaddrv6($pconfig['localid_address']) && ($pconfig['mode'] != "tunnel6"))
					$input_errors[] = gettext("A valid local network IPv6 address must be specified or you need to change Mode to IPv4");
				break;
		}
		/* Check if the localid_type is an interface, to confirm if it has a valid subnet. */
		if (is_array($config['interfaces'][$pconfig['localid_type']])) {
			// Don't let an empty subnet into racoon.conf, it can cause parse errors. Ticket #2201.
			$address = get_interface_ip($pconfig['localid_type']);
			$netbits = get_interface_subnet($pconfig['localid_type']);

			if (empty($address) || empty($netbits))
				$input_errors[] = gettext("Invalid Local Network.") . " " . convert_friendly_interface_to_friendly_descr($pconfig['localid_type']) . " " . gettext("has no subnet.");
		}

		if (!empty($pconfig['natlocalid_address'])) {
			switch ($pconfig['natlocalid_type']) {
				case "network":
					if (($pconfig['natlocalid_netbits'] != 0 && !$pconfig['natlocalid_netbits']) || !is_numeric($pconfig['natlocalid_netbits']))
						$input_errors[] = gettext("A valid NAT local network bit count must be specified.");
					if ($pconfig['localid_type'] == "address")
						$input_errors[] = gettext("You cannot configure a network type address for NAT while only an address type is selected for local source."); 
				case "address":
					if (!empty($pconfig['natlocalid_address']) && !is_ipaddr($pconfig['natlocalid_address']))
						$input_errors[] = gettext("A valid NAT local network IP address must be specified.");
					elseif (is_ipaddrv4($pconfig['natlocalid_address']) && ($pconfig['mode'] != "tunnel"))
						$input_errors[] = gettext("A valid NAT local network IPv4 address must be specified or you need to change Mode to IPv6");
					elseif (is_ipaddrv6($pconfig['natlocalid_address']) && ($pconfig['mode'] != "tunnel6"))
						$input_errors[] = gettext("A valid NAT local network IPv6 address must be specified or you need to change Mode to IPv4");
					break;
			}

			if (is_array($config['interfaces'][$pconfig['natlocalid_type']])) {
				// Don't let an empty subnet into racoon.conf, it can cause parse errors. Ticket #2201.
				$address = get_interface_ip($pconfig['natlocalid_type']);
				$netbits = get_interface_subnet($pconfig['natlocalid_type']);

				if (empty($address) || empty($netbits))
					$input_errors[] = gettext("Invalid Local Network.") . " " . convert_friendly_interface_to_friendly_descr($pconfig['natlocalid_type']) . " " . gettext("has no subnet.");
			}
		}

		switch ($pconfig['remoteid_type']) {
			case "network":
				if (($pconfig['remoteid_netbits'] != 0 && !$pconfig['remoteid_netbits']) || !is_numeric($pconfig['remoteid_netbits']))
					$input_errors[] = gettext("A valid remote network bit count must be specified.");
			case "address":
				if (!$pconfig['remoteid_address'] || !is_ipaddr($pconfig['remoteid_address']))
					$input_errors[] = gettext("A valid remote network IP address must be specified.");
				elseif (is_ipaddrv4($pconfig['remoteid_address']) && ($pconfig['mode'] != "tunnel"))
					$input_errors[] = gettext("A valid remote network IPv4 address must be specified or you need to change Mode to IPv6");
				elseif (is_ipaddrv6($pconfig['remoteid_address']) && ($pconfig['mode'] != "tunnel6"))
					$input_errors[] = gettext("A valid remote network IPv6 address must be specified or you need to change Mode to IPv4");
				break;
		}
	}
	/* Validate enabled phase2's are not duplicates */
	if (isset($pconfig['mobile'])){
		/* User is adding phase 2 for mobile phase1 */
		foreach($a_phase2 as $key => $name){
			if (isset($name['mobile']) && $name['uniqid'] != $pconfig['uniqid']) {
				/* check duplicate localids only for mobile clents */
				$localid_data = ipsec_idinfo_to_cidr($name['localid'], false, $name['mode']);
				$entered = array();
				$entered['type'] = $pconfig['localid_type'];
				if (isset($pconfig['localid_address'])) $entered['address'] = $pconfig['localid_address'];
				if (isset($pconfig['localid_netbits'])) $entered['netbits'] = $pconfig['localid_netbits'];
				$entered_localid_data = ipsec_idinfo_to_cidr($entered, false, $pconfig['mode']);
				if ($localid_data == $entered_localid_data){
					/* adding new p2 entry */
					$input_errors[] = gettext("Phase2 with this Local Network is already defined for mobile clients.");
					break;
				}
			}
		}
	}else{
		/* User is adding phase 2 for site-to-site phase1 */
		$input_error = 0;
		foreach($a_phase2 as $key => $name){
			if (!isset($name['mobile']) && $pconfig['ikeid'] == $name['ikeid'] && $pconfig['uniqid'] != $name['uniqid']) {
				/* check duplicate subnets only for given phase1 */
				$localid_data = ipsec_idinfo_to_cidr($name['localid'], false, $name['mode']);
				$remoteid_data = ipsec_idinfo_to_cidr($name['remoteid'], false, $name['mode']);
				$entered_local = array();
				$entered_local['type'] = $pconfig['localid_type'];
				if (isset($pconfig['localid_address'])) $entered_local['address'] = $pconfig['localid_address'];
				if (isset($pconfig['localid_netbits'])) $entered_local['netbits'] = $pconfig['localid_netbits'];
				$entered_localid_data = ipsec_idinfo_to_cidr($entered_local, false, $pconfig['mode']);
				$entered_remote = array();
				$entered_remote['type'] = $pconfig['remoteid_type'];
				if (isset($pconfig['remoteid_address'])) $entered_remote['address'] = $pconfig['remoteid_address'];
				if (isset($pconfig['remoteid_netbits'])) $entered_remote['netbits'] = $pconfig['remoteid_netbits'];
				$entered_remoteid_data = ipsec_idinfo_to_cidr($entered_remote, false, $pconfig['mode']);
				if ($localid_data == $entered_localid_data && $remoteid_data == $entered_remoteid_data) { 
					/* adding new p2 entry */
					$input_errors[] = gettext("Phase2 with this Local/Remote networks combination is already defined for this Phase1.");
					break;
				}
			}
		}
        }

	/* For ESP protocol, handle encryption algorithms */
	if ( $pconfig['proto'] == "esp") {
		$ealgos = pconfig_to_ealgos($pconfig);

		if (!count($ealgos)) {
			$input_errors[] = gettext("At least one encryption algorithm must be selected.");
		} else {
			if (empty($pconfig['halgos'])) {
				foreach ($ealgos as $ealgo) {
					if (!strpos($ealgo['name'], "gcm")) {
						$input_errors[] = gettext("At least one hashing algorithm needs to be selected.");
						break;
					}
				}
			}
		}
		
	}
	if (($_POST['lifetime'] && !is_numeric($_POST['lifetime']))) {
		$input_errors[] = gettext("The P2 lifetime must be an integer.");
	}

	if (!$input_errors) {

		$ph2ent = array();
		$ph2ent['ikeid'] = $pconfig['ikeid'];
		$ph2ent['uniqid'] = $pconfig['uniqid'];
		$ph2ent['mode'] = $pconfig['mode'];
		$ph2ent['disabled'] = $pconfig['disabled'] ? true : false;

		if(($ph2ent['mode'] == "tunnel") || ($ph2ent['mode'] == "tunnel6")){
			if (!empty($pconfig['natlocalid_address']))
				$ph2ent['natlocalid'] = pconfig_to_idinfo("natlocal",$pconfig);
			$ph2ent['localid'] = pconfig_to_idinfo("local",$pconfig);
			$ph2ent['remoteid'] = pconfig_to_idinfo("remote",$pconfig);
		}

		$ph2ent['protocol'] = $pconfig['proto'];
		$ph2ent['encryption-algorithm-option'] = $ealgos;
		if (!empty($pconfig['halgos']))
			$ph2ent['hash-algorithm-option'] = $pconfig['halgos'];
		else
			unset($ph2ent['hash-algorithm-option']);
		$ph2ent['pfsgroup'] = $pconfig['pfsgroup'];
		$ph2ent['lifetime'] = $pconfig['lifetime'];
		$ph2ent['pinghost'] = $pconfig['pinghost'];
		$ph2ent['descr'] = $pconfig['descr'];

		if (isset($pconfig['mobile']))
			$ph2ent['mobile'] = true;

		if ($ph2found === true && $a_phase2[$p2index])
			$a_phase2[$p2index] = $ph2ent;
		else
			$a_phase2[] = $ph2ent;


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
$shortcut_section = "ipsec";


include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script type="text/javascript" src="/javascript/jquery.ipv4v6ify.js"></script>
<script type="text/javascript">
//<![CDATA[

function change_mode() {
	index = document.iform.mode.selectedIndex;
	value = document.iform.mode.options[index].value;
	if ((value == 'tunnel') || (value == 'tunnel6')) {
		document.getElementById('opt_localid').style.display = '';
<?php if (!isset($pconfig['mobile'])): ?>
		document.getElementById('opt_remoteid').style.display = '';
<?php endif; ?>
	} else {
		document.getElementById('opt_localid').style.display = 'none';
<?php if (!isset($pconfig['mobile'])): ?>
		document.getElementById('opt_remoteid').style.display = 'none';
<?php endif; ?>
	}
}

function typesel_change_natlocal(bits) {
	var value = document.iform.mode.options[index].value;
	if (typeof(bits) === "undefined") {
		if (value === "tunnel") {
			bits = 24;
		}
		else if (value === "tunnel6") {
			bits = 64;
		}
	}
	var address_is_blank = !/\S/.test(document.iform.natlocalid_address.value);
	switch (document.iform.natlocalid_type.selectedIndex) {
		case 0:	/* single */
			document.iform.natlocalid_address.disabled = 0;
			if (address_is_blank) {
				document.iform.natlocalid_netbits.value = 0;
			}
			document.iform.natlocalid_netbits.disabled = 1;
			break;
		case 1:	/* network */
			document.iform.natlocalid_address.disabled = 0;
			if (address_is_blank) {
				document.iform.natlocalid_netbits.value = bits;
			}
			document.iform.natlocalid_netbits.disabled = 0;
			break;
		case 3:	/* none */
			document.iform.natlocalid_address.disabled = 1;
			document.iform.natlocalid_netbits.disabled = 1;
			break;
		default:
			document.iform.natlocalid_address.value = "";
			document.iform.natlocalid_address.disabled = 1;
			if (address_is_blank) {
				document.iform.natlocalid_netbits.value = 0;
			}
			document.iform.natlocalid_netbits.disabled = 1;
			break;
	}
}

function typesel_change_local(bits) {
	var value = document.iform.mode.options[index].value;
	if (typeof(bits) === "undefined") {
		if (value === "tunnel") {
			bits = 24;
		}
		else if (value === "tunnel6") {
			bits = 64;
		}
	}
	var address_is_blank = !/\S/.test(document.iform.localid_address.value);
	switch (document.iform.localid_type.selectedIndex) {
		case 0:	/* single */
			document.iform.localid_address.disabled = 0;
			if (address_is_blank) {
				document.iform.localid_netbits.value = 0;
			}
			document.iform.localid_netbits.disabled = 1;
			break;
		case 1:	/* network */
			document.iform.localid_address.disabled = 0;
			if (address_is_blank) {
				document.iform.localid_netbits.value = bits;
			}
			document.iform.localid_netbits.disabled = 0;
			break;
		case 3:	/* none */
			document.iform.localid_address.disabled = 1;
			document.iform.localid_netbits.disabled = 1;
			break;
		default:
			document.iform.localid_address.value = "";
			document.iform.localid_address.disabled = 1;
			if (address_is_blank) {
				document.iform.localid_netbits.value = 0;
			}
			document.iform.localid_netbits.disabled = 1;
			break;
	}
}

<?php if (!isset($pconfig['mobile'])): ?>

function typesel_change_remote(bits) {
	var value = document.iform.mode.options[index].value;
	if (typeof(bits) === "undefined") {
		if (value === "tunnel") {
			bits = 24;
		}
		else if (value === "tunnel6") {
			bits = 64;
		}
	}
	var address_is_blank = !/\S/.test(document.iform.remoteid_address.value);
	switch (document.iform.remoteid_type.selectedIndex) {
		case 0:	/* single */
			document.iform.remoteid_address.disabled = 0;
			if (address_is_blank) {
				document.iform.remoteid_netbits.value = 0;
			}
			document.iform.remoteid_netbits.disabled = 1;
			break;
		case 1:	/* network */
			document.iform.remoteid_address.disabled = 0;
			if (address_is_blank) {
				document.iform.remoteid_netbits.value = bits;
			}
			document.iform.remoteid_netbits.disabled = 0;
			break;
		default:
			document.iform.remoteid_address.value = "";
			document.iform.remoteid_address.disabled = 1;
			if (address_is_blank) {
				document.iform.remoteid_netbits.value = 0;
			}
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

//]]>
</script>

<form action="vpn_ipsec_phase2.php" method="post" name="iform" id="iform">

<?php
	if ($input_errors)
		print_input_errors($input_errors);
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="vpn ipsec phase-2">
	<tr class="tabnavtbl">
		<td id="tabnav">
			<?php
				$tab_array = array();
				$tab_array[0] = array(gettext("Tunnels"), true, "vpn_ipsec.php");
				$tab_array[1] = array(gettext("Mobile clients"), false, "vpn_ipsec_mobile.php");
				$tab_array[2] = array(gettext("Pre-Shared Keys"), false, "vpn_ipsec_keys.php");
				$tab_array[3] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
				display_top_tabs($tab_array);
			?>
		</td>
	</tr>
	<tr>
		<td id="mainarea">
			<div class="tabcont">
				<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Disabled"); ?></td>
						<td width="78%" class="vtable">
							<input name="disabled" type="checkbox" id="disabled" value="yes" <?php if ($pconfig['disabled']) echo "checked=\"checked\""; ?> />
							<strong><?=gettext("Disable this phase2 entry"); ?></strong>
							<br />
							<span class="vexpl"><?=gettext("Set this option to disable this phase2 entry without " .
							  "removing it from the list"); ?>.
							</span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Mode"); ?></td>
						<td width="78%" class="vtable">
							<select name="mode" class="formselect" onchange="change_mode()">
								<?php
									foreach($p2_modes as $name => $value):
										$selected = "";
										if ($name == $pconfig['mode'])
											$selected = "selected=\"selected\"";
								?>
								<option value="<?=$name;?>" <?=$selected;?>><?=$value;?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="opt_localid">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Local Network"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="0" cellpadding="0" summary="local network">
								<tr>
									<td><?=gettext("Type"); ?>:&nbsp;&nbsp;</td>
									<td></td>
									<td>
										<select name="localid_type" class="formselect" onchange="typesel_change_local()">
											<option value="address" <?php if ($pconfig['localid_type'] == "address") echo "selected=\"selected\"";?>><?=gettext("Address"); ?></option>
											<option value="network" <?php if ($pconfig['localid_type'] == "network") echo "selected=\"selected\"";?>><?=gettext("Network"); ?></option>
											<?php
												$iflist = get_configured_interface_with_descr();
												foreach ($iflist as $ifname => $ifdescr):
											?>
											<option value="<?=$ifname; ?>" <?php if ($pconfig['localid_type'] == $ifname ) echo "selected=\"selected\"";?>><?=sprintf(gettext("%s subnet"), $ifdescr); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<td><?=gettext("Address:");?>&nbsp;&nbsp;</td>
									<td><?=$mandfldhtmlspc;?></td>
									<td>
										<input name="localid_address" type="text" class="formfld unknown ipv4v6" id="localid_address" size="28" value="<?=htmlspecialchars($pconfig['localid_address']);?>" />
										/
										<select name="localid_netbits" class="formselect ipv4v6" id="localid_netbits">
										<?php for ($i = 128; $i >= 0; $i--): ?>
											<option value="<?=$i;?>" <?php if (isset($pconfig['localid_netbits']) && $i == $pconfig['localid_netbits']) echo "selected=\"selected\""; ?>>
												<?=$i;?>
											</option>
										<?php endfor; ?>
										</select>
									</td>
								</tr>
								<tr> <td colspan="3">
								<br />
								<?php echo gettext("In case you need NAT/BINAT on this network specify the address to be translated"); ?>
								</td></tr>
								<tr>
									<td><?=gettext("Type"); ?>:&nbsp;&nbsp;</td>
									<td></td>
									<td>
										<select name="natlocalid_type" class="formselect" onchange="typesel_change_natlocal()">
											<option value="address" <?php if ($pconfig['natlocalid_type'] == "address") echo "selected=\"selected\"";?>><?=gettext("Address"); ?></option>
											<option value="network" <?php if ($pconfig['natlocalid_type'] == "network") echo "selected=\"selected\"";?>><?=gettext("Network"); ?></option>
											<?php
												$iflist = get_configured_interface_with_descr();
												foreach ($iflist as $ifname => $ifdescr):
											?>
											<option value="<?=$ifname; ?>" <?php if ($pconfig['natlocalid_type'] == $ifname ) echo "selected=\"selected\"";?>><?=sprintf(gettext("%s subnet"), $ifdescr); ?></option>
											<?php endforeach; ?>
											<option value="none" <?php if (empty($pconfig['natlocalid_type']) || $pconfig['natlocalid_type'] == "none" ) echo "selected=\"selected\"";?>><?=gettext("None"); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<td><?=gettext("Address:");?>&nbsp;&nbsp;</td>
									<td><?=$mandfldhtmlspc;?></td>
									<td>
										<input name="natlocalid_address" type="text" class="formfld unknown ipv4v6" id="natlocalid_address" size="28" value="<?=htmlspecialchars($pconfig['natlocalid_address']);?>" />
										/
										<select name="natlocalid_netbits" class="formselect ipv4v6" id="natlocalid_netbits">
										<?php for ($i = 128; $i >= 0; $i--): ?>
											<option value="<?=$i;?>" <?php if (isset($pconfig['natlocalid_netbits']) && $i == $pconfig['natlocalid_netbits']) echo "selected=\"selected\""; ?>>
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
							<table border="0" cellspacing="0" cellpadding="0" summary="remote network">
								<tr>
									<td><?=gettext("Type"); ?>:&nbsp;&nbsp;</td>
									<td></td>
									<td>
										<select name="remoteid_type" class="formselect" onchange="typesel_change_remote()">
											<option value="address" <?php if ($pconfig['remoteid_type'] == "address") echo "selected=\"selected\""; ?>><?=gettext("Address"); ?></option>
											<option value="network" <?php if ($pconfig['remoteid_type'] == "network") echo "selected=\"selected\""; ?>><?=gettext("Network"); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<td><?=gettext("Address"); ?>:&nbsp;&nbsp;</td>
									<td><?=$mandfldhtmlspc;?></td>
									<td>
										<input name="remoteid_address" type="text" class="formfld unknown ipv4v6" id="remoteid_address" size="28" value="<?=htmlspecialchars($pconfig['remoteid_address']);?>" />
										/
										<select name="remoteid_netbits" class="formselect ipv4v6" id="remoteid_netbits">
										<?php for ($i = 128; $i >= 0; $i--) { 
											
											echo "<option value=\"{$i}\"";
											if (isset($pconfig['remoteid_netbits']) && $i == $pconfig['remoteid_netbits']) echo " selected=\"selected\"";
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
							<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
							<br />
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
							<select name="proto" class="formselect" onchange="change_protocol()">
							<?php foreach ($p2_protos as $proto => $protoname): ?>
								<option value="<?=$proto;?>" <?php if ($proto == $pconfig['proto']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars($protoname);?>
								</option>
							<?php endforeach; ?>
							</select>
							<br />
							<span class="vexpl">
								<?=gettext("ESP is encryption, AH is authentication only"); ?>
							</span>
						</td>
					</tr>
					<tr id="opt_enc">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Encryption algorithms"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellspacing="0" cellpadding="0" summary="encryption">
							<?php
								foreach ($p2_ealgos as $algo => $algodata):
									$checked = '';
									if (is_array($pconfig['ealgos']) && in_array($algo,$pconfig['ealgos']))
										$checked = " checked=\"checked\"";
								?>
								<tr>
									<td>
										<input type="checkbox" name="ealgos[]" value="<?=$algo;?>"<?=$checked?> />
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
													$selected = "";
				//									if ($checked && in_array("keylen_".$algo,$pconfig))
													if ($keylen == $pconfig["keylen_".$algo])
														$selected = " selected=\"selected\"";
											?>
											<option value="<?=$keylen;?>"<?=$selected;?>><?=$keylen;?> <?=gettext("bits"); ?></option>
											<?php endfor; ?>
										</select>
										<?php endif; ?>
									</td>
								</tr>
								
								<?php endforeach; ?>
								
							</table>
							<br />
							<?=gettext("Hint: use 3DES for best compatibility or if you have a hardware " . 
							"crypto accelerator card. Blowfish is usually the fastest in " .
							"software encryption"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Hash algorithms"); ?></td>
						<td width="78%" class="vtable">
						<?php foreach ($p2_halgos as $algo => $algoname): ?>
							<input type="checkbox" name="halgos[]" value="<?=$algo;?>" <?php if (in_array($algo, $pconfig['halgos'])) echo "checked=\"checked\""; ?> />
							<?=htmlspecialchars($algoname);?>
							<br />
						<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("PFS key group"); ?></td>
						<td width="78%" class="vtable">
						<?php if (!isset($pconfig['mobile']) || !isset($a_client['pfs_group'])): ?>
							<select name="pfsgroup" class="formselect">
							<?php foreach ($p2_pfskeygroups as $keygroup => $keygroupname): ?>
								<option value="<?=$keygroup;?>" <?php if ($keygroup == $pconfig['pfsgroup']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars($keygroupname);?>
								</option>
							<?php endforeach; ?>
							</select>
							<br />
							<?php else: ?>

							<select class="formselect" disabled="disabled">
								<option selected="selected"><?=$p2_pfskeygroups[$a_client['pfs_group']];?></option>
							</select>
							<input name="pfsgroup" type="hidden" value="<?=htmlspecialchars($pconfig['pfsgroup']);?>" />
							<br />
							<span class="vexpl"><em><?=gettext("Set globally in mobile client options"); ?></em></span>
						<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Lifetime"); ?></td>
						<td width="78%" class="vtable">
							<input name="lifetime" type="text" class="formfld unknown" id="lifetime" size="20" value="<?=htmlspecialchars($pconfig['lifetime']);?>" />
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
							<input name="pinghost" type="text" class="formfld unknown" id="pinghost" size="28" value="<?=htmlspecialchars($pconfig['pinghost']);?>" />
							<?=gettext("IP address"); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%">
						<?php if ($pconfig['mobile']): ?>
							<input name="mobile" type="hidden" value="true" />
							<input name="remoteid_type" type="hidden" value="mobile" />
						<?php endif; ?>
							<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
							<input name="ikeid" type="hidden" value="<?=htmlspecialchars($pconfig['ikeid']);?>" />
							<input name="uniqid" type="hidden" value="<?=htmlspecialchars($pconfig['uniqid']);?>" />
						</td>
					</tr>
				</table>
			</div>
		</td>
	</tr>
</table>
</form>
<script type="text/javascript">
//<![CDATA[
change_mode('<?=htmlspecialchars($pconfig['mode'])?>');
change_protocol('<?=htmlspecialchars($pconfig['proto'])?>');
typesel_change_local(<?=htmlspecialchars($pconfig['localid_netbits'])?>);
typesel_change_natlocal(<?=htmlspecialchars($pconfig['natlocalid_netbits'])?>);
<?php if (!isset($pconfig['mobile'])): ?>
typesel_change_remote(<?=htmlspecialchars($pconfig['remoteid_netbits'])?>);
<?php endif; ?>
//]]>
</script>
<?php include("fend.inc"); ?>
</body>
</html>

<?php

/* local utility functions */

function pconfig_to_ealgos(& $pconfig) {
	global $p2_ealgos;

	$ealgos = array();
	if (is_array($pconfig['ealgos'])) {
		foreach ($p2_ealgos as $algo_name => $algo_data) {
			if (in_array($algo_name,$pconfig['ealgos'])) {
				$ealg = array();
				$ealg['name'] = $algo_name;
				if (is_array($algo_data['keysel']))
					$ealg['keylen'] = $_POST["keylen_".$algo_name];
				$ealgos[] = $ealg;
			}
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
