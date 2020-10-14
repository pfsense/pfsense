<?php
/*
 * diag_backup.php
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
##|*IDENT=page-diagnostics-backup-restore
##|*NAME=Diagnostics: Backup & Restore
##|*DESCR=Allow access to the 'Diagnostics: Backup & Restore' page.
##|*WARN=standard-warning-root
##|*MATCH=diag_backup.php*
##|-PRIV

/* Allow additional execution time 0 = no limit. */
ini_set('max_execution_time', '0');
ini_set('max_input_time', '0');

/* omit no-cache headers because it confuses IE with file downloads */
$omit_nocacheheaders = true;
require_once("guiconfig.inc");
require_once("backup.inc");

$rrddbpath = "/var/db/rrd";
$rrdtool = "/usr/bin/nice -n20 /usr/local/bin/rrdtool";

if ($_POST['apply']) {
	ob_flush();
	flush();
	clear_subsystem_dirty("restore");
	exit;
}

if ($_POST) {
	if ($_POST['reinstallpackages']) {
		header("Location: pkg_mgr_install.php?mode=reinstallall");
		exit;
	} else if ($_POST['clearpackagelock']) {
		clear_subsystem_dirty('packagelock');
		$savemsg = "Package lock cleared.";
	} else {
		$execpost_return = execPost($_POST, $_FILES);
		$input_errors = $execpost_return['input_errors'];
		$savemsg = $execpost_return['savemsg'];
	}

}

$id = rand() . '.' . time();

$mth = ini_get('upload_progress_meter.store_method');
$dir = ini_get('upload_progress_meter.file.filename_template');

function build_area_list($showall) {
	global $config;

	$areas = array(
		"aliases" => gettext("Aliases"),
		"captiveportal" => gettext("Captive Portal"),
		"voucher" => gettext("Captive Portal Vouchers"),
		"dnsmasq" => gettext("DNS Forwarder"),
		"unbound" => gettext("DNS Resolver"),
		"dhcpd" => gettext("DHCP Server"),
		"dhcpdv6" => gettext("DHCPv6 Server"),
		"dyndnses" => gettext("Dynamic DNS"),
		"filter" => gettext("Firewall Rules"),
		"interfaces" => gettext("Interfaces"),
		"ipsec" => gettext("IPSEC"),
		"dnshaper" => gettext("Limiters"),
		"nat" => gettext("NAT"),
		"openvpn" => gettext("OpenVPN"),
		"installedpackages" => gettext("Package Manager"),
		"rrddata" => gettext("RRD Data"),
		"cron" => gettext("Scheduled Tasks"),
		"syslog" => gettext("Syslog"),
		"system" => gettext("System"),
		"staticroutes" => gettext("Static routes"),
		"sysctl" => gettext("System tunables"),
		"snmpd" => gettext("SNMP Server"),
		"shaper" => gettext("Traffic Shaper"),
		"vlans" => gettext("VLANS"),
		"wol" => gettext("Wake-on-LAN")
		);

	$list = array("" => gettext("All"));

	if ($showall) {
		return($list + $areas);
	} else {
		foreach ($areas as $area => $areaname) {
			if ($area === "rrddata" || check_and_returnif_section_exists($area) == true) {
				$list[$area] = $areaname;
			}
		}

		return($list);
	}
}

$pgtitle = array(gettext("Diagnostics"), htmlspecialchars(gettext("Backup & Restore")), htmlspecialchars(gettext("Backup & Restore")));
$pglinks = array("", "@self", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('restore')):
?>
	<br/>
	<form action="diag_reboot.php" method="post">
		<input name="Submit" type="hidden" value="Yes" />
		<?php print_info_box(gettext("The firewall configuration has been changed.") . "<br />" . gettext("The firewall is now rebooting.")); ?>
		<br />
	</form>
<?php
endif;

$tab_array = array();
$tab_array[] = array(htmlspecialchars(gettext("Backup & Restore")), true, "diag_backup.php");
$tab_array[] = array(gettext("Config History"), false, "diag_confbak.php");
display_top_tabs($tab_array);

$form = new Form(false);
$form->setMultipartEncoding();	// Allow file uploads

$section = new Form_Section('Backup Configuration');

$section->addInput(new Form_Select(
	'backuparea',
	'Backup area',
	'',
	build_area_list(false)
));

$section->addInput(new Form_Checkbox(
	'nopackages',
	'Skip packages',
	'Do not backup package information.',
	false
));

$section->addInput(new Form_Checkbox(
	'donotbackuprrd',
	'Skip RRD data',
	'Do not backup RRD data (NOTE: RRD Data can consume 4+ megabytes of config.xml space!)',
	true
));

$section->addInput(new Form_Checkbox(
	'backupdata',
	'Include extra data',
	'Backup extra data.',
	true
))->setHelp('Backup extra data files for some services.%1$s' .
	    '%2$s%3$sCaptive Portal - Captive Portal DB and UsedMACs DB%4$s' .
	    '%3$sCaptive Portal Vouchers - Used Vouchers DB%4$s' .
	    '%3$sDHCP Server - DHCP leases DB%4$s' .
	    '%3$sDHCPv6 Server - DHCPv6 leases DB%4$s%5$s',
	    '<div class="infoblock">', '<ul>', '<li>', '</li>', '</ul></div>'
);

$section->addInput(new Form_Checkbox(
	'encrypt',
	'Encryption',
	'Encrypt this configuration file.',
	false
));

$section->addPassword(new Form_Input(
	'encrypt_password',
	'Password',
	'password',
	null
));

$group = new Form_Group('');
// Note: ID attribute of each element created is to be unique.  Not being used, suppressing it.
$group->add(new Form_Button(
	'download',
	'Download configuration as XML',
	null,
	'fa-download'
))->setAttribute('id')->addClass('btn-primary');

$section->add($group);
$form->add($section);

$section = new Form_Section('Restore Backup');

$section->addInput(new Form_StaticText(
	null,
	sprintf(gettext("Open a %s configuration XML file and click the button below to restore the configuration."), $g['product_name'])
));

$section->addInput(new Form_Select(
	'restorearea',
	'Restore area',
	'',
	build_area_list(true)
));

$section->addInput(new Form_Input(
	'conffile',
	'Configuration file',
	'file',
	null
));

$section->addInput(new Form_Checkbox(
	'decrypt',
	'Encryption',
	'Configuration file is encrypted.',
	false
));

$section->addInput(new Form_Input(
	'decrypt_password',
	'Password',
	'password',
	null,
	['placeholder' => 'Password']
));

$group = new Form_Group('');
// Note: ID attribute of each element created is to be unique.  Not being used, suppressing it.
$group->add(new Form_Button(
	'restore',
	'Restore Configuration',
	null,
	'fa-undo'
))->setHelp('The firewall will reboot after restoring the configuration.')->addClass('btn-danger restore')->setAttribute('id');

$section->add($group);

$form->add($section);

if (($config['installedpackages']['package'] != "") || (is_subsystem_dirty("packagelock"))) {
	$section = new Form_Section('Package Functions');

	if ($config['installedpackages']['package'] != "") {
		$group = new Form_Group('');
		// Note: ID attribute of each element created is to be unique.  Not being used, suppressing it.
		$group->add(new Form_Button(
			'reinstallpackages',
			'Reinstall Packages',
			null,
			'fa-retweet'
		))->setHelp('Click this button to reinstall all system packages.  This may take a while.')->addClass('btn-success')->setAttribute('id');

		$section->add($group);
	}

	if (is_subsystem_dirty("packagelock")) {
		$group = new Form_Group('');
		// Note: ID attribute of each element created is to be unique.  Not being used, suppressing it.
		$group->add(new Form_Button(
			'clearpackagelock',
			'Clear Package Lock',
			null,
			'fa-wrench'
		))->setHelp('Click this button to clear the package lock if a package fails to reinstall properly after an upgrade.')->addClass('btn-warning')->setAttribute('id');

		$section->add($group);
	}

	$form->add($section);
}

print($form);
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// ------- Show/hide sections based on checkbox settings --------------------------------------

	function hideSections(hide) {
		hidePasswords();
	}

	function hidePasswords() {

		encryptHide = !($('input[name="encrypt"]').is(':checked'));
		decryptHide = !($('input[name="decrypt"]').is(':checked'));

		hideInput('encrypt_password', encryptHide);
		hideInput('decrypt_password', decryptHide);
	}

	// ---------- Click handlers ------------------------------------------------------------------

	$('input[name="encrypt"]').on('change', function() {
		hidePasswords();
	});

	$('input[name="decrypt"]').on('change', function() {
		hidePasswords();
	});

	$('#conffile').change(function () {
		if (document.getElementById("conffile").value) {
			$('.restore').prop('disabled', false);
		} else {
			$('.restore').prop('disabled', true);
		}
	});

	$('#backuparea').change(function () {
		if (document.getElementById("backuparea").value == 0) {
			disableInput('donotbackuprrd', false);
			disableInput('nopackages', false);
			disableInput('backupdata', false);
		} else {
			disableInput('donotbackuprrd', true);
			disableInput('nopackages', true);
			disableInput('backupdata', true);
			if (['captiveportal', 'dhcpd', 'dhcpdv6', 'voucher'].includes(document.getElementById("backuparea").value)) {
				disableInput('backupdata', false);
			}
		}
	});

	// ---------- On initial page load ------------------------------------------------------------

	hideSections();
	$('.restore').prop('disabled', true);
});
//]]>
</script>

<?php
include("foot.inc");

if (is_subsystem_dirty('restore')) {
	system_reboot();
}
