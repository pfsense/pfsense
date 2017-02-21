<?php
/*
 * services_ntpd_acts.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-ntpd-acts
##|*NAME=Services: NTP ACTS
##|*DESCR=Allow access to the 'Services: NTP ACTS' page.
##|*MATCH=services_ntpd_acts.php*
##|-PRIV

require_once("guiconfig.inc");

if ($_POST) {
	unset($input_errors);

	if (!$input_errors) {
		if (!empty($_POST['port']) && file_exists('/dev/'.$_POST['port'])) {
			$config['ntpd']['acts']['port'] = $_POST['port'];
		} else {
			/* if port is not set, remove all the config */
			unset($config['ntpd']['acts']);
		}

		if (!empty($_POST['speed'])) {
			$config['ntpd']['acts']['speed'] = $_POST['speed'];
		} elseif (isset($config['ntpd']['acts']['speed'])) {
			unset($config['ntpd']['acts']['speed']);
		}
		
		if (!empty($_POST['mode'])) {
			$config['ntpd']['acts']['mode'] = $_POST['mode'];
		} elseif (isset($config['ntpd']['acts']['mode'])) {
			$config['ntpd']['acts']['mode'] = 0;
		}
		
		if (!empty($_POST['minpoll'])) {
			$config['ntpd']['acts']['minpoll'] = $_POST['minpoll'];
		} elseif (isset($config['ntpd']['acts']['minpoll'])) {
			$config['ntpd']['acts']['minpoll'] = 12;
		}
		
		if (!empty($_POST['maxpoll'])) {
			$config['ntpd']['acts']['maxpoll'] = $_POST['maxpoll'];
		} elseif (isset($config['ntpd']['acts']['maxpoll'])) {
			$config['ntpd']['acts']['maxpoll'] = 17;
		}

		if (!empty($_POST['fudge1'])) {
			$config['ntpd']['acts']['fudge1'] = $_POST['fudge1'];
		} elseif (isset($config['ntpd']['acts']['fudge1'])) {
			unset($config['ntpd']['acts']['fudge1']);
		}

		if (!empty($_POST['stratum'])) {
			$config['ntpd']['acts']['stratum'] = $_POST['stratum'];
		} elseif (isset($config['ntpd']['acts']['stratum'])) {
			unset($config['ntpd']['acts']['stratum']);
		}
		
		if (!empty($_POST['prefer'])) {
			$config['ntpd']['acts']['prefer'] = $_POST['prefer'];
		} elseif (isset($config['ntpd']['acts']['prefer'])) {
			unset($config['ntpd']['acts']['prefer']);
		}

		if (!empty($_POST['noselect'])) {
			$config['ntpd']['acts']['noselect'] = $_POST['noselect'];
		} elseif (isset($config['ntpd']['acts']['noselect'])) {
			unset($config['ntpd']['acts']['noselect']);
		}
		
		if (!empty($_POST['flag1'])) {
			$config['ntpd']['acts']['flag1'] = $_POST['flag1'];
		} elseif (isset($config['ntpd']['acts']['flag1'])) {
			unset($config['ntpd']['acts']['flag1']);
		}

		if (!empty($_POST['flag2'])) {
			$config['ntpd']['acts']['flag2'] = $_POST['flag2'];
		} elseif (isset($config['ntpd']['acts']['flag2'])) {
			unset($config['ntpd']['acts']['flag2']);
		}

		if (!empty($_POST['refid'])) {
			$config['ntpd']['acts']['refid'] = $_POST['refid'];
		} elseif (isset($config['ntpd']['acts']['refid'])) {
			unset($config['ntpd']['acts']['refid']);
		}
		
		if (!empty($_POST['phonecmd'])) {
			$config['ntpd']['acts']['phonecmd'] = $_POST['phonecmd'];
		} elseif (isset($config['ntpd']['acts']['phonecmd'])) {
			$config['ntpd']['acts']['phonecmd'] = 'atdt13034944774 atdt18083354721';
		}

		write_config("Updated NTP ACTS Settings");

		$changes_applied = true;
		$retval = 0;
		$retval |= system_ntp_configure();
	}
} else {
	/* set defaults if they do not already exist */
	if (!is_array($config['ntpd']) || !is_array($config['ntpd']['acts'])) {
		if (!is_array($config['ntpd'])) {
			$config['ntpd'] = array();
		}
		
		if (!is_array($config['ntpd']['acts'])) {
			$config['ntpd']['acts'] = array();
		}
		$config['ntpd']['acts']['speed'] = '19200';
		$config['ntpd']['acts']['minpoll'] = 12;
		$config['ntpd']['acts']['maxpoll'] = 17;
		$config['ntpd']['acts']['phonecmd'] = 'atdt13034944774 atdt18083354721';
	}
}

$pconfig = &$config['ntpd']['acts'];

$pgtitle = array(gettext("Services"), gettext("NTP"), gettext("ACTS"));
$pglinks = array("", "services_ntpd.php", "@self");
$shortcut_section = "ntp";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "services_ntpd.php");
$tab_array[] = array(gettext("ACLs"), false, "services_ntpd_acls.php");
$tab_array[] = array(gettext("Serial GPS"), false, "services_ntpd_gps.php");
$tab_array[] = array(gettext("PPS"), false, "services_ntpd_pps.php");
$tab_array[] = array(gettext("ACTS"), true, "services_ntpd_acts.php");
display_top_tabs($tab_array);

$form = new Form;

$section = new Form_Section('NTP Serial ACTS Configuration');

$section->addInput(new Form_StaticText(
	'Notes',
	'This driver supports the US (NIST and USNO) and European (PTB (Germany), NPL (UK), etc.) modem time services, as well as Spectracom GPS and WWVB receivers connected via a modem. The driver periodically dials a number from a telephone list, receives the timecode data and calculates the local clock correction. It is designed primarily for backup when neither a radio clock nor connectivity to Internet time servers are available. It can also be configured to operate full period.'
));

$serialports = glob("/dev/cua?[0-9]{,.[0-9]}", GLOB_BRACE);

if (!empty($serialports)) {
	$splist = array();

	foreach ($serialports as $port) {
		$shortport = substr($port, 5);
		$splist[$shortport] = $shortport;
	}

	$section->addInput(new Form_Select(
		'port',
		'Serial Port',
		$pconfig['port'],
		['' => gettext('None')] + $splist
	))->setHelp('All serial ports are listed, be sure to pick the port with the ACTS modem attached. ');

	$section->addInput(new Form_Select(
		'speed',
		'Baud Rate',
		$pconfig['speed'],
		['1200' => '1200', '2400' => '2400', '4800' => '4800', '9600' => '9600', '19200' => '19200']
	))->setHelp('Set to match modem capabilities and ACTS source. (default: 19200)');
}

$section->addInput(new Form_Select(
	'mode',
	'Mode',
	$pconfig['mode'],
	[0 => gettext('Backup'), 1 => gettext('Auto'), 2 => gettext('Manual')]
))->setHelp('Calling program mode. (default: Backup)');

$section->addInput(new Form_Input(
	'minpoll',
	'Min Poll',
	'text',
	$pconfig['minpoll']
))->setHelp('The minimum poll interval (default: 12, 1.1 hours).');

$section->addInput(new Form_Input(
	'maxpoll',
	'Max Poll',
	'text',
	$pconfig['maxpoll']
))->setHelp('The maximum poll interval (default: 17, 36 hours).');

$section->addInput(new Form_Input(
	'fudge1',
	'Fudge Time',
	'text',
	$pconfig['fudge1']
))->setHelp('Fudge time is used to specify the time offset calibration factor, in seconds and fraction (default: 0.0).');

$section->addInput(new Form_Input(
	'stratum',
	'Stratum',
	'text',
	$pconfig['stratum']
))->setHelp('This may be used to change the ACTS Clock stratum (default: 0). This may be useful to, for some reason, have ntpd prefer a different clock and just monitor this source.');

$section->addInput(new Form_Checkbox(
	'prefer',
	'Flags',
	'Prefer this clock (default: unchecked).',
	$pconfig['prefer']
));

$section->addInput(new Form_Checkbox(
	'noselect',
	null,
	'Do not use this clock, display for reference only (default: unchecked).',
	$pconfig['noselect']
));

$section->addInput(new Form_Checkbox(
	'flag1',
	null,
	'Enable dialing if in manual mode (default: unchecked).',
	$pconfig['flag1']
));

$section->addInput(new Form_Checkbox(
	'flag2',
	null,
	'Enables port locking (default: unchecked). Allows the modem to be shared when not in use by this driver.',
	$pconfig['flag2']
));

$section->addInput(new Form_Input(
	'refid',
	'Clock ID',
	'text',
	$pconfig['refid'],
	['placeholder' => '1 to 4 characters']
))->setHelp('This may be used to change the ACTS Clock ID (default: Set by the driver to (one of) NIST, USNO, PTB or WWVB).');

$section->addInput(new Form_Input(
	'phonecmd',
	'Phone Dial Command',
	'text',
	$pconfig['phonecmd']
))->setHelp('This command is used in conjunction with the ACTS modem driver. The arguments consist of a maximum of 10 telephone numbers used to dial USNO, NIST or European time services. The Hayes command ATDT is normally prepended to the number, which can contain other modem control codes as well. (default: "atdt13034944774 atdt18083354721").');

$form->add($section);
print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	
	// minpoll shall always be less than or equal to maxpoll and
	// maxpoll shall always be more than or equal to minpoll.
	$('#minpoll').on('change', function() {
		if( $(this).val() === '') {
			$(this).val(12);
		}
		if(parseInt($(this).val()) > parseInt($('#maxpoll').val())) {
			$('#maxpoll').val($(this).val());
		}
	});

	$('#maxpoll').on('change', function() {
		if( $(this).val() === '') {
			$(this).val(17);
		}
		if(parseInt($(this).val()) < parseInt($('#minpoll').val())) {
			$('#minpoll').val($(this).val());
		}
	});

	$('#stratum').on('change', function() {
		if(parseInt($(this).val()) > 17) {
			$(this).val(17);
		}
	});

	// Checkboxes prefer and noselect are mutually exclusive.
	$('#prefer').click(function() {
		if ($(this).is(':checked')) {
			$('#noselect').prop('checked', false);
		}
	});

	$('#noselect').click(function() {
		if ($(this).is(':checked')) {
			$('#prefer').prop('checked', false);
		}
	});
});
//]]>
</script>

<?php include("foot.inc");
