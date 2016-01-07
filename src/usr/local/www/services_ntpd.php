<?php
/*
	services_ntpd.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2013 Dagorlad
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
##|*IDENT=page-services-ntpd
##|*NAME=Services: NTP
##|*DESCR=Allow access to the 'Services: NTP' page.
##|*MATCH=services_ntpd.php*
##|-PRIV

define(NUMTIMESERVERS, 10);		// The maximum number of configurable time servers
require("guiconfig.inc");
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
		write_config("Upgraded settings from openttpd");
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

		if (empty($_POST['kod'])) {
			$config['ntpd']['kod'] = 'on';
		} elseif (isset($config['ntpd']['kod'])) {
			unset($config['ntpd']['kod']);
		}

		if (empty($_POST['nomodify'])) {
			$config['ntpd']['nomodify'] = 'on';
		} elseif (isset($config['ntpd']['nomodify'])) {
			unset($config['ntpd']['nomodify']);
		}

		if (!empty($_POST['noquery'])) {
			$config['ntpd']['noquery'] = $_POST['noquery'];
		} elseif (isset($config['ntpd']['noquery'])) {
			unset($config['ntpd']['noquery']);
		}

		if (!empty($_POST['noserve'])) {
			$config['ntpd']['noserve'] = $_POST['noserve'];
		} elseif (isset($config['ntpd']['noserve'])) {
			unset($config['ntpd']['noserve']);
		}

		if (empty($_POST['nopeer'])) {
			$config['ntpd']['nopeer'] = 'on';
		} elseif (isset($config['ntpd']['nopeer'])) {
			unset($config['ntpd']['nopeer']);
		}

		if (empty($_POST['notrap'])) {
			$config['ntpd']['notrap'] = 'on';
		} elseif (isset($config['ntpd']['notrap'])) {
			unset($config['ntpd']['notrap']);
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

		if (!empty($_POST['leaptxt'])) {
			$config['ntpd']['leapsec'] = base64_encode($_POST['leaptxt']);
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
	$carplist = get_configured_carp_interface_list();

	foreach ($carplist as $cif => $carpip) {
		$interfaces[$cif] = $carpip . " (" . get_vip_descr($carpip) .")";
	}

	$aliaslist = get_configured_ip_aliases_list();

	foreach ($aliaslist as $aliasip => $aliasif) {
		$interfaces[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
	}

	$size = (count($interfaces) < 10) ? count($interfaces) : 10;

	foreach ($interfaces as $iface => $ifacename) {
		if (!is_ipaddr(get_interface_ip($iface)) && !is_ipaddr($iface)) {
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
$pgtitle = array(gettext("Services"), gettext("NTP"), gettext("NTP"));
$shortcut_section = "ntp";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("NTP"), true, "services_ntpd.php");
$tab_array[] = array(gettext("Serial GPS"), false, "services_ntpd_gps.php");
$tab_array[] = array(gettext("PPS"), false, "services_ntpd_pps.php");
display_top_tabs($tab_array);

$form = new Form;

$section = new Form_Section('NTP server configuration');

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
		'Delete'
	))->removeClass('btn-primary')->addClass('btn-warning');

	 $section->add($group);
}

$section->addInput(new Form_Button(
	'addrow',
	'Add'
))->removeClass('btn-primary')->addClass('btn-success');

$section->addInput(new Form_StaticText(
	null,
	$btnaddrow
))->setHelp('For best results three to five servers should be configured here.' . '<br />' .
			'The prefer option indicates that NTP should favor the use of this server more than all others.' . '<br />' .
			'The noselect option indicates that NTP should not use this server for time, but stats for this server will be collected and displayed.');

$section->addInput(new Form_Input(
	'ntporphan',
	'Orphan Mode',
	'text',
	$pconfig['ntporphan']
))->setHelp('Orphan mode allows the system clock to be used when no other clocks are available. ' .
			'The number here specifies the stratum reported during orphan mode and should normally be set to a number high enough ' .
			'to insure that any other servers available to clients are preferred over this server. (default: 12).');

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
			'<a href="diag_logs_ntpd.php">' . 'Status > System Logs > NTP' . '</a>');

// Statistics logging section
$btnadvstats = new Form_Button(
	'btnadvstats',
	'Advanced'
);

$btnadvstats->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Statistics Logging',
	$btnadvstats
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

// Access restrictions section
$btnadvrestr = new Form_Button(
	'btnadvrestr',
	'Advanced'
);

$btnadvrestr->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Access Restrictions',
	$btnadvrestr
))->setHelp('These options control access to NTP from the WAN.');

$section->addInput(new Form_Checkbox(
	'kod',
	null,
	'Enable Kiss-o\'-death packets (default: checked).',
	!$pconfig['kod']
));

$section->addInput(new Form_Checkbox(
	'nomodify',
	null,
	'Deny state modifications (i.e. run time configuration) by ntpq and ntpdc (default: checked).',
	!$pconfig['nomodify']
));

$section->addInput(new Form_Checkbox(
	'noquery',
	null,
	'Disable ntpq and ntpdc queries (default: unchecked).',
	$pconfig['noquery']
));

$section->addInput(new Form_Checkbox(
	'noserve',
	null,
	'Disable all except ntpq and ntpdc queries (default: unchecked).',
	$pconfig['noserve']
));

$section->addInput(new Form_Checkbox(
	'nopeer',
	null,
	'Deny packets that attempt a peer association (default: checked).',
	!$pconfig['nopeer']
));

$section->addInput(new Form_Checkbox(
	'notrap',
	null,
	'Deny mode 6 control message trap service (default: checked).',
	!$pconfig['notrap']
))->addClass('advrestrictions');

// Leap seconds section
$btnleap = new Form_Button(
	'btnleap',
	'Advanced'
);

$btnleap->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Leap seconds',
	$btnleap
))->setHelp('A leap second file allows NTP to advertize an upcoming leap second addition or subtraction. ' .
			'Normally this is only useful if this server is a stratum 1 time server. ');

$section->addInput(new Form_Textarea(
	'leaptext',
	null,
	base64_decode(chunk_split($pconfig['leapsec']))
))->setHelp('Enter Leap second configuration as text OR select a file to upload');

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

	// Make the ‘clear’ button a plain button, not a submit button
	$('#btnadvstats').prop('type','button');

	// On click, show the controls in the stats section
	$("#btnadvstats").click(function() {
		hideCheckbox('clockstats', false);
		hideCheckbox('loopstats', false);
		hideCheckbox('peerstats', false);
	});

	// Make the ‘clear’ button a plain button, not a submit button
	$('#btnadvrestr').prop('type','button');

	// On click, show the controls in the restrictions section
	$("#btnadvrestr").click(function() {
		hideCheckbox('kod', false);
		hideCheckbox('nomodify', false);
		hideCheckbox('noquery', false);
		hideCheckbox('noserve', false);
		hideCheckbox('nopeer', false);
		hideCheckbox('notrap', false);
	});

	// Make the ‘btnleap’ button a plain button, not a submit button
	$('#btnleap').prop('type','button');

	// On click, show the controls in the leap seconds section
	$("#btnleap").click(function() {
		hideInput('leaptext', false);
		hideInput('leapfile', false);
	});

	// Set intial states
	hideCheckbox('clockstats', true);
	hideCheckbox('loopstats', true);
	hideCheckbox('peerstats', true);
	hideCheckbox('kod', true);
	hideCheckbox('nomodify', true);
	hideCheckbox('noquery', true);
	hideCheckbox('noserve', true);
	hideCheckbox('nopeer', true);
	hideCheckbox('notrap', true);
	hideInput('leaptext', true);
	hideInput('leapfile', true);

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();
});
//]]>
</script>

<?php include("foot.inc");
