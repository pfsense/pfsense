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
$pconfig['strictcrlpolicy'] = isset($config['ipsec']['strictcrlpolicy']);
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
		if (!is_numericint($pconfig['maxmss']) && $pconfig['maxmss'] != '') {
			$input_errors[] = "An integer must be specified for Maximum MSS.";
		}
		if ($pconfig['maxmss'] <> '' && $pconfig['maxmss'] < 576 || $pconfig['maxmss'] > 65535) {
			$input_errors[] = "An integer between 576 and 65535 must be specified for Maximum MSS";
		}
	}

	if (!$input_errors) {

		foreach ($ipsec_loglevels as $lkey => $ldescr) {
			if (empty($_POST["ipsec_{$lkey}"])) {
				if (isset($config['ipsec']["ipsec_{$lkey}"])) {
					unset($config['ipsec']["ipsec_{$lkey}"]);
				}
			} else {
				$config['ipsec']["ipsec_{$lkey}"] = $_POST["ipsec_{$lkey}"];
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
		
		if ($_POST['strictcrlpolicy'] == "yes") {
			$config['ipsec']['strictcrlpolicy'] = true;
		} elseif (isset($config['ipsec']['strictcrlpolicy'])) {
			unset($config['ipsec']['strictcrlpolicy']);
		}

		if ($_POST['makebeforebreak'] == "yes") {
			$config['ipsec']['makebeforebreak'] = true;
		} elseif (isset($config['ipsec']['makebeforebreak'])) {
			unset($config['ipsec']['makebeforebreak']);
		}

		if ($_POST['noshuntlaninterfaces'] == "yes") {
			if (isset($config['ipsec']['noshuntlaninterfaces'])) {
				unset($config['ipsec']['noshuntlaninterfaces']);
			}
		} else {
			$config['ipsec']['noshuntlaninterfaces'] = true;
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
		} else if (isset($config['ipsec']['uniqueids'])) {
			unset($config['ipsec']['uniqueids']);
		}

		if ($_POST['maxmss_enable'] == "yes") {
			$config['system']['maxmss_enable'] = true;
			$config['system']['maxmss'] = $_POST['maxmss'];
		} else {
			if (isset($config['system']['maxmss_enable'])) {
				unset($config['system']['maxmss_enable']);
			}
			if (isset($config['system']['maxmss'])) {
				unset($config['system']['maxmss']);
			}
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

		header("Location: vpn_ipsec_settings.php");
		return;
	}

	// The logic value sent by $POST is opposite to the way it is stored in the config.
	// Reset the $pconfig value so it reflects the opposite of what was $POSTed.
	if ($_POST['noshuntlaninterfaces'] == "yes") {
		$pconfig['noshuntlaninterfaces'] = false;
	} else {
		$pconfig['noshuntlaninterfaces'] = true;
	}
}

$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Settings"));
$shortcut_section = "ipsec";

include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[

function maxmss_checked(obj) {
	if (obj.checked) {
		jQuery('#maxmss').attr('disabled', false);
	} else {
		jQuery('#maxmss').attr('disabled', 'true');
	}
}

//]]>
</script>

<?php
	if ($savemsg) {
		print_info_box($savemsg);
	}
	if ($input_errors) {
		print_input_errors($input_errors);
	}
?>

<?php

$tab_array = array();
$tab_array[0] = array(gettext("Tunnels"), false, "vpn_ipsec.php");
$tab_array[1] = array(gettext("Mobile clients"), false, "vpn_ipsec_mobile.php");
$tab_array[2] = array(gettext("Pre-Shared Key"), false, "vpn_ipsec_keys.php");
$tab_array[3] = array(gettext("Advanced Settings"), true, "vpn_ipsec_settings.php");
display_top_tabs($tab_array);

require_once('classes/Form.class.php');
$form = new Form;

$section = new Form_Section('Start IPsec in debug mode based on sections selected');

foreach ($ipsec_loglevels as $lkey => $ldescr)
{
	$section->addInput(new Form_Select(
		'ipsec_' . $lkey,
		$ldescr,
		$pconfig['ipsec_' . $lkey],
		array('Silent', 'Audit', 'Control', 'Diag', 'Raw', 'Highest')
	))->setWidth(2);
}

$section->addInput(new Form_StaticText('', ''))->setHelp(
	'Launches IPsec in debug mode so that more verbose logs will be generated to aid in troubleshooting.'
);

$form->add($section);

$section = new Form_Section('IPsec Advanced Settings');

$section->addInput(new Form_Select(
	'uniqueids',
	'Configure Unique IDs as',
	$pconfig['uniqueids'],
	$ipsec_idhandling
))->setHelp(
	'Whether a particular participant ID should be kept unique, with any new IKE_SA using an ID ' .
	'deemed to replace all old ones using that ID. Participant IDs normally are unique, so a new ' .
	'IKE_SA using the same ID is almost invariably intended to replace an old one. ' .
	'The difference between <b>no</b> and <b>never</b> is that the old IKE_SAs will be replaced when receiving an ' .
	'INITIAL_CONTACT notify if the option is no but will ignore these notifies if <b>never</b> is configured. ' .
	'The daemon also accepts the value <b>keep</b> to reject ' .
	'new IKE_SA setups and keep the duplicate established earlier. Defaults to Yes.'
);

$section->addInput(new Form_Checkbox(
	'compression',
	'IP Compression',
	'Enable IPCompression',
	$pconfig['compression']
))->setHelp('IPComp compression of content is proposed on the connection.');

$section->addInput(new Form_Checkbox(
	'enableinterfacesuse',
	'Strict interface binding',
	'Enable strict interface binding',
	$pconfig['enableinterfacesuse']
))->setHelp('Enable strongSwan\'s interfaces_use option to bind specific interfaces only. This option is known to break IPsec with dynamic IP interfaces. This is not recommended at this time.');

$section->addInput(new Form_Checkbox(
	'acceptunencryptedmainmode',
	'Unencrypted payloads in IKEv1 Main Mode',
	'Accept unencrypted ID and HASH payloads in IKEv1 Main Mode',
	$pconfig['acceptunencryptedmainmode']
))->setHelp(
	'Some implementations send the third Main Mode message unencrypted, probably to find the PSKs for the specified ID for authentication.' .
	'This is very similar to Aggressive Mode, and has the same security implications: ' .
	'A passive attacker can sniff the negotiated Identity, and start brute forcing the PSK using the HASH payload.' .
	'It is recommended to keep this option to no, unless you know exactly what the implications are and require compatibility to such devices (for example, some SonicWall boxes).'
);

$section->addInput(new Form_Checkbox(
	'maxmss_enable',
	'Enable Maximum MSS',
	'Enable MSS clamping on VPN traffic',
	$pconfig['maxmss_enable']
))->toggles('.toggle-maxmss', 'collapse');

$group = new Form_Group('Maximum MSS');
$group->addClass('toggle-maxmss collapse');

if (!empty($pconfig['maxmss_enable']))
	$group->addClass('in');

$group->add(new Form_Input(
	'maxmss',
	'Maximum MSS',
	'text',
	($pconfig['maxmss'] ? $pconfig['maxmss'] : '1400')
))->setHelp(
	'Enable MSS clamping on TCP flows over VPN. ' .
	'This helps overcome problems with PMTUD on IPsec VPN links. If left blank, the default value is 1400 bytes. '
);

$section->add($group);

$section->addInput(new Form_Checkbox(
	'unityplugin',
	'Disable Cisco Extensions',
	'Disable Unity Plugin',
	$pconfig['unityplugin']
))->setHelp('Disable Unity Plugin which provides Cisco Extension support as Split-Include, Split-Exclude, Split-Dns, ...');

$section->addInput(new Form_Checkbox(
	'shuntlaninterfaces',
	'Bypass LAN address',
	'Enable bypass for LAN interface ip',
	$pconfig['shuntlaninterfaces']
))->setHelp('Prevent LAN ip address to be processed for IPsec traffic.');

$form->add($section);

print $form;

?>

<?php include("foot.inc"); ?>