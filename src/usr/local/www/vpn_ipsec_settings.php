<?php
/*
	vpn_ipsec_settings.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-vpn-ipsec-settings
##|*NAME=VPN: IPsec: Settings
##|*DESCR=Allow access to the 'VPN: IPsec: Settings' page.
##|*MATCH=vpn_ipsec_settings.php*
##|-PRIV

require("functions.inc");
require("guiconfig.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

$def_loglevel = '1';

foreach (array_keys($ipsec_log_cats) as $cat) {
	if (isset($config['ipsec']['logging'][$cat])) {
		$pconfig[$cat] = $config['ipsec']['logging'][$cat];
	} else {
		$pconfig[$cat] = $def_loglevel;
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

	foreach ($ipsec_log_cats as $cat => $desc) {
		if (!in_array(intval($pconfig[$cat]), array_keys($ipsec_log_sevs), true)) {
			$input_errors[] = "A valid value must be specified for {$desc} debug.";
		}
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

		/* log levels aren't set initially and use default. They all
		 * get set when we save, even if it's to the default level.
		 */
		foreach (array_keys($ipsec_log_cats) as $cat) {
			if (!isset($pconfig[$cat])) {
				continue;
			}
			if ($pconfig[$cat] != $config['ipsec']['logging'][$cat]) {
				$config['ipsec']['logging'][$cat] = $pconfig[$cat];
				vpn_update_daemon_loglevel($cat, $pconfig[$cat]);
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

		// The UI deals with "Auto-exclude LAN address" but in the back-end we work with
		// noshuntlaninterfaces which is the reverse true/false logic setting - #4655
		if ($_POST['autoexcludelanaddress'] == "yes") {
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

		header("Location: vpn_ipsec_settings.php");
		return;
	}

	// The logic value sent by $POST for autoexcludelanaddress is opposite to
	// the way it is stored in the config as noshuntlaninterfaces.
	// Reset the $pconfig value so it reflects the opposite of what was $POSTed.
	// This helps a redrawn UI page after Save to correctly display the most recently entered setting.
	if ($_POST['autoexcludelanaddress'] == "yes") {
		$pconfig['noshuntlaninterfaces'] = false;
	} else {
		$pconfig['noshuntlaninterfaces'] = true;
	}
}

$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Advanced Settings"));
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

$tab_array = array();
$tab_array[0] = array(gettext("Tunnels"), false, "vpn_ipsec.php");
$tab_array[1] = array(gettext("Mobile Clients"), false, "vpn_ipsec_mobile.php");
$tab_array[2] = array(gettext("Pre-Shared Keys"), false, "vpn_ipsec_keys.php");
$tab_array[3] = array(gettext("Advanced Settings"), true, "vpn_ipsec_settings.php");
display_top_tabs($tab_array);

$form = new Form;

$section = new Form_Section('Start IPsec in debug mode based on sections selected');

foreach ($ipsec_log_cats as $cat => $desc) {
	$section->addInput(new Form_Select(
		$cat,
		$desc,
		$pconfig[$cat],
		$ipsec_log_sevs
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

if (!empty($pconfig['maxmss_enable'])) {
	$group->addClass('in');
}

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
	'strictcrlpolicy',
	'Strict CRL Checking',
	'Enable strict Certificate Revocation List checking',
	$pconfig['strictcrlpolicy']
))->setHelp('Check this to require availability of a fresh CRL for peer authentication based on RSA signatures to succeed.');

$section->addInput(new Form_Checkbox(
	'makebeforebreak',
	'Make before Break',
	'Initiate IKEv2 reauthentication with a make-before-break',
	$pconfig['makebeforebreak']
))->setHelp('instead of a break-before-make scheme. Make-before-break uses overlapping IKE and CHILD_SA during reauthentication ' .
			'by first recreating all new SAs before deleting the old ones. This behavior can be beneficial to avoid connectivity gaps ' .
			'during reauthentication, but requires support for overlapping SAs by the peer');

$section->addInput(new Form_Checkbox(
	'autoexcludelanaddress',
	'Auto-exclude LAN address',
	'Enable bypass for LAN interface IP',
	!$pconfig['noshuntlaninterfaces']
))->setHelp('Exclude traffic from LAN subnet to LAN IP address from IPsec.');

$form->add($section);

print $form;

?>

<?php include("foot.inc"); ?>
