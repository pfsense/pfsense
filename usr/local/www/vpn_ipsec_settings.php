<?php
/*
	vpn_ipsec_settings.php

	Copyright (C) 2015 Electric Sheep Fencing, LLC
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
##|*IDENT=page-vpn-ipsec-settings
##|*NAME=VPN: IPsec: Settings page
##|*DESCR=Allow access to the 'VPN: IPsec: Settings' page.
##|*MATCH=vpn_ipsec_settings.php*
##|-PRIV

require("functions.inc");
require("guiconfig.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

foreach ($ipsec_loglevels as $lkey => $ldescr) {
	if (!empty($config['ipsec']["ipsec_{$lkey}"])) {
		$pconfig["ipsec_{$lkey}"] = $config['ipsec']["ipsec_{$lkey}"];
	}
}
$pconfig['unityplugin'] = isset($config['ipsec']['unityplugin']);
$pconfig['makebeforebreak'] = isset($config['ipsec']['makebeforebreak']);
$pconfig['noshuntlaninterfaces'] = isset($config['ipsec']['noshuntlaninterfaces']);
$pconfig['compression'] = isset($config['ipsec']['compression']);
$pconfig['enableinterfacesuse'] = isset($config['ipsec']['enableinterfacesuse']);
$pconfig['acceptunencryptedmainmode'] = isset($config['ipsec']['acceptunencryptedmainmode']);
$pconfig['maxmss_enable'] = isset($config['system']['maxmss_enable']);
$pconfig['maxmss'] = $config['system']['maxmss'];
$pconfig['uniqueids'] = $config['ipsec']['uniqueids'];

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (!in_array($pconfig['ipsec_dmn'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for Daemon debug.";
	}
	if (!in_array($pconfig['ipsec_mgr'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for SA Manager debug.";
	}
	if (!in_array($pconfig['ipsec_ike'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for IKE SA debug.";
	}
	if (!in_array($pconfig['ipsec_chd'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for IKE Child SA debug.";
	}
	if (!in_array($pconfig['ipsec_job'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for Job Processing debug.";
	}
	if (!in_array($pconfig['ipsec_cfg'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for Configuration backend debug.";
	}
	if (!in_array($pconfig['ipsec_knl'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for Kernel Interface debug.";
	}
	if (!in_array($pconfig['ipsec_net'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for Networking debug.";
	}
	if (!in_array($pconfig['ipsec_asn'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for ASN Encoding debug.";
	}
	if (!in_array($pconfig['ipsec_enc'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for Message encoding debug.";
	}
	if (!in_array($pconfig['ipsec_imc'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for Integrity checker debug.";
	}
	if (!in_array($pconfig['ipsec_imv'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for Integrity Verifier debug.";
	}
	if (!in_array($pconfig['ipsec_pts'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for Platform Trust Service debug.";
	}
	if (!in_array($pconfig['ipsec_tls'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for TLS Handler debug.";
	}
	if (!in_array($pconfig['ipsec_esp'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for IPsec Traffic debug.";
	}
	if (!in_array($pconfig['ipsec_lib'], array('0', '1', '2', '3', '4', '5'), true)) {
		$input_errors[] = "A valid value must be specified for StrongSwan Lib debug.";
	}
	if (isset($pconfig['maxmss'])) {
		if (!is_numericint($pconfig['maxmss']) && $pconfig['maxmss'] <> '') {
			$input_errors[] = "An integer must be specified for Maximum MSS.";
		}
		if ($pconfig['maxmss'] <> '' && $pconfig['maxmss'] < 576 || $pconfig['maxmss'] > 65535) {
			$input_errors[] = "An integer between 576 and 65535 must be specified for Maximum MSS";
		}
	}

	if (!$input_errors) {

		if (is_array($config['ipsec'])) {
			foreach ($ipsec_loglevels as $lkey => $ldescr) {
				if (empty($_POST["ipsec_{$lkey}"])) {
					if (isset($config['ipsec']["ipsec_{$lkey}"])) {
						unset($config['ipsec']["ipsec_{$lkey}"]);
					}
				} else {
					$config['ipsec']["ipsec_{$lkey}"] = $_POST["ipsec_{$lkey}"];
				}
			}
		}

		$needsrestart = false;

		if ($_POST['compression'] == "yes") {
			if (!isset($config['ipsec']['compression'])) {
				$needsrestart = true;
			}
			$config['ipsec']['compression'] = true;
		} elseif (isset($config['ipsec']['compression'])) {
			$needsrestart = true;
			unset($config['ipsec']['compression']);
		}

		if ($_POST['enableinterfacesuse'] == "yes") {
			if (!isset($config['ipsec']['enableinterfacesuse'])) {
				$needsrestart = true;
			}
			$config['ipsec']['enableinterfacesuse'] = true;
		} elseif (isset($config['ipsec']['enableinterfacesuse'])) {
			$needsrestart = true;
			unset($config['ipsec']['enableinterfacesuse']);
		}

		if ($_POST['unityplugin'] == "yes") {
			if (!isset($config['ipsec']['unityplugin'])) {
				$needsrestart = true;
			}
			$config['ipsec']['unityplugin'] = true;
		} elseif (isset($config['ipsec']['unityplugin'])) {
			$needsrestart = true;
			unset($config['ipsec']['unityplugin']);
		}

		if ($_POST['makebeforebreak'] == "yes") {
			$config['ipsec']['makebeforebreak'] = true;
		} elseif (isset($config['ipsec']['makebeforebreak'])) {
			unset($config['ipsec']['makebeforebreak']);
		}

		if ($_POST['noshuntlaninterfaces'] == "yes") {
			$config['ipsec']['noshuntlaninterfaces'] = true;
		} elseif (isset($config['ipsec']['noshuntlaninterfaces'])) {
			unset($config['ipsec']['noshuntlaninterfaces']);
		}

		if ($_POST['acceptunencryptedmainmode'] == "yes") {
			if (!isset($config['ipsec']['acceptunencryptedmainmode'])) {
				$needsrestart = true;
			}
			$config['ipsec']['acceptunencryptedmainmode'] = true;
		} elseif (isset($config['ipsec']['acceptunencryptedmainmode'])) {
			$needsrestart = true;
			unset($config['ipsec']['acceptunencryptedmainmode']);
		}

		if (!empty($_POST['uniqueids'])) {
			$config['ipsec']['uniqueids'] = $_POST['uniqueids'];
		} else {
			unset($config['ipsec']['uniqueids']);
		}

		if ($_POST['maxmss_enable'] == "yes") {
			$config['system']['maxmss_enable'] = true;
			$config['system']['maxmss'] = $_POST['maxmss'];
		} else {
			unset($config['system']['maxmss_enable']);
			unset($config['system']['maxmss']);
		}

		write_config();

		$retval = 0;
		$retval = filter_configure();
		if (stristr($retval, "error") <> true) {
			$savemsg = get_std_save_message(gettext($retval));
		} else {
			$savemsg = gettext($retval);
		}

		vpn_ipsec_configure($needsrestart);
		vpn_ipsec_configure_loglevels();

//		header("Location: vpn_ipsec_settings.php");
//		return;
	}
}

$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Settings"));
$shortcut_section = "ipsec";

include("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[

function maxmss_checked(obj) {
	if (obj.checked) {
		jQuery('#maxmss').attr('disabled',false);
	} else {
		jQuery('#maxmss').attr('disabled','true');
	}
}

//]]>
</script>

<form action="vpn_ipsec_settings.php" method="post" name="iform" id="iform">

<?php
	if ($savemsg) {
		print_info_box($savemsg);
	}
	if ($input_errors) {
		print_input_errors($input_errors);
	}
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="vpn ipsec settings">
	<tr>
		<td class="tabnavtbl">
			<?php
				$tab_array = array();
				$tab_array[0] = array(gettext("Tunnels"), false, "vpn_ipsec.php");
				$tab_array[1] = array(gettext("Mobile clients"), false, "vpn_ipsec_mobile.php");
				$tab_array[2] = array(gettext("Pre-Shared Key"), false, "vpn_ipsec_keys.php");
				$tab_array[3] = array(gettext("Advanced Settings"), true, "vpn_ipsec_settings.php");
				display_top_tabs($tab_array);
			?>
		</td>
	</tr>
	<tr>
		<td id="mainarea">
			<div class="tabcont">
				<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("IPsec Advanced Settings"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("IPsec Debug"); ?></td>
						<td width="78%" class="vtable">
							<strong><?=gettext("Start IPsec in debug mode based on sections selected"); ?></strong>
							<br />
							<table summary="ipsec debug">
							<?php foreach ($ipsec_loglevels as $lkey => $ldescr): ?>
								<tr>
									<td width="22%" valign="top" class="vncell"><?=$ldescr;?></td>
									<td width="78%" valign="top" class="vncell">
										<?php
										echo "<select name=\"ipsec_{$lkey}\" id=\"ipsec_{$lkey}\">\n";
										foreach (array("Silent", "Audit", "Control", "Diag", "Raw", "Highest") as $lidx => $lvalue) {
											echo "<option value=\"{$lidx}\" ";
											if ($pconfig["ipsec_{$lkey}"] == $lidx)
												echo "selected=\"selected\"";
											echo ">{$lvalue}</option>\n";
										}
										?>
										</select>
									</td>
								</tr>
							<?php endforeach; ?>
								<tr style="display:none;">
									<td></td>
								</tr>
							</table>
							<br /><?=gettext("Launches IPsec in debug mode so that more verbose logs " .
							"will be generated to aid in troubleshooting."); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Unique IDs"); ?></td>
						<td width="78%" class="vtable">
							<strong><?=gettext("Configure Unique IDs as: "); ?></strong>
							<?php
								echo "<select name=\"uniqueids\" id=\"uniqueids\">\n";
								foreach ($ipsec_idhandling as $value => $lvalue) {
									echo "<option value=\"{$value}\" ";
									if ($pconfig['uniqueids'] == $value) {
										echo "selected=\"selected\"";
									}
									echo ">{$lvalue}</option>\n";
								}
							?>
							</select>
							<br />
							<?=gettext("whether a particular participant ID should be kept unique, with any new IKE_SA using an ID " .
								"deemed to replace all old ones using that ID. Participant IDs normally are unique, so a new " .
								"IKE_SA using the same ID is almost invariably intended to replace an old one. " .
								"The difference between <b>no</b> and <b>never</b> is that the old IKE_SAs will be replaced when receiving an " .
								"INITIAL_CONTACT notify if the option is no but will ignore these notifies if <b>never</b> is configured. " .
								"The daemon also accepts the value <b>keep</b> to reject " .
								"new IKE_SA setups and keep the duplicate established earlier. Defaults to Yes."); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("IP Compression"); ?></td>
						<td width="78%" class="vtable">
							<input name="compression" type="checkbox" id="compression" value="yes" <?php if ($pconfig['compression']) echo "checked=\"checked\""; ?> />
							<strong><?=gettext("Enable IPCompression"); ?></strong>
							<br />
							<?=gettext("IPComp compression of content is proposed on the connection."); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Strict interface binding"); ?></td>
						<td width="78%" class="vtable">
							<input name="enableinterfacesuse" type="checkbox" id="enableinterfacesuse" value="yes" <?php if ($pconfig['enableinterfacesuse']) echo "checked=\"checked\""; ?> />
							<strong><?=gettext("Enable strict interface binding"); ?></strong>
							<br />
							<?=gettext("Enable strongSwan's interfaces_use option to bind specific interfaces only. This option is known to break IPsec with dynamic IP interfaces. This is not recommended at this time."); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Unencrypted payloads in IKEv1 Main Mode"); ?></td>
						<td width="78%" class="vtable">
							<input name="acceptunencryptedmainmode" type="checkbox" id="acceptunencryptedmainmode" value="yes" <?php if ($pconfig['acceptunencryptedmainmode']) echo "checked=\"checked\""; ?> />
							<strong><?=gettext("Accept unencrypted ID and HASH payloads in IKEv1 Main Mode"); ?></strong>
							<br />
							<?=gettext("Some implementations send the third Main Mode message unencrypted, probably to find the PSKs for the specified ID for authentication." .
							"This is very similar to Aggressive Mode, and has the same security implications: " .
							"A passive attacker can sniff the negotiated Identity, and start brute forcing the PSK using the HASH payload." .
							" It is recommended to keep this option to no, unless you know exactly what the implications are and require compatibility to such devices (for example, some SonicWall boxes).");?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Maximum MSS"); ?></td>
						<td width="78%" class="vtable">
							<input name="maxmss_enable" type="checkbox" id="maxmss_enable" value="yes" <?php if ($pconfig['maxmss_enable'] == true) echo "checked=\"checked\""; ?> onclick="maxmss_checked(this)" />
							<strong><?=gettext("Enable MSS clamping on VPN traffic"); ?></strong>
							<br />
							<input name="maxmss" id="maxmss" value="<?php if ($pconfig['maxmss'] <> "") echo $pconfig['maxmss']; else "1400"; ?>" class="formfld unknown" <?php if ($pconfig['maxmss_enable'] == false) echo "disabled=\"disabled\""; ?> />
							<br />
							<?=gettext("Enable MSS clamping on TCP flows over VPN. " .
							"This helps overcome problems with PMTUD on IPsec VPN links. If left blank, the default value is 1400 bytes. "); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Disable Cisco Extensions"); ?></td>
						<td width="78%" class="vtable">
							<input name="unityplugin" type="checkbox" id="unityplugin" value="yes" <?php if ($pconfig['unityplugin'] == true) echo "checked=\"checked\""; ?> />
							<strong><?=gettext("Disable Unity Plugin"); ?></strong>
							<br />
							<?=gettext("Disable Unity Plugin which provides Cisco Extension support as Split-Include, Split-Exclude, Split-Dns, ..."); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Make before Break"); ?></td>
						<td width="78%" class="vtable">
							<input name="makebeforebreak" type="checkbox" id="makebeforebreak" value="yes" <?php if ($pconfig['makebeforebreak'] == true) echo "checked=\"checked\""; ?> />
							<strong><?=gettext("Initiate IKEv2 reauthentication with a make-before-break"); ?></strong>
							<br />
							<?=gettext("instead of a break-before-make scheme. Make-before-break uses overlapping IKE and CHILD_SA during reauthentication " .
								"by first recreating all new SAs before deleting the old ones. This behavior can be beneficial to avoid connectivity gaps " .
								"during reauthentication, but requires support for overlapping SAs by the peer.");?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Auto-exclude LAN address"); ?></td>
						<td width="78%" class="vtable">
							<input name="noshuntlaninterfaces" type="checkbox" id="noshuntlaninterfaces" value="yes" <?php if ($pconfig['noshuntlaninterfaces'] != true) echo "checked=\"checked\""; ?> />
							<strong><?=gettext("Enable bypass for LAN interface IP"); ?></strong>
							<br />
							<?=gettext("Exclude traffic from LAN subnet to LAN IP address from IPsec."); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%">
							<input name="submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
						</td>
					</tr>
				</table>
			</div>
		</td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
