<?php
/*
 * services_ntpd.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

global $ntp_poll_min_default, $ntp_poll_max_default, $ntp_server_types;
$ntp_poll_values = system_ntp_poll_values();
$auto_pool_suffix = "pool.ntp.org";
$max_candidate_peers = 25;
$min_candidate_peers = 4;

if (!is_array($config['ntpd'])) {
	$config['ntpd'] = array();
}

if (empty($config['ntpd']['interface'])) {
	if (is_array($config['installedpackages']['openntpd']) && is_array($config['installedpackages']['openntpd']['config']) &&
	    is_array($config['installedpackages']['openntpd']['config'][0]) && !empty($config['installedpackages']['openntpd']['config'][0]['interface'])) {
		$pconfig['interface'] = explode(",", $config['installedpackages']['openntpd']['config'][0]['interface']);
		unset($config['installedpackages']['openntpd']);
		write_config(gettext("Upgraded settings from openntpd"));
	} else {
		$pconfig['interface'] = array();
	}
} else {
	$pconfig['interface'] = explode(",", $config['ntpd']['interface']);
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (!empty($_POST['ntpmaxpeers']) && (!is_numericint($_POST['ntpmaxpeers']) ||
	    ($_POST['ntpmaxpeers'] < $min_candidate_peers) || ($_POST['ntpmaxpeers'] > $max_candidate_peers))) {
		$input_errors[] = sprintf(gettext("Max candidate pool peers must be a number between %d and %d"), $min_candidate_peers, $max_candidate_peers);
	}
	
	if ((strlen($pconfig['ntporphan']) > 0) && (!is_numericint($pconfig['ntporphan']) || ($pconfig['ntporphan'] < 1) || ($pconfig['ntporphan'] > 15))) {
		$input_errors[] = gettext("The supplied value for NTP Orphan Mode is invalid.");
	}

	if (!array_key_exists($pconfig['ntpminpoll'], $ntp_poll_values)) {
		$input_errors[] = gettext("The supplied value for Minimum Poll Interval is invalid.");
	}

	if (!array_key_exists($pconfig['ntpmaxpoll'], $ntp_poll_values)) {
		$input_errors[] = gettext("The supplied value for Maximum Poll Interval is invalid.");
	}

	for ($i = 0; $i < NUMTIMESERVERS; $i++) {
		if (isset($pconfig["servselect{$i}"]) && (($pconfig["servistype{$i}"] == 'pool') || 
		    (substr_compare($pconfig["server{$i}"], $auto_pool_suffix, strlen($pconfig["server{$i}"]) - strlen($auto_pool_suffix), strlen($auto_pool_suffix)) === 0))) {
			$input_errors[] = gettext("It is not possible to use 'No Select' for pools.");
		}
		if (!empty($pconfig["server{$i}"]) && !is_domain($pconfig["server{$i}"]) &&
		    !is_ipaddr($pconfig["server{$i}"])) {
			$input_errors[] = gettext("NTP Time Server names must be valid domain names, IPv4 addresses, or IPv6 addresses");
		}
	}

	if (is_numericint($pconfig['ntpminpoll']) &&
	    is_numericint($pconfig['ntpmaxpoll']) &&
	    ($pconfig['ntpmaxpoll'] < $pconfig['ntpminpoll'])) {
		$input_errors[] = gettext("The supplied value for Minimum Poll Interval is higher than NTP Maximum Poll Interval.");
	}

	if (isset($pconfig['serverauth'])) {
		if (empty($pconfig['serverauthkey'])) {
			$input_errors[] = gettext("The supplied value for NTP Authentication key can't be empty.");
		} elseif (($pconfig['serverauthalgo'] == 'md5') && ((strlen($pconfig['serverauthkey']) > 20) ||
		    !ctype_print($pconfig['serverauthkey']))) {
			$input_errors[] = gettext("The supplied value for NTP Authentication key for MD5 digest must be from 1 to 20 printable characters.");
		} elseif (($pconfig['serverauthalgo'] == 'sha1') && ((strlen($pconfig['serverauthkey']) != 40) ||
		    !ctype_xdigit($pconfig['serverauthkey']))) {
			$input_errors[] = gettext("The supplied value for NTP Authentication key for SHA1 digest must be hex-encoded string of 40 characters.");
		} elseif (($pconfig['serverauthalgo'] == 'sha256') && ((strlen($pconfig['serverauthkey']) != 64) ||
		    !ctype_xdigit($pconfig['serverauthkey']))) {
			$input_errors[] = gettext("The supplied value for NTP Authentication key for SHA256 digest must be hex-encoded string of 64 characters.");
		}
	}

	if (!$input_errors) {
		$config['ntpd']['enable'] = isset($_POST['enable']) ? 'enabled' : 'disabled';
		if (is_array($_POST['interface'])) {
			$config['ntpd']['interface'] = implode(",", $_POST['interface']);
		} elseif (isset($config['ntpd']['interface'])) {
			unset($config['ntpd']['interface']);
		}

		unset($config['ntpd']['prefer']);
		unset($config['ntpd']['noselect']);
		unset($config['ntpd']['ispool']);
		unset($config['ntpd']['ispeer']);
		$timeservers = '';

		for ($i = 0; $i < NUMTIMESERVERS; $i++) {
			$tserver = trim($_POST["server{$i}"]);
			if (!empty($tserver)) {
				$timeservers .= "{$tserver} ";
				if (isset($_POST["servprefer{$i}"])) {
					$config['ntpd']['prefer'] .= "{$tserver} ";
				}
				if (isset($_POST["servselect{$i}"])) {
					$config['ntpd']['noselect'] .= "{$tserver} ";
				}
				if ($_POST["servistype{$i}"] == 'pool') {
					$config['ntpd']['ispool'] .= "{$tserver} ";
				} elseif ($_POST["servistype{$i}"] == 'peer') {
					$config['ntpd']['ispeer'] .= "{$tserver} ";
				}
			}
		}
		if (trim($timeservers) == "") {
			$timeservers = "pool.ntp.org";
		}
		$config['system']['timeservers'] = trim($timeservers);

		if (!empty($pconfig['ntpmaxpeers'])) {
			$config['ntpd']['ntpmaxpeers'] = $pconfig['ntpmaxpeers'];
		} else {
			unset($config['ntpd']['ntpmaxpeers']);
		}
		$config['ntpd']['orphan'] = trim($pconfig['ntporphan']);
		$config['ntpd']['ntpminpoll'] = $pconfig['ntpminpoll'];
		$config['ntpd']['ntpmaxpoll'] = $pconfig['ntpmaxpoll'];
		$config['ntpd']['dnsresolv'] = $pconfig['dnsresolv'];

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

		if (!empty($_POST['serverauth'])) {
			$config['ntpd']['serverauth'] = $_POST['serverauth'];
			$config['ntpd']['serverauthkey'] = base64_encode(trim($_POST['serverauthkey']));
			$config['ntpd']['serverauthalgo'] = $_POST['serverauthalgo'];
		} elseif (isset($config['ntpd']['serverauth'])) {
			unset($config['ntpd']['serverauth']);
			unset($config['ntpd']['serverauthkey']);
			unset($config['ntpd']['serverauthalgo']);
		}

		write_config("Updated NTP Server Settings");

		$changes_applied = true;
		$retval = 0;
		$retval |= system_ntp_configure();
	}
}

function build_interface_list() {
	global $pconfig;

	$iflist = array('options' => array(), 'selected' => array());

	$interfaces = get_configured_interface_with_descr();
	$interfaces['lo0'] = "Localhost";

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

init_config_arr(array('ntpd'));
$pconfig = &$config['ntpd'];
$pconfig['enable'] = ($config['ntpd']['enable'] != 'disabled') ? 'enabled' : 'disabled';
if (empty($pconfig['interface'])) {
	$pconfig['interface'] = array();
} else {
	$pconfig['interface'] = explode(",", $pconfig['interface']);
}
$pgtitle = array(gettext("Services"), gettext("NTP"), gettext("Settings"));
$pglinks = array("", "@self", "@self");
$shortcut_section = "ntp";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
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

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable NTP Server',
	($pconfig['enable'] == 'enabled')
))->setHelp('You may need to disable NTP if %1$s is running in a virtual machine and the host is responsible for the clock.', $g['product_label']);

$iflist = build_interface_list();

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$iflist['selected'],
	$iflist['options'],
	true
))->setHelp('Interfaces without an IP address will not be shown.%1$s' .
			'Selecting no interfaces will listen on all interfaces with a wildcard.%1$s' .
			'Selecting all interfaces will explicitly listen on only the interfaces/IPs specified.', '<br />');

$timeservers = explode(' ', $config['system']['timeservers']);
$maxrows = max(count($timeservers), 1);
for ($counter=0; $counter < $maxrows; $counter++) {
	$group = new Form_Group($counter == 0 ? 'Time Servers':'');
	$group->addClass('repeatable');
	$group->setAttribute('max_repeats', NUMTIMESERVERS);
	$group->setAttribute('max_repeats_alert', sprintf(gettext('%d is the maximum number of configured servers.'), NUMTIMESERVERS));

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

	if ((substr_compare($timeservers[$counter], $auto_pool_suffix, strlen($timeservers[$counter]) - strlen($auto_pool_suffix), strlen($auto_pool_suffix)) === 0) || (isset($config['ntpd']['ispool']) && isset($timeservers[$counter]) && substr_count($config['ntpd']['ispool'], $timeservers[$counter]))) {
		$servertype = 'pool';
	} elseif (isset($config['ntpd']['ispeer']) && isset($timeservers[$counter]) && substr_count($config['ntpd']['ispeer'], $timeservers[$counter])) {
		$servertype = 'peer';
	} else {
		$servertype = 'server';
	}

	$group->add(new Form_Select(
		'servistype' . $counter,
		null,
		$servertype,
		$ntp_server_types
	 ))->sethelp('Type')->setWidth(2);

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
))->setHelp(
	'NTP will only sync if a majority of the servers agree on the time.  For best results you should configure between 3 and 5 servers ' .
	'(%4$sNTP support pages recommend at least 4 or 5%5$s), or a pool. If only one server is configured, it %2$swill%3$s be believed, and if 2 servers ' .
	'are configured and they disagree, %2$sneither%3$s will be believed. Options:%1$s' .
	'%2$sPrefer%3$s - NTP should favor the use of this server more than all others.%1$s' .
	'%2$sNo Select%3$s - NTP should not use this server for time, but stats for this server will be collected and displayed.%1$s' .
	'%2$sType%3$s - Server, Peer or a Pool of NTP servers and not a single address. This is assumed for *.pool.ntp.org.',
	'<br />',
	'<b>',
	'</b>',
	'<a target="_blank" href="https://support.ntp.org/bin/view/Support/ConfiguringNTP">',
	'</a>'
	);

$section->addInput(new Form_Input(
	'ntpmaxpeers',
	'Max candidate pool peers',
	'number',
	$pconfig['ntpmaxpeers'],
	['min' => $min_candidate_peers, 'max' => $max_candidate_peers]
))->setHelp('Maximum number of candidate peers in the NTP pool. This value should be set low enough to provide sufficient alternate sources ' .
	    'while not contacting an excessively large number of peers. ' .
	    'Many servers inside public pools are provided by volunteers, ' .
	    'and a large candidate pool places unnecessary extra load ' .
	    'on the volunteer time servers for little to no added benefit. (Default: 5).');

$section->addInput(new Form_Input(
	'ntporphan',
	'Orphan Mode',
	'text',
	$pconfig['orphan'],
	['placeholder' => "12"]
))->setHelp('Orphan mode allows the system clock to be used when no other clocks are available. ' .
			'The number here specifies the stratum reported during orphan mode and should normally be set to a number high enough ' .
			'to insure that any other servers available to clients are preferred over this server (default: 12).');

$section->addInput(new Form_Select(
	'ntpminpoll',
	'Minimum Poll Interval',
	$pconfig['ntpminpoll'],
	$ntp_poll_values
))->setHelp('Minimum poll interval for NTP messages. If set, must be less than or equal to Maximum Poll Interval.');

$section->addInput(new Form_Select(
	'ntpmaxpoll',
	'Maximum Poll Interval',
	$pconfig['ntpmaxpoll'],
	$ntp_poll_values
))->setHelp('Maximum poll interval for NTP messages. If set, must be greater than or equal to Minimum Poll Interval.');

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
))->setHelp('These options enable additional messages from NTP to be written to the System Log %1$sStatus > System Logs > NTP%2$s',
			'<a href="status_logs.php?logfile=ntpd">', '</a>.');

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
))->setHelp(
	'Leap seconds may be added or subtracted at the end of June or December. Leap seconds are administered by the ' .
	'%1$sIERS%2$s, who publish them in their Bulletin C approximately 6 - 12 months in advance.  Normally this correction ' .
	'should only be needed if the server is a stratum 1 NTP server, but many NTP servers do not advertise an upcoming leap ' .
	'second when other NTP servers synchronise to them.%3$s%4$sIf the leap second is important to your network services, ' .
	'it is %6$sgood practice%2$s to download and add the leap second file at least a day in advance of any time correction%5$s.%3$s ' .
	'More information and files for downloading can be found on their %1$swebsite%2$s, and also on the %7$NIST%2$s and %8$sNTP%2$s websites.',
	'<a target="_blank" href="https://www.iers.org">',
	'</a>',
	'<br />',
	'<b>',
	'</b>',
	'<a target="_blank" href="https://support.ntp.org/bin/view/Support/ConfiguringNTP">',
	'<a target="_blank" href="https://www.nist.gov">',
	'<a target="_blank" href="https://www.ntp.org">'
);

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

$section->addInput(new Form_Select(
	'dnsresolv',
	'DNS Resolution',
	$pconfig['dnsresolv'],
	array(
		'auto' => 'Auto',
		'inet' => 'IPv4',
		'inet6' => 'IPv6',
	)
))->setHelp('Force NTP peers DNS resolution IP protocol. Do not affect pools.');

$section->addInput(new Form_Checkbox(
	'serverauth',
	'Enable NTP Server Authentication',
	'Enable NTPv3 authentication (RFC 1305)',
	$pconfig['serverauth']
))->setHelp('Authentication allows the NTP client to confirm it is communicating with the intended server, ' .
	    'which protects against man-in-the-middle attacks.');

$group = new Form_Group('Authentication key');
$group->addClass('ntpserverauth');

$group->add(new Form_IpAddress(
	'serverauthkey',
	'NTP Authentication key',
	base64_decode($pconfig['serverauthkey']),
	['placeholder' => 'NTP Authentication key']
))->setHelp(
	'Key format: %1$s MD5 - The key is 1 to 20 printable characters %1$s' .
	'SHA1 - The key is a hex-encoded ASCII string of 40 characters %1$s' .
	'SHA256 - The key is a hex-encoded ASCII string of 64 characters',
	'<br />'
);

$group->add(new Form_Select(
	'serverauthalgo',
	null,
	$pconfig['serverauthalgo'],
	$ntp_auth_halgos
))->setWidth(3)->setHelp('Digest algorithm');

$section->add($group);

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

	function change_serverauth() {
		hideClass('ntpserverauth', !($('#serverauth').prop('checked')));
	}

	$('#btnadvleap').click(function(event) {
		show_advleap();
	});

	$('#serverauth').change(function () {
		change_serverauth();
	});

	// Set initial states
	show_advstats(true);
	show_advleap(true);
	change_serverauth();

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();
});
//]]>
</script>

<?php include("foot.inc");
