<?php
/*
 * services_ntpd.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2013 Dagorlad
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
##|*IDENT=page-services-ntpd
##|*NAME=Services: NTP Settings
##|*DESCR=Allow access to the 'Services: NTP Settings' page.
##|*MATCH=services_ntpd.php*
##|-PRIV

define('NUMTIMESERVERS', 10);		// The maximum number of configurable time servers
require_once("guiconfig.inc");
require_once('rrd.inc');
require_once("shaper.inc");

if (!is_array($config['ntpd'])) {
	$config['ntpd'] = array();
}

if (empty($config['ntpd']['interface'])) {
	if (is_array($config['installedpackages']['openntpd']) && is_array($config['installedpackages']['openntpd']['config']) &&
	    is_array($config['installedpackages']['openntpd']['config'][0]) && !empty($config['installedpackages']['openntpd']['config'][0]['interface'])) {
		$pconfig['interface'] = explode(",", $config['installedpackages']['openntpd']['config'][0]['interface']);
		unset($config['installedpackages']['openntpd']);
		write_config(gettext("Upgraded settings from openttpd"));
	} else {
		$pconfig['interface'] = array();
	}
} else {
	$pconfig['interface'] = explode(",", $config['ntpd']['interface']);
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (!$input_errors) {
		if (is_array($_POST['interface'])) {
			$config['ntpd']['interface'] = implode(",", $_POST['interface']);
		} elseif (isset($config['ntpd']['interface'])) {
			unset($config['ntpd']['interface']);
		}

		if (!empty($_POST['gpsport']) && file_exists('/dev/'.$_POST['gpsport'])) {
			$config['ntpd']['gpsport'] = $_POST['gpsport'];
		} elseif (isset($config['ntpd']['gpsport'])) {
			unset($config['ntpd']['gpsport']);
		}

		unset($config['ntpd']['prefer']);
		unset($config['ntpd']['noselect']);
		$timeservers = '';

		for ($i = 0; $i < NUMTIMESERVERS; $i++) {
			$tserver = trim($_POST["server{$i}"]);
			if (!empty($tserver)) {
				$timeservers .= "{$tserver} ";
				if (!empty($_POST["servprefer{$i}"])) {
					$config['ntpd']['prefer'] .= "{$tserver} ";
				}
				if (!empty($_POST["servselect{$i}"])) {
					$config['ntpd']['noselect'] .= "{$tserver} ";
				}
			}
		}
		if (trim($timeservers) == "") {
			$timeservers = "pool.ntp.org";
		}
		$config['system']['timeservers'] = trim($timeservers);

		if (!empty($_POST['ntporphan']) && ($_POST['ntporphan'] < 17) && ($_POST['ntporphan'] != '12')) {
			$config['ntpd']['orphan'] = $_POST['ntporphan'];
		} elseif (isset($config['ntpd']['orphan'])) {
			unset($config['ntpd']['orphan']);
		}

		if (!empty($_POST['logpeer'])) {
			$config['ntpd']['logpeer'] = $_POST['logpeer'];
		} elseif (isset($config['ntpd']['logpeer'])) {
			unset($config['ntpd']['logpeer']);
		}

		if (!empty($_POST['logsys'])) {
			$config['ntpd']['logsys'] = $_POST['logsys'];
		} elseif (isset($config['ntpd']['logsys'])) {
			unset($config['ntpd']['logsys']);
		}

		if (!empty($_POST['clockstats'])) {
			$config['ntpd']['clockstats'] = $_POST['clockstats'];
		} elseif (isset($config['ntpd']['clockstats'])) {
			unset($config['ntpd']['clockstats']);
		}

		if (!empty($_POST['loopstats'])) {
			$config['ntpd']['loopstats'] = $_POST['loopstats'];
		} elseif (isset($config['ntpd']['loopstats'])) {
			unset($config['ntpd']['loopstats']);
		}

		if (!empty($_POST['peerstats'])) {
			$config['ntpd']['peerstats'] = $_POST['peerstats'];
		} elseif (isset($config['ntpd']['peerstats'])) {
			unset($config['ntpd']['peerstats']);
		}

		if ((empty($_POST['statsgraph'])) == (isset($config['ntpd']['statsgraph']))) {
			$enable_rrd_graphing = true;
		}
		if (!empty($_POST['statsgraph'])) {
			$config['ntpd']['statsgraph'] = $_POST['statsgraph'];
		} elseif (isset($config['ntpd']['statsgraph'])) {
			unset($config['ntpd']['statsgraph']);
		}
		if (isset($enable_rrd_graphing)) {
			enable_rrd_graphing();
		}

		if (!empty($_POST['leaptext'])) {
			$config['ntpd']['leapsec'] = base64_encode($_POST['leaptext']);
		} elseif (isset($config['ntpd']['leapsec'])) {
			unset($config['ntpd']['leapsec']);
		}

		if (is_uploaded_file($_FILES['leapfile']['tmp_name'])) {
			$config['ntpd']['leapsec'] = base64_encode(file_get_contents($_FILES['leapfile']['tmp_name']));
		}

		write_config("Updated NTP Server Settings");

		$retval = 0;
		$retval = system_ntp_configure();
		$savemsg = get_std_save_message($retval);
	}
}

function build_interface_list() {
	global $pconfig;

	$iflist = array('options' => array(), 'selected' => array());

	$interfaces = get_configured_interface_with_descr();
	foreach ($interfaces as $iface => $ifacename) {
		if (!is_ipaddr(get_interface_ip($iface)) &&
		    !is_ipaddrv6(get_interface_ipv6($iface))) {
			continue;
		}

		$iflist['options'][$iface] = $ifacename;

		if (in_array($iface, $pconfig['interface'])) {
			array_push($iflist['selected'], $iface);
		}
	}

	return($iflist);
}

$pconfig = &$config['ntpd'];
if (empty($pconfig['interface'])) {
	$pconfig['interface'] = array();
} else {
	$pconfig['interface'] = explode(",", $pconfig['interface']);
}
$pgtitle = array(gettext("Services"), gettext("NTP"), gettext("Settings"));
$shortcut_section = "ntp";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Settings"), true, "services_ntpd.php");
$tab_array[] = array(gettext("ACLs"), false, "services_ntpd_acls.php");
$tab_array[] = array(gettext("Serial GPS"), false, "services_ntpd_gps.php");
$tab_array[] = array(gettext("PPS"), false, "services_ntpd_pps.php");
display_top_tabs($tab_array);

$form = new Form;
$form->setMultipartEncoding();	// Allow file uploads

$section = new Form_Section('NTP Server Configuration');

$iflist = build_interface_list();

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$iflist['selected'],
	$iflist['options'],
	true
))->setHelp('Interfaces without an IP address will not be shown.' . '<br />' .
			'Selecting no interfaces will listen on all interfaces with a wildcard.' . '<br />' .
			'Selecting all interfaces will explicitly listen on only the interfaces/IPs specified.');

$timeservers = explode(' ', $config['system']['timeservers']);
$maxrows = max(count($timeservers), 1);
for ($counter=0; $counter < $maxrows; $counter++) {
	$group = new Form_Group($counter == 0 ? 'Time Servers':'');
	$group->addClass('repeatable');

	$group->add(new Form_Input(
		'server' . $counter,
		null,
		'text',
		$timeservers[$counter],
		['placeholder' => 'Hostname']
	 ))->setWidth(3);

	 $group->add(new Form_Checkbox(
		'servprefer' . $counter,
		null,
		null,
		isset($config['ntpd']['prefer']) && isset($timeservers[$counter]) && substr_count($config['ntpd']['prefer'], $timeservers[$counter])
	 ))->sethelp('Prefer');

	 $group->add(new Form_Checkbox(
		'servselect' . $counter,
		null,
		null,
		isset($config['ntpd']['noselect']) && isset($timeservers[$counter]) && substr_count($config['ntpd']['noselect'], $timeservers[$counter])
	 ))->sethelp('No Select');

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-trash'
	))->addClass('btn-warning');

	 $section->add($group);
}

$section->addInput(new Form_Button(
	'addrow',
	'Add',
	null,
	'fa-plus'
))->addClass('btn-success');

$section->addInput(new Form_StaticText(
	null,
	$btnaddrow
))->setHelp('For best results three to five servers should be configured here.' . '<br />' .
			'The prefer option indicates that NTP should favor the use of this server more than all others.' . '<br />' .
			'The no select option indicates that NTP should not use this server for time, but stats for this server will be collected and displayed.');

$section->addInput(new Form_Input(
	'ntporphan',
	'Orphan Mode',
	'text',
	$pconfig['ntporphan']
))->setHelp('Orphan mode allows the system clock to be used when no other clocks are available. ' .
			'The number here specifies the stratum reported during orphan mode and should normally be set to a number high enough ' .
			'to insure that any other servers available to clients are preferred over this server (default: 12).');

$section->addInput(new Form_Checkbox(
	'statsgraph',
	'NTP Graphs',
	'Enable RRD graphs of NTP statistics (default: disabled).',
	$pconfig['statsgraph']
));

$section->addInput(new Form_Checkbox(
	'logpeer',
	'Logging',
	'Log peer messages (default: disabled).',
	$pconfig['logpeer']
));

$section->addInput(new Form_Checkbox(
	'logsys',
	null,
	'Log system messages (default: disabled).',
	$pconfig['logsys']
))->setHelp('These options enable additional messages from NTP to be written to the System Log ' .
			'<a href="status_logs.php?logfile=ntpd">' . 'Status > System Logs > NTP' . '</a>.');

// Statistics logging section
$btnadv = new Form_Button(
	'btnadvstats',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'Statistics Logging',
	$btnadv
))->setHelp('Warning: These options will create persistent daily log files in /var/log/ntp.');

$section->addInput(new Form_Checkbox(
	'clockstats',
	null,
	'Log reference clock statistics (default: disabled).',
	$pconfig['clockstats']
));

$section->addInput(new Form_Checkbox(
	'loopstats',
	null,
	'Log clock discipline statistics (default: disabled).',
	$pconfig['loopstats']
));

$section->addInput(new Form_Checkbox(
	'peerstats',
	null,
	'Log NTP peer statistics (default: disabled).',
	$pconfig['peerstats']
));

// Leap seconds section
$btnadv = new Form_Button(
	'btnadvleap',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'Leap seconds',
	$btnadv
))->setHelp('A leap second file allows NTP to advertise an upcoming leap second addition or subtraction. ' .
			'Normally this is only useful if this server is a stratum 1 time server. ');

$section->addInput(new Form_Textarea(
	'leaptext',
	null,
	base64_decode(chunk_split($pconfig['leapsec']))
))->setHelp('Enter Leap second configuration as text OR select a file to upload.');

$section->addInput(new Form_Input(
	'leapfile',
	null,
	'file'
))->addClass('btn-default');

$form->add($section);

print($form);

?>

<script type="text/javascript">
//<![CDATA[
	// If this variable is declared, any help text will not be deleted when rows are added
	// IOW the help text will appear on every row
	retainhelp = true;
</script>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Show advanced stats options ============================================
	var showadvstats = false;

	function show_advstats(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			if (!$pconfig['clockstats'] && !$pconfig['loopstats'] && !$pconfig['peerstats']) {
				$showadv = false;
			} else {
				$showadv = true;
			}
?>
			showadvstats = <?php if ($showadv) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvstats = !showadvstats;
		}

		hideCheckbox('clockstats', !showadvstats);
		hideCheckbox('loopstats', !showadvstats);
		hideCheckbox('peerstats', !showadvstats);

		if (showadvstats) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#btnadvstats').html('<i class="fa fa-cog"></i> ' + text);
	}

	$('#btnadvstats').click(function(event) {
		show_advstats();
	});

	// Show advanced leap second options ======================================
	var showadvleap = false;

	function show_advleap(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			// Note: leapfile is not a field saved in the config, so no need to test for it here.
			// leapsec is the encoded text in the config, leaptext is not a pconfig[] key.
			if (empty($pconfig['leapsec'])) {
				$showadv = false;
			} else {
				$showadv = true;
			}
?>
			showadvleap = <?php if ($showadv) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvleap = !showadvleap;
		}

		hideInput('leaptext', !showadvleap);
		hideInput('leapfile', !showadvleap);

		if (showadvleap) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#btnadvleap').html('<i class="fa fa-cog"></i> ' + text);
	}

	$('#btnadvleap').click(function(event) {
		show_advleap();
	});

	// Set initial states
	show_advstats(true);
	show_advleap(true);

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();
});
//]]>
</script>

<?php include("foot.inc");
